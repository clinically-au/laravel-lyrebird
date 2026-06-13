<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Support;

/**
 * The package's default connection settings, resolved once from config. Acts as
 * the fallback behind any per-tenant LyrebirdCredentials.
 */
final readonly class LyrebirdConfig
{
    public function __construct(
        public string $baseUrl,
        public string $partnerApiBaseUrl,
        public int $timeout,
        public string $launchPath,
        public ?string $partnerApiKey,
        public ?string $secureLaunchPublicKey,
    ) {}
}
