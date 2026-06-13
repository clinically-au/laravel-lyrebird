<?php

declare(strict_types=1);

use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Data\LyrebirdCredentials;
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\LyrebirdManager;
use Clinically\Lyrebird\Support\LyrebirdConfig;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

/**
 * Build a manager whose defaults carry NO credentials, proving a tenant's keys
 * come entirely from Lyrebird::for() and never from config/env.
 */
function managerWithoutDefaultCredentials(Factory $http): LyrebirdManager
{
    return new LyrebirdManager($http, new LyrebirdConfig(
        baseUrl: 'https://app.lyrebirdhealth.com',
        partnerApiBaseUrl: 'https://app.lyrebirdhealth.com/partnerapi/v1',
        timeout: 30,
        launchPath: '/app',
        partnerApiKey: null,
        secureLaunchPublicKey: null,
    ));
}

it('uses per-tenant credentials for Secure Launch, not config', function (): void {
    $http = new Factory;
    $keypair = lyrebirdTestKeypair();

    $connection = managerWithoutDefaultCredentials($http)->for(new LyrebirdCredentials(
        secureLaunchPublicKey: $keypair['publicKeyBase64Jwk'],
    ));

    $url = $connection->secureLaunchUrl(new PatientContext(
        firstName: 'John', lastName: 'Doe', gender: 'M',
        patientAccountId: 'PAT_1', userId: 'DR_1',
    ));

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $payload = json_decode($query['encryptedPayload'], true);
    $decrypted = json_decode(decryptSecureLaunchPayload($payload, $keypair['private']), true);

    expect($decrypted['patientData']['PAT_FIRST_NAME'])->toBe('John');
});

it('uses per-tenant credentials for the Partner API bearer token', function (): void {
    $http = new Factory;
    $http->fake(['*' => $http->response(['ok' => true])]);

    managerWithoutDefaultCredentials($http)
        ->for(new LyrebirdCredentials(partnerApiKey: 'tenant-abc-key'))
        ->pushAppointments('tenant-user', [
            new AppointmentData(
                appointmentId: 'a1', patientId: 'p1', practitionerId: 'pr1',
                appointmentDateTime: '2025-11-06T14:30:00Z', patientName: 'John Doe',
            ),
        ]);

    $http->assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer tenant-abc-key')
        && $request['userId'] === 'tenant-user');
});

it('lets a tenant override base URLs', function (): void {
    $http = new Factory;
    $http->fake(['*' => $http->response([])]);

    managerWithoutDefaultCredentials($http)
        ->for(new LyrebirdCredentials(
            partnerApiKey: 'k',
            partnerApiBaseUrl: 'https://eu.lyrebirdhealth.com/partnerapi/v1',
        ))
        ->practitioners();

    $http->assertSent(fn (Request $request): bool => $request->url() === 'https://eu.lyrebirdhealth.com/partnerapi/v1/practitioners');
});
