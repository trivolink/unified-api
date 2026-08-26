<?php

namespace Spaseossr\UnifiedApi\Tests;

class ScaffoldTest extends TestCase
{
    public function test_inertia_service_provider_is_loaded(): void
    {
        $this->assertTrue(app()->providerIsLoaded(\Inertia\ServiceProvider::class));
    }

    public function test_root_view_stub_exists(): void
    {
        $this->assertFileExists(__DIR__.'/Stubs/views/app.blade.php');
    }
}
