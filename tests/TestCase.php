<?php

namespace Spaseossr\UnifiedApi\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Inertia\ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('view.paths', [__DIR__.'/Stubs/views']);
        $app['config']->set('inertia.ssr.enabled', false);
        $app['config']->set('unified-api', require __DIR__.'/../config/unified-api.php');
    }
}
