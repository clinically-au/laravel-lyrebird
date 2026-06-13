# clinically/laravel-lyrebird — working notes for AI assistants

Two-way Lyrebird Health AI scribe integration. Clinically is the EMR. Two transports only: **Secure Launch V2** and the **Partner API** (push appointments + webhook note write-back). FHIR / HL7 / SMART on FHIR are deliberately out of scope.

## Conventions

- `declare(strict_types=1)` in every file; `final` classes; `final readonly` DTOs with constructor promotion + `toArray()`/`fromArray()`. No `spatie/laravel-data`.
- Config-driven; no `env()` outside `config/lyrebird.php`. No models, no migrations — notes reach the app via the `ConsultationNoteReceived` event.
- PHPStan level 6, Pint (laravel preset + `declare_strict_types`), Pest 3.

## Secure Launch V2 crypto (do not "improve" without re-reading)

ECIES matching Lyrebird's reference (`felixmccuaig/secure-launch`, `nodejs/secure-launch.mjs`):

- Ephemeral ECDH on **P-256 (prime256v1)**. The raw 32-byte shared secret (X-coordinate from `openssl_pkey_derive`) is used **directly as the AES-256 key — NO HKDF**. This matches WebCrypto `deriveKey(ECDH → AES-GCM)`. Adding a KDF breaks decryption on Lyrebird's side.
- AES-256-GCM, 12-byte IV, 16-byte tag. `openssl_encrypt(..., OPENSSL_RAW_DATA, $iv, $tag, '', 16)` returns ciphertext; the tag is separate.
- Plaintext = `json_encode(['timestamp' => <ms>, 'patientData' => [...]])`. Lyrebird rejects timestamps outside a 5-minute window — build URLs at click time.
- Payload JSON: `{version:"1.1", ephemeralPublicKey: base64(JSON JWK {kty,crv,x,y}), ciphertext, iv, authTag}` (last three standard base64). Launch URL: `{base_url}/app?encryptedPayload=` + `rawurlencode(json)`.
- PHP can't import a JWK directly: build the public key by wrapping `0x04 || X || Y` in the fixed P-256 SPKI DER prefix (`EciesEncryptor::P256_SPKI_PREFIX_HEX`) → PEM → `openssl_pkey_get_public()`. The same prefix appears in `tests/Pest.php` for the decrypt side.

## Multitenancy (important)

Credentials are **per-tenant, supplied at call time** — never assume the config singleton. `LyrebirdManager` is a factory: `Lyrebird::for(LyrebirdCredentials)` → `LyrebirdConnection` bound to that tenant's `partnerApiKey`/`secureLaunchPublicKey` (null fields fall back to `Support\LyrebirdConfig` defaults). The bare facade methods use the default connection (single-tenant). Per-call objects are cheap to rebuild — don't cache a connection across tenants.

Webhook auth is a bound contract: `Contracts\WebhookAuthenticator` (default `Http\ConfigWebhookAuthenticator` checks the config key). Multitenant apps bind their own to resolve the tenant from the bearer token and set tenant context before `ConsultationNoteReceived` fires.

## Partner API

- Base `…/partnerapi/v1`; Bearer auth. `POST /appointments` ({userId, appointments[]}), `GET /practitioners`.
- The Partner API key is **also** the webhook bearer secret (`VerifyLyrebirdWebhook` constant-time compares it).
- Webhook body is snake_case (`patient_id`, `appointment_id`, …) and echoes the external IDs we pushed → map back to local records in the app's listener.

## Tests

- `tests/Unit` is pure (no container): crypto round-trip + `Http::fake` Partner API.
- `tests/Feature` uses Testbench (`TestCase`): webhook auth + event dispatch. Only Feature gets the base `TestCase` (`uses(...)->in('Feature')`).
