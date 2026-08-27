<?php

namespace TrivoLink\UnifiedApi\Tests;

use Illuminate\Support\Facades\Route;

class TokenEndpointDisabledTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        // Runs before providers boot, so no token route is loaded.
        $app['config']->set('unified-api.token_endpoint.enabled', false);
    }

    public function test_token_route_is_not_registered_when_disabled(): void
    {
        $this->assertFalse(Route::has('unified-api.token'));
    }
}
