<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Events;

use Clinically\Lyrebird\Data\ConsultationNote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when Lyrebird writes a consultation note back via the webhook.
 * The consuming application listens for this and persists/maps the note to its
 * own patient and encounter records; the package itself stores nothing.
 */
final class ConsultationNoteReceived
{
    use Dispatchable;

    public function __construct(public readonly ConsultationNote $note) {}
}
