<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Data;

/**
 * A single appointment pushed to Lyrebird via the Partner API. Identifiers are
 * the EMR's own external IDs; Lyrebird echoes them back on the note webhook so
 * the consuming app can map a returned note to its records.
 */
final readonly class AppointmentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $appointmentId,
        public string $patientId,
        public string $practitionerId,
        public string $appointmentDateTime,
        public string $patientName,
        public ?string $patientGender = null,
        public ?string $patientPhone = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'appointmentId' => $this->appointmentId,
            'patientId' => $this->patientId,
            'practitionerId' => $this->practitionerId,
            'appointmentDateTime' => $this->appointmentDateTime,
            'patientName' => $this->patientName,
            'patientGender' => $this->patientGender,
            'patientPhone' => $this->patientPhone,
            'metadata' => $this->metadata === [] ? null : $this->metadata,
        ], fn (mixed $value): bool => $value !== null);
    }
}
