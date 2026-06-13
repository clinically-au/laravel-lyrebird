<?php

declare(strict_types=1);

use Clinically\Lyrebird\Http\Controllers\LyrebirdWebhookController;
use Clinically\Lyrebird\Http\Middleware\VerifyLyrebirdWebhook;
use Illuminate\Support\Facades\Route;

/** @var array<int, string> $middleware */
$middleware = (array) config('lyrebird.webhook.middleware', []);
$middleware[] = VerifyLyrebirdWebhook::class;

Route::post((string) config('lyrebird.webhook.path'), LyrebirdWebhookController::class)
    ->middleware($middleware)
    ->name('lyrebird.webhook');
