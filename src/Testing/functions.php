<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use TrivoLink\UnifiedApi\Testing\EnvelopeSnapshot;

if (! function_exists('envelopeSnapshot')) {
    /**
     * Snapshot the envelope shape of a unified request and compare it to the
     * stored contract (tests/Snapshots/UnifiedApi/<name>.json).
     *
     * @param  callable(): TestResponse|JsonResponse  $request
     */
    function envelopeSnapshot(string $name, callable $request): void
    {
        (new EnvelopeSnapshot)($name, $request);
    }
}
