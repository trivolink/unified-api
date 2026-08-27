<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Http\Request;
use Spaseossr\UnifiedApi\Envelope;

class EnvelopeTest extends TestCase
{
    public function test_success_envelope_shape_and_key_order(): void
    {
        $json = Envelope::success(['users' => 5], ['component' => 'Dashboard'], 'Hello')
            ->toResponse(Request::create('/x'))
            ->getData(true);

        $this->assertSame(
            ['data', 'meta', 'message', 'version'],
            array_keys($json)
        );
        $this->assertSame(['users' => 5], $json['data']);
        $this->assertSame(['component' => 'Dashboard'], $json['meta']);
        $this->assertSame('Hello', $json['message']);
    }

    public function test_version_comes_from_config(): void
    {
        config(['unified-api.version' => 'v2']);

        $json = Envelope::success(null)->toResponse(Request::create('/x'))->getData(true);

        $this->assertSame('v2', $json['version']);
    }

    public function test_version_defaults_to_v1(): void
    {
        $json = Envelope::success(null)->toResponse(Request::create('/x'))->getData(true);

        $this->assertSame('v1', $json['version']);
    }

    public function test_error_envelope_keeps_status_and_errors(): void
    {
        $response = Envelope::error('The given data was invalid.', ['email' => ['Taken']], 422)
            ->toResponse(Request::create('/x'));

        $this->assertSame(422, $response->getStatusCode());

        $json = $response->getData(true);
        $this->assertNull($json['data']);
        $this->assertSame('The given data was invalid.', $json['message']);
        $this->assertSame(['email' => ['Taken']], $json['errors']);
        $this->assertSame('v1', $json['version']);
    }

    public function test_success_envelope_has_no_errors_key(): void
    {
        $json = Envelope::success(null)->toResponse(Request::create('/x'))->getData(true);

        $this->assertArrayNotHasKey('errors', $json);
    }

    public function test_redirect_envelope_url_and_default_status(): void
    {
        $response = Envelope::redirect('/profile', 'Saved')->toResponse(Request::create('/x'));

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);
        $this->assertNull($json['data']);
        $this->assertSame('Saved', $json['message']);
        $this->assertSame('/profile', $json['meta']['redirect']);
    }

    public function test_redirect_status_is_configurable(): void
    {
        config(['unified-api.redirect_status' => 303]);

        $response = Envelope::redirect('/profile')->toResponse(Request::create('/x'));

        $this->assertSame(303, $response->getStatusCode());
    }

    public function test_empty_meta_encodes_as_json_object_not_array(): void
    {
        $content = Envelope::error('Unauthenticated.', status: 401)
            ->toResponse(Request::create('/x'))
            ->getContent();

        // Native clients parse meta as a map; [] would break object decoders.
        $this->assertStringContainsString('"meta":{}', $content);
        $this->assertStringNotContainsString('"meta":[]', $content);
    }
}
