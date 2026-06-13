<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Data;

/**
 * Per-tenant Lyrebird credentials, supplied at call time via Lyrebird::for().
 * Any field left null falls back to the package's configured default, so a
 * multitenant app typically only sets partnerApiKey + secureLaunchPublicKey
 * (e.g. resolved from the current tenant) and never relies on env.
 */
final readonly class LyrebirdCredentials
{
    public function __construct(
        public ?string $partnerApiKey = null,
        public ?string $secureLaunchPublicKey = null,
        public ?string $baseUrl = null,
        public ?string $partnerApiBaseUrl = null,
        public ?int $timeout = null,
        public ?string $launchPath = null,
    ) {}
}
