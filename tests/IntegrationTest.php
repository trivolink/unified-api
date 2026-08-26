<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Support\Facades\Route;
use Spaseossr\UnifiedApi\UnifiedResponse;
use Spaseossr\UnifiedApi\UnifiedResponseFactory;

class IntegrationTest extends TestCase
{
    public function test_container_binding_is_overridden(): void
    {
        $this->assertInstanceOf(
            UnifiedResponseFactory::class,
            app(\Inertia\ResponseFactory::class)
        );
    }

    public function test_inertia_render_helper_returns_unified_response(): void
    {
        $this->assertInstanceOf(UnifiedResponse::class, inertia('Dashboard', ['x' => 1]));
    }

    public function test_full_stack_route_inertia_serves_envelope_to_json_client(): void
    {
        Route::inertia('/team', 'Team/Show', ['team' => 'Acme']);

        $this->get('/team', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.team', 'Acme')
            ->assertJsonPath('meta.component', 'Team/Show')
            ->assertJsonPath('version', 'v1');
    }

    public function test_full_stack_route_inertia_serves_html_to_browser(): void
    {
        Route::inertia('/team', 'Team/Show', ['team' => 'Acme']);

        $response = $this->get('/team', ['Accept' => 'text/html']);

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    public function test_config_is_merged_from_package(): void
    {
        $this->assertSame('v1', config('unified-api.version'));
        $this->assertTrue(config('unified-api.wrap_errors'));
        $this->assertSame(200, (int) config('unified-api.redirect_status'));
    }

    public function test_csrf_middleware_alias_is_registered(): void
    {
        $middleware = app('router')->getMiddleware();

        $this->assertContains(
            \Spaseossr\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients::class,
            $middleware
        );
        $this->assertSame(
            \Spaseossr\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients::class,
            $middleware['unified.csrf'] ?? null
        );
    }
}
