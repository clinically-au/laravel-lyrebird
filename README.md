# Lyrebird Health integration for Laravel

`clinically/laravel-lyrebird` is a two-way integration with the [Lyrebird Health](https://www.lyrebirdhealth.com) AI medical scribe, with **Clinically acting as the EMR**. It implements two of Lyrebird's "Open API" transports:

- **Secure Launch V2** — open Lyrebird from your UI with the patient already in context, using ECIES (ephemeral ECDH P-256 + AES-256-GCM).
- **Partner API** — push appointments into Lyrebird over REST, and receive finished consultation notes back via a webhook.

> FHIR, HL7 and SMART on FHIR are alternative Lyrebird transports for the same two flows and are intentionally **not** implemented here.

## Installation

```bash
composer require clinically/laravel-lyrebird
php artisan vendor:publish --tag=lyrebird-config
```

## Configuration

```dotenv
# Partner API (also the webhook shared secret)
LYREBIRD_PARTNER_API_KEY=your-partner-api-key

# Secure Launch V2 — base64-encoded EC P-256 JWK from your Lyrebird org (Organisation > API)
LYREBIRD_PUBLIC_KEY=eyJrdHkiOiJFQyIsImNydiI6IlAtMjU2Iiwie...

# Optional overrides
LYREBIRD_URL=https://app.lyrebirdhealth.com
LYREBIRD_PARTNER_API_URL=https://app.lyrebirdhealth.com/partnerapi/v1
LYREBIRD_WEBHOOK_PATH=webhooks/lyrebird
```

Register the webhook URL (`https://your-app/webhooks/lyrebird`) in Lyrebird's Partner API settings.

The config values are the **defaults** (single-tenant convenience). Multitenant apps supply credentials per call instead — see [Multitenancy](#multitenancy).

## Usage

### 1. Push appointments into Lyrebird

```php
use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Facades\Lyrebird;

Lyrebird::pushAppointments($lyrebirdUserId, [
    new AppointmentData(
        appointmentId: $appt->id,            // your external IDs — echoed back on the note webhook
        patientId: $patient->id,
        practitionerId: $clinician->id,
        appointmentDateTime: $appt->starts_at->toIso8601String(),
        patientName: $patient->full_name,
        patientGender: 'MALE',
        patientPhone: $patient->phone,
        metadata: ['appointmentType' => 'consultation'],
    ),
]);

// And read the roster to discover Lyrebird practitioner ids:
$practitioners = Lyrebird::practitioners();
```

### 2. Launch Lyrebird in patient context

```php
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\Facades\Lyrebird;

$launchUrl = Lyrebird::secureLaunchUrl(new PatientContext(
    firstName: $patient->first_name,
    lastName: $patient->last_name,
    gender: 'M',                        // M / F / U / O
    patientAccountId: $patient->id,
    userId: $clinician->lyrebird_user_id,
    dateOfBirth: '19850615',            // YYYYMMDD (optional)
    medicalRecordNumber: $patient->mrn, // optional
    userName: $clinician->name,         // optional
    userEmail: $clinician->email,       // optional
));
```

Render `$launchUrl` as a button/link. Lyrebird validates the embedded timestamp within a 5-minute window, so generate the URL at click time (e.g. via a controller action) rather than caching it.

### 3. Receive the consultation note

When the clinician finishes, Lyrebird POSTs the note to your webhook. The package verifies the bearer token and fires an event — **storage is up to you**:

```php
use Clinically\Lyrebird\Events\ConsultationNoteReceived;

class AttachLyrebirdNote
{
    public function handle(ConsultationNoteReceived $event): void
    {
        $note = $event->note; // content, patientId, practitionerId, appointmentId, timestamp, userId

        Encounter::where('external_id', $note->appointmentId)
            ->first()
            ?->clinicalNotes()
            ->create(['body' => $note->content]);
    }
}
```

## Multitenancy

Each tenant has its own Lyrebird Partner API key and Secure Launch public key. Supply them per call with `Lyrebird::for()` — nothing is read from env/config:

```php
use Clinically\Lyrebird\Data\LyrebirdCredentials;
use Clinically\Lyrebird\Facades\Lyrebird;

$connection = Lyrebird::for(new LyrebirdCredentials(
    partnerApiKey: $tenant->lyrebird_partner_api_key,
    secureLaunchPublicKey: $tenant->lyrebird_public_key,
    // baseUrl / partnerApiBaseUrl / timeout / launchPath are optional overrides
));

$url = $connection->secureLaunchUrl($patientContext);
$connection->pushAppointments($tenant->lyrebird_user_id, $appointments);
```

`Lyrebird::secureLaunchUrl(...)` etc. (without `for()`) use the configured default connection — fine for a single-tenant app.

### Inbound webhook per tenant

Lyrebird sends the tenant's Partner API key as the webhook bearer token, so the webhook must be authenticated against the *right* tenant. Bind your own `WebhookAuthenticator`, resolve the tenant from the token, and set tenant context before the event is dispatched:

```php
use Clinically\Lyrebird\Contracts\WebhookAuthenticator;
use Illuminate\Http\Request;

final class TenantWebhookAuthenticator implements WebhookAuthenticator
{
    public function authenticate(Request $request): bool
    {
        $token = (string) $request->bearerToken();
        $tenant = Tenant::query()->where('lyrebird_partner_api_key', hash('sha256', $token))->first();

        if ($tenant === null) {
            return false;
        }

        $tenant->makeCurrent(); // your tenancy package's "set context"

        return true;
    }
}

// In a service provider:
$this->app->bind(WebhookAuthenticator::class, TenantWebhookAuthenticator::class);
```

Your `ConsultationNoteReceived` listener then runs within the resolved tenant context. (Store/compare keys hashed, and prefer a constant-time compare or a unique indexed lookup.)

## Security notes

- The **public key** (`LYREBIRD_PUBLIC_KEY`) is safe to store server-side; Lyrebird's private key never leaves Lyrebird. Each launch uses a fresh ephemeral keypair (forward secrecy) and AES-GCM authenticated encryption.
- The webhook is authenticated by comparing the inbound `Authorization: Bearer` against `LYREBIRD_PARTNER_API_KEY` in constant time.

## Testing

```bash
composer install
composer test       # pest
composer analyse    # phpstan level 6
composer format     # pint
```

The Secure Launch crypto is covered by a round-trip test that encrypts with the package and decrypts with a locally-generated private key, proving interoperability with Lyrebird without calling their servers.

## License

MIT.
