<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Support\Facades\Route;
use Spaseossr\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients;

class CsrfMiddlewareTest extends TestCase
{
    protected function defineRoutes($router)
    {
        Route::middleware(['web', ValidateCsrfTokenExceptApiClients::class])->group(function () {
            Route::post('/secure', fn () => ['ok' => true]);
        });
    }

    public function test_browser_post_without_csrf_token_is_rejected(): void
    {
        $this->post('/secure', [], ['Accept' => 'text/html'])
            ->assertStatus(419);
    }

    public function test_unified_client_post_bypasses_csrf(): void
    {
        $this->post('/secure', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
