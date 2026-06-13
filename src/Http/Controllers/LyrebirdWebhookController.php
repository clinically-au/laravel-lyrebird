<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Http\Controllers;

use Clinically\Lyrebird\Data\ConsultationNote;
use Clinically\Lyrebird\Events\ConsultationNoteReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Lyrebird consultation-note write-backs and dispatches a
 * ConsultationNoteReceived event. Persistence is left to the application.
 */
final class LyrebirdWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        ConsultationNoteReceived::dispatch(ConsultationNote::fromArray($payload));

        return new JsonResponse(['received' => true]);
    }
}
