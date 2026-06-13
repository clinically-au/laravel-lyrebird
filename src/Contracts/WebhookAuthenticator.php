<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Contracts;

use Illuminate\Http\Request;

/**
 * Authenticates an inbound Lyrebird webhook. The default implementation
 * compares the bearer token against the configured Partner API key.
 *
 * Multitenant apps bind their own implementation: resolve the tenant from the
 * bearer token (each tenant has a distinct Partner API key) and set the tenant
 * context so the ConsultationNoteReceived listener runs scoped to that tenant.
 */
interface WebhookAuthenticator
{
    public function authenticate(Request $request): bool;
}
