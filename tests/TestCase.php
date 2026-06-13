<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Tests;

use Clinically\Lyrebird\LyrebirdServiceProvider;
use Illuminate\Foundation\Application;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LyrebirdServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('lyrebird.base_url', 'https://app.lyrebirdhealth.com');
        $app['config']->set('lyrebird.partner_api.base_url', 'https://app.lyrebirdhealth.com/partnerapi/v1');
        $app['config']->set('lyrebird.partner_api.api_key', 'test-partner-key');
        $app['config']->set('lyrebird.webhook.enabled', true);
        $app['config']->set('lyrebird.webhook.path', 'webhooks/lyrebird');
        $app['config']->set('lyrebird.webhook.middleware', []);
    }
}
