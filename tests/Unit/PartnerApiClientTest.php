<?php

declare(strict_types=1);

use Clinically\Lyrebird\Client\PartnerApiClient;
use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Exceptions\PartnerApiException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

function makePartnerApiClient(Factory $http): PartnerApiClient
{
    return new PartnerApiClient($http, 'https://app.lyrebirdhealth.com/partnerapi/v1', 'test-key', 30);
}

describe('PartnerApiClient::pushAppointments', function (): void {
    it('posts appointments with the bearer token and expected body', function (): void {
        $http = new Factory;
        $http->fake(['*' => $http->response(['ok' => true])]);

        makePartnerApiClient($http)->pushAppointments('user-uuid', [
            new AppointmentData(
                appointmentId: 'ext-appt-123',
                patientId: 'ext-patient-456',
                practitionerId: 'ext-prac-789',
                appointmentDateTime: '2025-11-06T14:30:00Z',
                patientName: 'John Doe',
                patientGender: 'MALE',
                metadata: ['appointmentType' => 'consultation'],
            ),
        ]);

        $http->assertSent(function (Request $request): bool {
            return $request->url() === 'https://app.lyrebirdhealth.com/partnerapi/v1/appointments'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['userId'] === 'user-uuid'
                && $request['appointments'][0]['appointmentId'] === 'ext-appt-123'
                && $request['appointments'][0]['patientGender'] === 'MALE'
                && $request['appointments'][0]['metadata']['appointmentType'] === 'consultation';
        });
    });

    it('throws a PartnerApiException on a non-2xx response', function (): void {
        $http = new Factory;
        $http->fake(['*' => $http->response('nope', 500)]);

        makePartnerApiClient($http)->pushAppointments('user-uuid', []);
    })->throws(PartnerApiException::class);
});

describe('PartnerApiClient::practitioners', function (): void {
    it('returns the practitioner roster', function (): void {
        $http = new Factory;
        $http->fake(['*' => $http->response([
            ['id' => 'p1', 'email' => 'a@b.com', 'firstName' => 'A', 'lastName' => 'B'],
        ])]);

        $practitioners = makePartnerApiClient($http)->practitioners();

        expect($practitioners)->toHaveCount(1)
            ->and($practitioners->first()['id'])->toBe('p1');
    });
});
