<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Http\Middleware;

use Clinically\Lyrebird\Contracts\WebhookAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound Lyrebird webhooks via the bound WebhookAuthenticator.
 */
final class VerifyLyrebirdWebhook
{
    public function __construct(private readonly WebhookAuthenticator $authenticator) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authenticator->authenticate($request)) {
            abort(401, 'Invalid Lyrebird webhook credentials.');
        }

        return $next($request);
    }
}
