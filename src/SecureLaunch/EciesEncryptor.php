<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\SecureLaunch;

use Clinically\Lyrebird\Exceptions\SecureLaunchException;
use OpenSSLAsymmetricKey;

/**
 * Implements the encryption half of Lyrebird Secure Launch V2 — ECIES using an
 * ephemeral ECDH key agreement on the NIST P-256 curve with AES-256-GCM.
 *
 * The ECDH shared secret (the raw 32-byte X-coordinate) is used directly as the
 * AES-256 key, with no KDF, matching the WebCrypto reference implementation so
 * Lyrebird's private key can reproduce the same key and decrypt the payload.
 */
final class EciesEncryptor
{
    private const PAYLOAD_VERSION = '1.1';

    /**
     * ASN.1 SubjectPublicKeyInfo prefix for an uncompressed EC point on the
     * prime256v1 (NIST P-256) curve. Followed by 0x04 || X(32 bytes) || Y(32 bytes).
     */
    private const P256_SPKI_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d03010703420004';

    public function __construct(private readonly ?string $publicKeyBase64Jwk) {}

    /**
     * Encrypt the given plaintext for Lyrebird's configured public key.
     *
     * @return array{version: string, ephemeralPublicKey: string, ciphertext: string, iv: string, authTag: string}
     */
    public function encrypt(string $plaintext): array
    {
        $lyrebirdPublicKey = $this->importLyrebirdPublicKey();

        $ephemeral = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            // Ignored for EC (the curve sets the key size) but required to be
            // non-zero on PHP builds with no discoverable openssl.cnf, where
            // default_bits is 0 and key generation otherwise refuses to run.
            'private_key_bits' => 384,
        ]);

        if (! $ephemeral instanceof OpenSSLAsymmetricKey) {
            throw new SecureLaunchException('Failed to generate an ephemeral P-256 keypair.');
        }

        $sharedSecret = openssl_pkey_derive($lyrebirdPublicKey, $ephemeral);

        if ($sharedSecret === false || strlen($sharedSecret) !== 32) {
            throw new SecureLaunchException('ECDH key agreement failed.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $sharedSecret, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($ciphertext === false) {
            throw new SecureLaunchException('AES-256-GCM encryption failed.');
        }

        return [
            'version' => self::PAYLOAD_VERSION,
            'ephemeralPublicKey' => base64_encode($this->exportPublicJwk($ephemeral)),
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'authTag' => base64_encode($tag),
        ];
    }

    private function importLyrebirdPublicKey(): OpenSSLAsymmetricKey
    {
        if ($this->publicKeyBase64Jwk === null || $this->publicKeyBase64Jwk === '') {
            throw new SecureLaunchException('No Lyrebird public key configured (lyrebird.secure_launch.public_key).');
        }

        $json = base64_decode($this->publicKeyBase64Jwk, true);

        if ($json === false) {
            throw new SecureLaunchException('Lyrebird public key is not valid base64.');
        }

        /** @var mixed $jwk */
        $jwk = json_decode($json, true);

        if (! is_array($jwk)
            || ($jwk['kty'] ?? null) !== 'EC'
            || ($jwk['crv'] ?? null) !== 'P-256'
            || ! isset($jwk['x'], $jwk['y'])
            || ! is_string($jwk['x'])
            || ! is_string($jwk['y'])
        ) {
            throw new SecureLaunchException('Lyrebird public key must be a base64-encoded EC P-256 JWK.');
        }

        $x = self::base64UrlDecode($jwk['x']);
        $y = self::base64UrlDecode($jwk['y']);

        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw new SecureLaunchException('Lyrebird public key coordinates are malformed.');
        }

        $prefix = hex2bin(self::P256_SPKI_PREFIX_HEX);

        if ($prefix === false) {
            throw new SecureLaunchException('Invalid SPKI prefix.');
        }

        $der = $prefix.$x.$y;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----'."\n";

        $key = openssl_pkey_get_public($pem);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new SecureLaunchException('Failed to import the Lyrebird public key.');
        }

        return $key;
    }

    private function exportPublicJwk(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y']) || ! is_string($details['ec']['x']) || ! is_string($details['ec']['y'])) {
            throw new SecureLaunchException('Failed to export the ephemeral public key.');
        }

        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => self::base64UrlEncode(self::pad32($details['ec']['x'])),
            'y' => self::base64UrlEncode(self::pad32($details['ec']['y'])),
        ];

        $json = json_encode($jwk, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new SecureLaunchException('Failed to encode the ephemeral public key.');
        }

        return $json;
    }

    private static function pad32(string $bytes): string
    {
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $standard = strtr($value, '-_', '+/');
        $remainder = strlen($standard) % 4;

        if ($remainder > 0) {
            $standard .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($standard, true);

        if ($decoded === false) {
            throw new SecureLaunchException('Invalid base64url value in JWK.');
        }

        return $decoded;
    }
}
