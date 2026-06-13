<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\SecureLaunch;

use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\Exceptions\SecureLaunchException;

/**
 * Builds a Lyrebird Secure Launch V2 URL: encrypts the patient context with a
 * fresh timestamp and returns the launch URL the EMR opens to hand Lyrebird the
 * patient in context.
 */
final class SecureLaunchUrlBuilder
{
    private const REQUIRED_FIELDS = ['PAT_FIRST_NAME', 'PAT_LAST_NAME', 'PAT_GENDER', 'PAT_ACCT', 'USER'];

    public function __construct(
        private readonly EciesEncryptor $encryptor,
        private readonly string $baseUrl,
        private readonly string $launchPath = '/app',
    ) {}

    public function build(PatientContext $context): string
    {
        $fields = $context->toFormFields();

        foreach (self::REQUIRED_FIELDS as $required) {
            if (! isset($fields[$required])) {
                throw new SecureLaunchException("Missing required patient context field [{$required}].");
            }
        }

        $plaintext = json_encode([
            'timestamp' => $this->currentTimestampMs(),
            'patientData' => $fields,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($plaintext === false) {
            throw new SecureLaunchException('Failed to encode patient context.');
        }

        $payload = $this->encryptor->encrypt($plaintext);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new SecureLaunchException('Failed to encode encrypted payload.');
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($this->launchPath, '/').'?encryptedPayload='.rawurlencode($json);
    }

    protected function currentTimestampMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
