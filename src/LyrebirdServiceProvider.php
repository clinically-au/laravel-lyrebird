<?php

declare(strict_types=1);

namespace Clinically\Lyrebird;

use Clinically\Lyrebird\Contracts\WebhookAuthenticator;
use Clinically\Lyrebird\Http\ConfigWebhookAuthenticator;
use Clinically\Lyrebird\Support\LyrebirdConfig;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class LyrebirdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lyrebird.php', 'lyrebird');

        $this->app->singleton(LyrebirdConfig::class, fn (Application $app): LyrebirdConfig => new LyrebirdConfig(
            baseUrl: (string) $app['config']->get('lyrebird.base_url'),
            partnerApiBaseUrl: (string) $app['config']->get('lyrebird.partner_api.base_url'),
            timeout: (int) $app['config']->get('lyrebird.partner_api.timeout', 30),
            launchPath: (string) $app['config']->get('lyrebird.secure_launch.launch_path', '/app'),
            partnerApiKey: $app['config']->get('lyrebird.partner_api.api_key'),
            secureLaunchPublicKey: $app['config']->get('lyrebird.secure_launch.public_key'),
        ));

        $this->app->singleton(LyrebirdManager::class, fn (Application $app): LyrebirdManager => new LyrebirdManager(
            $app->make(HttpFactory::class),
            $app->make(LyrebirdConfig::class),
        ));

        $this->app->bind(WebhookAuthenticator::class, ConfigWebhookAuthenticator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lyrebird.php' => config_path('lyrebird.php'),
        ], 'lyrebird-config');

        if ((bool) $this->app['config']->get('lyrebird.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/lyrebird.php');
        }
    }
}
