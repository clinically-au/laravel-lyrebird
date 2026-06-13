<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Exceptions;

use RuntimeException;

final class PartnerApiException extends RuntimeException
{
    public function __construct(
        public readonly string $endpoint,
        public readonly int $status,
        public readonly string $body,
    ) {
        parent::__construct("Lyrebird Partner API request to [{$endpoint}] failed with status {$status}: {$body}");
    }
}
