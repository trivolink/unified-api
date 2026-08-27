<?php

namespace TrivoLink\UnifiedApi\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use TrivoLink\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients;

class CsrfMiddlewareTest extends TestCase
{
    protected function defineRoutes($router)
    {
        Route::middleware(['web', ValidateCsrfTokenExceptApiClients::class])->group(function () {
            Route::post('/secure', fn () => ['ok' => true]);
        });
    }

    public function test_unified_client_post_bypasses_csrf(): void
    {
        $this->post('/secure', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_browser_requests_delegate_to_native_csrf_middleware(): void
    {
        $native = Mockery::mock(ValidateCsrfTokenExceptApiClients::csrfMiddleware());
        $native->shouldReceive('handle')->once()->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(ValidateCsrfTokenExceptApiClients::csrfMiddleware(), $native);

        $called = false;
        $response = (new ValidateCsrfTokenExceptApiClients)->handle(
            $this->browserRequest(),
            function () use (&$called) {
                $called = true;

                return response('ok');
            }
        );

        $this->assertTrue($called);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_unified_requests_do_not_invoke_native_csrf_middleware(): void
    {
        $native = Mockery::mock(ValidateCsrfTokenExceptApiClients::csrfMiddleware());
        $native->shouldNotReceive('handle');
        $this->app->instance(ValidateCsrfTokenExceptApiClients::csrfMiddleware(), $native);

        $response = (new ValidateCsrfTokenExceptApiClients)->handle(
            $this->apiRequest(),
            fn () => response('through')
        );

        $this->assertSame('through', $response->getContent());
    }

    protected function browserRequest(): Request
    {
        return Request::create('/secure', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);
    }

    protected function apiRequest(): Request
    {
        return Request::create('/secure', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
