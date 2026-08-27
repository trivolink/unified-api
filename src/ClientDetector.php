<?php

namespace TrivoLink\UnifiedApi;

use Illuminate\Http\Request;

class ClientDetector
{
    /**
     * Determine whether the request is from a unified API client
     * (mobile/desktop) that should receive the JSON envelope.
     *
     * The X-Inertia header marks browser SPA navigations, which also
     * send Accept: application/json — those must keep receiving the
     * Inertia page object, not the envelope.
     */
    public static function isUnifiedApiRequest(Request $request): bool
    {
        return $request->wantsJson() && ! $request->inertia();
    }
}
