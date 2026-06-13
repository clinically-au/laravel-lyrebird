<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Http;

use Clinically\Lyrebird\Contracts\WebhookAuthenticator;
use Illuminate\Http\Request;

/**
 * Default single-tenant webhook authenticator: constant-time compare of the
 * inbound bearer token against the configured Partner API key.
 */
final class ConfigWebhookAuthenticator implements WebhookAuthenticator
{
    public function authenticate(Request $request): bool
    {
        $expected = (string) config('lyrebird.partner_api.api_key');
        $provided = (string) $request->bearerToken();

        return $expected !== '' && hash_equals($expected, $provided);
    }
}
