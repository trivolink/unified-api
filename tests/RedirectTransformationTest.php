<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RedirectTransformationTest extends TestCase
{
    protected function defineRoutes($router)
    {
        Route::middleware('web')->group(function () {
            Route::post('/profile', function () {
                Inertia::flash('message', 'Profile updated.');

                return redirect()->to('/profile');
            })->name('profile.update');

            Route::post('/validate', function () {
                throw ValidationException::withMessages([
                    'email' => ['The email field is required.'],
                ]);
            });

            Route::get('/boom', function () {
                abort(500);
            });
        });
    }

    public function test_web_post_keeps_redirect_response(): void
    {
        $this->post('/profile', [], ['Accept' => 'text/html'])
            ->assertRedirect('/profile');
    }

    public function test_api_post_receives_redirect_envelope(): void
    {
        $response = $this->post('/profile', [], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('meta.redirect', '/profile')
            ->assertJsonPath('version', 'v1');
    }

    public function test_api_validation_error_is_wrapped_with_status(): void
    {
        $response = $this->postJson('/validate', [], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('message', 'The email field is required.');
        $this->assertArrayHasKey('errors', $response->getData(true));
    }

    public function test_api_server_error_is_wrapped(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/boom', ['Accept' => 'application/json']);

        $response->assertStatus(500);
        $this->assertSame('v1', $response->getData(true)['version']);
    }

    public function test_web_validation_error_stays_native(): void
    {
        $response = $this->post('/validate', [], ['Accept' => 'text/html']);

        // Web (non-Inertia) POST redirects back on validation failure —
        // the key assertion is that no envelope wrapping occurred.
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('X-Inertia'));
    }

    public function test_error_wrapping_can_be_disabled(): void
    {
        config(['unified-api.wrap_errors' => false]);

        $response = $this->postJson('/validate', [], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertArrayNotHasKey('version', $response->getData(true));
    }
}
