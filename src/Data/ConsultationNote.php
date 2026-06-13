<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Data;

/**
 * A consultation note written back from Lyrebird via the webhook. The patient,
 * practitioner and appointment identifiers echo the external IDs the EMR
 * originally pushed, so they can be mapped back to local records.
 */
final readonly class ConsultationNote
{
    public function __construct(
        public string $content,
        public ?string $patientId = null,
        public ?string $practitionerId = null,
        public ?string $appointmentId = null,
        public ?string $timestamp = null,
        public ?string $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            content: isset($payload['content']) ? (string) $payload['content'] : '',
            patientId: isset($payload['patient_id']) ? (string) $payload['patient_id'] : null,
            practitionerId: isset($payload['practitioner_id']) ? (string) $payload['practitioner_id'] : null,
            appointmentId: isset($payload['appointment_id']) ? (string) $payload['appointment_id'] : null,
            timestamp: isset($payload['timestamp']) ? (string) $payload['timestamp'] : null,
            userId: isset($payload['user_id']) ? (string) $payload['user_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'patient_id' => $this->patientId,
            'practitioner_id' => $this->practitionerId,
            'appointment_id' => $this->appointmentId,
            'timestamp' => $this->timestamp,
            'user_id' => $this->userId,
        ];
    }
}
