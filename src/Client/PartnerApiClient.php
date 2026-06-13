<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Client;

use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Exceptions\PartnerApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

/**
 * Outbound client for the Lyrebird Partner API — pushes appointments into
 * Lyrebird and reads the practitioner roster. Authenticated with a Bearer
 * token (the organisation's Partner API key).
 */
final class PartnerApiClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 30,
    ) {}

    /**
     * @param  array<int, AppointmentData>  $appointments
     */
    public function pushAppointments(string $userId, array $appointments): void
    {
        $response = $this->request()->post('/appointments', [
            'userId' => $userId,
            'appointments' => array_values(array_map(
                fn (AppointmentData $appointment): array => $appointment->toArray(),
                $appointments,
            )),
        ]);

        if ($response->failed()) {
            throw new PartnerApiException('/appointments', $response->status(), $response->body());
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function practitioners(): Collection
    {
        $response = $this->request()->get('/practitioners');

        if ($response->failed()) {
            throw new PartnerApiException('/practitioners', $response->status(), $response->body());
        }

        /** @var array<int, array<string, mixed>> $practitioners */
        $practitioners = (array) $response->json();

        return collect($practitioners);
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }
}
