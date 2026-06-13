## clinically/laravel-lyrebird

Two-way integration with the Lyrebird Health AI medical scribe, where Clinically acts as the EMR. Implements two Lyrebird "Open API" transports: **Secure Launch V2** (launch Lyrebird with patient context) and the **Partner API** (push appointments out, receive consultation notes back via webhook).

### Features

- Secure Launch V2: build an encrypted launch URL (ECIES — ephemeral ECDH P-256 + AES-256-GCM) that opens Lyrebird with the patient in context. Example:

@verbatim
<code-snippet name="Build a Secure Launch URL" lang="php">
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\Facades\Lyrebird;

$url = Lyrebird::secureLaunchUrl(new PatientContext(
    firstName: 'John', lastName: 'Doe', gender: 'M',
    patientAccountId: $patient->id, userId: $clinician->lyrebird_user_id,
    dateOfBirth: '19850615', // YYYYMMDD
));
</code-snippet>
@endverbatim

- Partner API: push upcoming appointments into Lyrebird. Example:

@verbatim
<code-snippet name="Push appointments" lang="php">
use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Facades\Lyrebird;

Lyrebird::pushAppointments($lyrebirdUserId, [
    new AppointmentData(
        appointmentId: $appt->id, patientId: $patient->id,
        practitionerId: $clinician->id, appointmentDateTime: $appt->starts_at->toIso8601String(),
        patientName: $patient->full_name, patientGender: 'MALE',
    ),
]);
</code-snippet>
@endverbatim

- Note write-back: a webhook receives finished notes and fires an event. The package stores nothing — listen and persist in the app. Example:

@verbatim
<code-snippet name="Handle a returned note" lang="php">
use Clinically\Lyrebird\Events\ConsultationNoteReceived;

class AttachLyrebirdNote
{
    public function handle(ConsultationNoteReceived $event): void
    {
        // $event->note->appointmentId echoes the external ID you pushed
        Encounter::where('external_id', $event->note->appointmentId)
            ->first()?->notes()->create(['body' => $event->note->content]);
    }
}
</code-snippet>
@endverbatim

### Conventions

- Identifiers in `AppointmentData` and `PatientContext` are the EMR's own external IDs; Lyrebird echoes `patient_id`/`appointment_id` back on the note webhook so you can map a note to local records.
- The Partner API key (`LYREBIRD_PARTNER_API_KEY`) is also the shared secret Lyrebird sends as the webhook bearer token; the package verifies it in constant time.
- The Secure Launch public key (`LYREBIRD_PUBLIC_KEY`) is a base64-encoded EC P-256 JWK from your Lyrebird org; it is safe to keep server-side. The private key never leaves Lyrebird.
- FHIR, HL7 and SMART on FHIR transports are not implemented (alternative transports for the same two flows).
