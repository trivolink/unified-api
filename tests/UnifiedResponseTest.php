<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spaseossr\UnifiedApi\UnifiedResponse;

class UnifiedResponseTest extends TestCase
{
    protected function apiRequest(): Request
    {
        return Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }

    public function test_json_client_receives_envelope(): void
    {
        $response = new UnifiedResponse('Dashboard', [], ['users' => 5]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame(['data', 'meta', 'message', 'version'], array_keys($json));
        $this->assertSame(['users' => 5], $json['data']);
        $this->assertSame('v1', $json['version']);
    }

    public function test_meta_contains_component_and_url(): void
    {
        $response = new UnifiedResponse('Dashboard', [], ['users' => 5]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame('Dashboard', $json['meta']['component']);
        $this->assertSame('/dashboard', $json['meta']['url']);
    }

    public function test_meta_respects_config_toggles(): void
    {
        config(['unified-api.meta' => ['component' => false, 'url' => false]]);

        $response = new UnifiedResponse('Dashboard', [], ['users' => 5]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame([], $json['meta']);
    }

    public function test_closure_props_are_resolved(): void
    {
        $response = new UnifiedResponse('Dashboard', [], [
            'stats' => fn () => ['count' => 3],
        ]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame(['count' => 3], $json['data']['stats']);
    }

    public function test_shared_props_are_included_in_data(): void
    {
        Inertia::share('auth', ['user' => 'Tania']);

        $response = new UnifiedResponse('Dashboard', Inertia::getShared(), ['users' => 5]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame(['user' => 'Tania'], $json['data']['auth']);
        $this->assertSame(5, $json['data']['users']);
    }

    public function test_deferred_props_resolve_eagerly_for_api_clients(): void
    {
        $response = new UnifiedResponse('Dashboard', [], [
            'lazy' => Inertia::defer(fn () => 'resolved-lazily'),
            'regular' => 'plain',
        ]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame('resolved-lazily', $json['data']['lazy']);
        $this->assertSame('plain', $json['data']['regular']);
    }

    public function test_optional_props_resolve_eagerly_for_api_clients(): void
    {
        $response = new UnifiedResponse('Dashboard', [], [
            'partial' => Inertia::optional(fn () => 'optional-value'),
        ]);
        $json = $response->toResponse($this->apiRequest())->getData(true);

        $this->assertSame('optional-value', $json['data']['partial']);
    }

    public function test_html_client_falls_back_to_parent_html_response(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $response = (new UnifiedResponse('Dashboard', [], ['users' => 5]))
            ->toResponse($request)
            ->prepare($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Dashboard', $response->getContent());
    }

    public function test_inertia_client_falls_back_to_parent_page_object(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INERTIA' => 'true',
        ]);

        $response = (new UnifiedResponse('Dashboard', [], ['users' => 5]))
            ->toResponse($request);
        $json = $response->getData(true);

        $this->assertSame('Dashboard', $json['component']);
        $this->assertSame(5, $json['props']['users']);
        $this->assertSame('true', $response->headers->get('X-Inertia'));
    }

    protected function tearDown(): void
    {
        Inertia::flushShared();
        parent::tearDown();
    }
}
