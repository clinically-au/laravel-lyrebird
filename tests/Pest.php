<?php

declare(strict_types=1);

use Clinically\Lyrebird\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Generate a P-256 keypair standing in for Lyrebird's Secure Launch keypair.
 *
 * @return array{private: OpenSSLAsymmetricKey, publicKeyBase64Jwk: string}
 */
function lyrebirdTestKeypair(): array
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
        'private_key_bits' => 384,
    ]);

    $details = openssl_pkey_get_details($key);

    $jwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => b64url(str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)),
        'y' => b64url(str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT)),
    ];

    return [
        'private' => $key,
        'publicKeyBase64Jwk' => base64_encode(json_encode($jwk)),
    ];
}

/**
 * Reconstruct an OpenSSL EC public key from a JWK (the ephemeral key Lyrebird
 * would import on its side).
 *
 * @param  array{x: string, y: string}  $jwk
 */
function ecPublicKeyFromJwk(array $jwk): OpenSSLAsymmetricKey
{
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
    $der = $prefix.b64urlDecode($jwk['x']).b64urlDecode($jwk['y']);
    $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----'."\n";

    return openssl_pkey_get_public($pem);
}

/**
 * Decrypt a Secure Launch payload as Lyrebird would, returning the plaintext.
 *
 * @param  array{ephemeralPublicKey: string, ciphertext: string, iv: string, authTag: string}  $payload
 */
function decryptSecureLaunchPayload(array $payload, OpenSSLAsymmetricKey $privateKey): string
{
    $ephemeralJwk = json_decode(base64_decode($payload['ephemeralPublicKey']), true);
    $ephemeralPublicKey = ecPublicKeyFromJwk($ephemeralJwk);

    $sharedSecret = openssl_pkey_derive($ephemeralPublicKey, $privateKey);

    $plaintext = openssl_decrypt(
        base64_decode($payload['ciphertext']),
        'aes-256-gcm',
        $sharedSecret,
        OPENSSL_RAW_DATA,
        base64_decode($payload['iv']),
        base64_decode($payload['authTag']),
    );

    if ($plaintext === false) {
        throw new RuntimeException('Failed to decrypt Secure Launch payload — keys did not agree.');
    }

    return $plaintext;
}

function b64url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function b64urlDecode(string $value): string
{
    $standard = strtr($value, '-_', '+/');
    $remainder = strlen($standard) % 4;

    if ($remainder > 0) {
        $standard .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode($standard);
}
