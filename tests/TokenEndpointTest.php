<?php

namespace TrivoLink\UnifiedApi\Tests;

use TrivoLink\UnifiedApi\Tests\Stubs\App\User as StubUser;
use TrivoLink\UnifiedApi\Tests\Stubs\App\UserWithoutApiTokens;

class TokenEndpointTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', StubUser::class);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/Stubs/database/migrations');
    }

    protected function user(array $attributes = []): StubUser
    {
        return StubUser::create(array_merge([
            'name' => 'Tania',
            'email' => 'tania@example.com',
            'password' => 'password',
        ], $attributes));
    }

    public function test_issues_a_bearer_token_for_valid_credentials(): void
    {
        $this->user();

        $response = $this->postJson('/api/token', [
            'email' => 'tania@example.com',
            'password' => 'password',
            'device_name' => 'iphone-15',
        ]);

        $response->assertOk()->assertJsonPath('version', 'v1');

        $token = $response->getData(true)['data']['token'];
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'iphone-15',
            'tokenable_type' => StubUser::class,
            'tokenable_id' => 1,
        ]);
    }

    public function test_defaults_device_name_to_mobile(): void
    {
        $this->user();

        $this->postJson('/api/token', [
            'email' => 'tania@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'mobile']);
    }

    public function test_rejects_wrong_password_with_422_envelope(): void
    {
        $this->user();

        $response = $this->postJson('/api/token', [
            'email' => 'tania@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);

        $json = $response->getData(true);
        $this->assertNull($json['data']);
        $this->assertSame('v1', $json['version']);
        $this->assertArrayHasKey('email', $json['errors']);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/token', []);

        $response->assertStatus(422);

        $errors = $response->getData(true)['errors'];
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_returns_500_when_user_model_lacks_has_api_tokens(): void
    {
        config(['app.debug' => true]);
        config(['auth.providers.users.model' => UserWithoutApiTokens::class]);

        UserWithoutApiTokens::create([
            'name' => 'Tania',
            'email' => 'tania@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/token', [
            'email' => 'tania@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(500);

        $this->assertStringContainsString(
            'HasApiTokens',
            $response->getData(true)['message'],
        );
    }
}
