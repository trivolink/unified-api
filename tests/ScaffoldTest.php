<?php

namespace TrivoLink\UnifiedApi\Tests;

use Inertia\ServiceProvider;
use TrivoLink\UnifiedApi\ServiceProvider as UnifiedApiServiceProvider;

class ScaffoldTest extends TestCase
{
    public function test_inertia_service_provider_is_loaded(): void
    {
        $this->assertTrue(app()->providerIsLoaded(ServiceProvider::class));
    }

    public function test_config_is_publishable_under_the_unified_api_config_tag(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            UnifiedApiServiceProvider::class,
            'unified-api-config',
        );

        $this->assertNotEmpty($paths);
        $this->assertContains(config_path('unified-api.php'), $paths);
    }

    public function test_root_view_stub_exists(): void
    {
        $this->assertFileExists(__DIR__.'/Stubs/views/app.blade.php');
    }
}
