<?php

declare(strict_types=1);

return [

    /*
     * Base URL of the Lyrebird web application. Secure Launch URLs are built
     * against this host (e.g. https://app.lyrebirdhealth.com/app?encryptedPayload=...).
     */
    'base_url' => env('LYREBIRD_URL', 'https://app.lyrebirdhealth.com'),

    /*
     * Partner API — outbound REST used to push appointments into Lyrebird and
     * read the practitioner roster. The api_key is also the shared secret used
     * to authenticate inbound write-back webhooks.
     */
    'partner_api' => [
        'base_url' => env('LYREBIRD_PARTNER_API_URL', 'https://app.lyrebirdhealth.com/partnerapi/v1'),
        'api_key' => env('LYREBIRD_PARTNER_API_KEY'),
        'timeout' => (int) env('LYREBIRD_TIMEOUT', 30),
    ],

    /*
     * Secure Launch V2 (ECIES: ephemeral ECDH P-256 + AES-256-GCM). The public
     * key is a base64-encoded EC P-256 JWK issued by your Lyrebird organisation
     * under Organisation > API. It is safe to keep server-side in config/env;
     * the matching private key never leaves Lyrebird.
     */
    'secure_launch' => [
        'public_key' => env('LYREBIRD_PUBLIC_KEY'),
        'launch_path' => env('LYREBIRD_LAUNCH_PATH', '/app'),
        'timestamp_window' => (int) env('LYREBIRD_LAUNCH_TIMESTAMP_WINDOW', 300),
    ],

    /*
     * Inbound webhook that receives consultation notes from Lyrebird. When
     * enabled the package registers a POST route at the configured path,
     * verifies the bearer token against partner_api.api_key, and dispatches a
     * Clinically\Lyrebird\Events\ConsultationNoteReceived event. Storage is the
     * consuming application's responsibility.
     */
    'webhook' => [
        'enabled' => (bool) env('LYREBIRD_WEBHOOK_ENABLED', true),
        'path' => env('LYREBIRD_WEBHOOK_PATH', 'webhooks/lyrebird'),
        'middleware' => ['api'],
    ],

];
