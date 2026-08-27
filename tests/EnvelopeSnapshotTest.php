<?php

namespace Spaseossr\UnifiedApi\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use Spaseossr\UnifiedApi\Testing\EnvelopeSnapshot;

class EnvelopeSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolate every test's writes in its own temp directory and make
        // sure update mode is off unless a test turns it on.
        EnvelopeSnapshot::usingSnapshotPath(sys_get_temp_dir().'/unified-api-snapshots-'.uniqid());
        putenv('ENVELOPE_SNAPSHOT_UPDATE');
    }

    protected function tearDown(): void
    {
        putenv('ENVELOPE_SNAPSHOT_UPDATE');
        EnvelopeSnapshot::usingSnapshotPath(null);

        parent::tearDown();
    }

    public function test_first_run_creates_the_snapshot_and_passes(): void
    {
        Route::inertia('/team', 'Team/Show', ['team' => 'Acme']);

        envelopeSnapshot('team-show', fn () => $this->get('/team', ['Accept' => 'application/json']));

        $file = EnvelopeSnapshot::snapshotPath().'/team-show.json';
        $this->assertFileExists($file);

        $this->assertSame([
            'data' => ['team' => 'string'],
            'meta' => ['component' => 'string', 'url' => 'string'],
            'message' => 'null',
            'version' => 'string',
        ], json_decode(file_get_contents($file), true));
    }

    public function test_stable_shape_passes_on_the_next_run(): void
    {
        Route::inertia('/team', 'Team/Show', ['team' => 'Acme']);

        envelopeSnapshot('team-stable', fn () => $this->get('/team', ['Accept' => 'application/json']));
        envelopeSnapshot('team-stable', fn () => $this->get('/team', ['Accept' => 'application/json']));

        $this->assertTrue(true);
    }

    public function test_values_may_change_without_failing(): void
    {
        Route::inertia('/first', 'Team/Show', ['team' => 'Acme', 'members' => 3]);
        Route::inertia('/second', 'Team/Show', ['team' => 'Zorg Enterprises', 'members' => 99]);

        envelopeSnapshot('value-churn', fn () => $this->get('/first', ['Accept' => 'application/json']));
        envelopeSnapshot('value-churn', fn () => $this->get('/second', ['Accept' => 'application/json']));

        $this->assertTrue(true);
    }

    public function test_shape_change_fails_with_guidance(): void
    {
        Route::inertia('/before', 'Team/Show', ['team' => 'Acme']);
        Route::inertia('/after', 'Team/Show', ['team' => ['name' => 'Acme']]);

        envelopeSnapshot('shape-drift', fn () => $this->get('/before', ['Accept' => 'application/json']));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageMatches('/UNIFIED_API_VERSION/');

        envelopeSnapshot('shape-drift', fn () => $this->get('/after', ['Accept' => 'application/json']));
    }

    public function test_update_mode_regenerates_the_snapshot(): void
    {
        Route::inertia('/before', 'Team/Show', ['team' => 'Acme']);
        Route::inertia('/after', 'Team/Show', ['team' => ['name' => 'Acme']]);

        envelopeSnapshot('regen', fn () => $this->get('/before', ['Accept' => 'application/json']));

        putenv('ENVELOPE_SNAPSHOT_UPDATE=1');

        envelopeSnapshot('regen', fn () => $this->get('/after', ['Accept' => 'application/json']));

        $file = EnvelopeSnapshot::snapshotPath().'/regen.json';
        $this->assertSame(
            ['team' => ['name' => 'string']],
            json_decode(file_get_contents($file), true)['data']
        );
    }

    public function test_non_2xx_responses_fail_without_writing_a_file(): void
    {
        Route::get('/boom', fn () => response()->json(['data' => null], 422));

        try {
            envelopeSnapshot('boom', fn () => $this->get('/boom', ['Accept' => 'application/json']));

            $this->fail('A non-2xx response should have failed the snapshot.');
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString('2xx', $exception->getMessage());
        }

        $this->assertFileDoesNotExist(EnvelopeSnapshot::snapshotPath().'/boom.json');
    }

    public function test_snapshot_path_defaults_to_the_app_tests_directory(): void
    {
        EnvelopeSnapshot::usingSnapshotPath(null);

        $this->assertSame(
            base_path('tests/Snapshots/UnifiedApi'),
            EnvelopeSnapshot::snapshotPath()
        );
    }

    public function test_snapshot_names_are_slugged_into_file_names(): void
    {
        Route::inertia('/team', 'Team/Show', ['team' => 'Acme']);

        envelopeSnapshot('Users/Index page', fn () => $this->get('/team', ['Accept' => 'application/json']));

        $this->assertFileExists(EnvelopeSnapshot::snapshotPath().'/Users-Index-page.json');
    }
}
