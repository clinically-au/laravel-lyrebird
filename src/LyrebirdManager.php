<?php

declare(strict_types=1);

namespace Clinically\Lyrebird;

use Clinically\Lyrebird\Client\PartnerApiClient;
use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Data\LyrebirdCredentials;
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\SecureLaunch\EciesEncryptor;
use Clinically\Lyrebird\SecureLaunch\SecureLaunchUrlBuilder;
use Clinically\Lyrebird\Support\LyrebirdConfig;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;

/**
 * Entry point behind the Lyrebird facade. Acts as a factory: build a
 * LyrebirdConnection for a specific tenant with Lyrebird::for($credentials), or
 * call the outbound methods directly to use the configured default connection.
 */
final class LyrebirdManager
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LyrebirdConfig $defaults,
    ) {}

    /**
     * Build a connection for the given credentials. Null fields fall back to the
     * configured defaults; pass null for credentials to use the default
     * connection entirely (single-tenant / config-driven).
     */
    public function connection(?LyrebirdCredentials $credentials = null): LyrebirdConnection
    {
        $credentials ??= new LyrebirdCredentials;

        $baseUrl = $credentials->baseUrl ?? $this->defaults->baseUrl;
        $partnerApiBaseUrl = $credentials->partnerApiBaseUrl ?? $this->defaults->partnerApiBaseUrl;
        $timeout = $credentials->timeout ?? $this->defaults->timeout;
        $launchPath = $credentials->launchPath ?? $this->defaults->launchPath;
        $partnerApiKey = $credentials->partnerApiKey ?? $this->defaults->partnerApiKey;
        $publicKey = $credentials->secureLaunchPublicKey ?? $this->defaults->secureLaunchPublicKey;

        return new LyrebirdConnection(
            new SecureLaunchUrlBuilder(new EciesEncryptor($publicKey), $baseUrl, $launchPath),
            new PartnerApiClient($this->http, $partnerApiBaseUrl, $partnerApiKey, $timeout),
        );
    }

    /**
     * Build a connection for a tenant's credentials (alias of connection()).
     */
    public function for(LyrebirdCredentials $credentials): LyrebirdConnection
    {
        return $this->connection($credentials);
    }

    public function secureLaunchUrl(PatientContext $context): string
    {
        return $this->connection()->secureLaunchUrl($context);
    }

    /**
     * @param  array<int, AppointmentData>  $appointments
     */
    public function pushAppointments(string $userId, array $appointments): void
    {
        $this->connection()->pushAppointments($userId, $appointments);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function practitioners(): Collection
    {
        return $this->connection()->practitioners();
    }
}
