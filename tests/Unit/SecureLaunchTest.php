<?php

declare(strict_types=1);

use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\Exceptions\SecureLaunchException;
use Clinically\Lyrebird\SecureLaunch\EciesEncryptor;
use Clinically\Lyrebird\SecureLaunch\SecureLaunchUrlBuilder;

describe('EciesEncryptor', function (): void {
    it('produces a payload Lyrebird can decrypt with its private key', function (): void {
        $keypair = lyrebirdTestKeypair();
        $encryptor = new EciesEncryptor($keypair['publicKeyBase64Jwk']);

        $payload = $encryptor->encrypt('the-secret-plaintext');

        expect($payload['version'])->toBe('1.1')
            ->and($payload)->toHaveKeys(['ephemeralPublicKey', 'ciphertext', 'iv', 'authTag']);

        $decrypted = decryptSecureLaunchPayload($payload, $keypair['private']);

        expect($decrypted)->toBe('the-secret-plaintext');
    });

    it('uses a 12-byte IV and 16-byte auth tag', function (): void {
        $keypair = lyrebirdTestKeypair();
        $payload = (new EciesEncryptor($keypair['publicKeyBase64Jwk']))->encrypt('x');

        expect(strlen(base64_decode($payload['iv'])))->toBe(12)
            ->and(strlen(base64_decode($payload['authTag'])))->toBe(16);
    });

    it('produces a fresh ephemeral key each call (forward secrecy)', function (): void {
        $keypair = lyrebirdTestKeypair();
        $encryptor = new EciesEncryptor($keypair['publicKeyBase64Jwk']);

        $first = $encryptor->encrypt('x');
        $second = $encryptor->encrypt('x');

        expect($first['ephemeralPublicKey'])->not->toBe($second['ephemeralPublicKey']);
    });

    it('throws when no public key is configured', function (): void {
        (new EciesEncryptor(null))->encrypt('x');
    })->throws(SecureLaunchException::class);

    it('throws when the public key is not a valid EC P-256 JWK', function (): void {
        (new EciesEncryptor(base64_encode('{"kty":"RSA"}')))->encrypt('x');
    })->throws(SecureLaunchException::class);
});

describe('SecureLaunchUrlBuilder', function (): void {
    it('builds a launch URL whose encrypted payload round-trips to the patient context', function (): void {
        $keypair = lyrebirdTestKeypair();
        $builder = new SecureLaunchUrlBuilder(
            new EciesEncryptor($keypair['publicKeyBase64Jwk']),
            'https://app.lyrebirdhealth.com',
            '/app',
        );

        $url = $builder->build(new PatientContext(
            firstName: 'John',
            lastName: 'Doe',
            gender: 'M',
            patientAccountId: 'PAT_12345',
            userId: 'DR_789',
            dateOfBirth: '19850615',
            userName: 'Dr Jane Wilson',
        ));

        expect($url)->toStartWith('https://app.lyrebirdhealth.com/app?encryptedPayload=');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $payload = json_decode($query['encryptedPayload'], true);

        $decrypted = json_decode(decryptSecureLaunchPayload($payload, $keypair['private']), true);

        expect($decrypted['patientData'])->toMatchArray([
            'PAT_FIRST_NAME' => 'John',
            'PAT_LAST_NAME' => 'Doe',
            'PAT_GENDER' => 'M',
            'PAT_ACCT' => 'PAT_12345',
            'USER' => 'DR_789',
            'PAT_DOB' => '19850615',
            'USER_NAME' => 'Dr Jane Wilson',
        ])
            ->and($decrypted['patientData'])->not->toHaveKey('PAT_MRN')
            ->and($decrypted['timestamp'])->toBeInt()
            ->and(abs($decrypted['timestamp'] - (int) round(microtime(true) * 1000)))->toBeLessThan(300000);
    });

    it('rejects a context missing a required field', function (): void {
        $keypair = lyrebirdTestKeypair();
        $builder = new SecureLaunchUrlBuilder(
            new EciesEncryptor($keypair['publicKeyBase64Jwk']),
            'https://app.lyrebirdhealth.com',
        );

        $builder->build(new PatientContext(
            firstName: '',
            lastName: 'Doe',
            gender: 'M',
            patientAccountId: 'PAT_1',
            userId: 'DR_1',
        ));
    })->throws(SecureLaunchException::class);
});
