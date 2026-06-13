<?php

declare(strict_types=1);

use Clinically\Lyrebird\Data\ConsultationNote;
use Clinically\Lyrebird\Events\ConsultationNoteReceived;
use Illuminate\Support\Facades\Event;

$samplePayload = [
    'content' => 'Patient presented with...',
    'patient_id' => 'pat_12345',
    'practitioner_id' => 'prac_67890',
    'appointment_id' => 'appt_abcde',
    'timestamp' => '2025-11-19T01:55:00.000Z',
    'user_id' => 'user_uuid',
];

it('accepts a valid webhook and dispatches ConsultationNoteReceived', function () use ($samplePayload): void {
    Event::fake([ConsultationNoteReceived::class]);

    $this->withHeaders(['Authorization' => 'Bearer test-partner-key'])
        ->postJson('webhooks/lyrebird', $samplePayload)
        ->assertOk()
        ->assertJson(['received' => true]);

    Event::assertDispatched(ConsultationNoteReceived::class, function (ConsultationNoteReceived $event): bool {
        return $event->note instanceof ConsultationNote
            && $event->note->content === 'Patient presented with...'
            && $event->note->patientId === 'pat_12345'
            && $event->note->appointmentId === 'appt_abcde'
            && $event->note->userId === 'user_uuid';
    });
});

it('rejects a webhook with a missing or wrong bearer token', function () use ($samplePayload): void {
    Event::fake([ConsultationNoteReceived::class]);

    $this->withHeaders(['Authorization' => 'Bearer wrong-key'])
        ->postJson('webhooks/lyrebird', $samplePayload)
        ->assertUnauthorized();

    $this->postJson('webhooks/lyrebird', $samplePayload)
        ->assertUnauthorized();

    Event::assertNotDispatched(ConsultationNoteReceived::class);
});
