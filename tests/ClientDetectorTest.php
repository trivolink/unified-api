<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Http\Request;
use Spaseossr\UnifiedApi\ClientDetector;

class ClientDetectorTest extends TestCase
{
    public function test_browser_html_request_is_not_unified(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]);

        $this->assertFalse(ClientDetector::isUnifiedApiRequest($request));
    }

    public function test_json_accept_header_marks_unified_client(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertTrue(ClientDetector::isUnifiedApiRequest($request));
    }

    public function test_inertia_spa_request_is_not_unified(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INERTIA' => 'true',
        ]);

        $this->assertTrue($request->inertia());
        $this->assertFalse(ClientDetector::isUnifiedApiRequest($request));
    }

    public function test_wildcard_accept_is_not_unified(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => '*/*',
        ]);

        $this->assertFalse(ClientDetector::isUnifiedApiRequest($request));
    }
}
