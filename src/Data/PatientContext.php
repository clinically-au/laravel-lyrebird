<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Data;

/**
 * Patient + clinician context for a Secure Launch. Field names map to the
 * form keys Lyrebird expects inside the encrypted payload's `patientData`.
 */
final readonly class PatientContext
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $patientAccountId,
        public string $userId,
        public ?string $dateOfBirth = null,
        public ?string $medicalRecordNumber = null,
        public ?string $userName = null,
        public ?string $userEmail = null,
    ) {}

    /**
     * The Lyrebird form-field representation, with empty optional fields omitted.
     *
     * @return array<string, string>
     */
    public function toFormFields(): array
    {
        return array_filter([
            'PAT_FIRST_NAME' => $this->firstName,
            'PAT_LAST_NAME' => $this->lastName,
            'PAT_GENDER' => $this->gender,
            'PAT_ACCT' => $this->patientAccountId,
            'USER' => $this->userId,
            'PAT_DOB' => $this->dateOfBirth,
            'PAT_MRN' => $this->medicalRecordNumber,
            'USER_NAME' => $this->userName,
            'USER_EMAIL' => $this->userEmail,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
