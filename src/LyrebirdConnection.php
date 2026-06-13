<?php

declare(strict_types=1);

namespace Clinically\Lyrebird;

use Clinically\Lyrebird\Client\PartnerApiClient;
use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\SecureLaunch\SecureLaunchUrlBuilder;
use Illuminate\Support\Collection;

/**
 * A Lyrebird connection bound to a single set of credentials (one tenant). Built
 * by LyrebirdManager; not resolved from the container directly.
 */
final class LyrebirdConnection
{
    public function __construct(
        private readonly SecureLaunchUrlBuilder $launchBuilder,
        private readonly PartnerApiClient $partnerApi,
    ) {}

    /**
     * Build a Secure Launch V2 URL that opens Lyrebird with the given patient
     * in context.
     */
    public function secureLaunchUrl(PatientContext $context): string
    {
        return $this->launchBuilder->build($context);
    }

    /**
     * Push appointments into Lyrebird for the given Lyrebird practitioner id.
     *
     * @param  array<int, AppointmentData>  $appointments
     */
    public function pushAppointments(string $userId, array $appointments): void
    {
        $this->partnerApi->pushAppointments($userId, $appointments);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function practitioners(): Collection
    {
        return $this->partnerApi->practitioners();
    }
}
