<?php

namespace Spaseossr\UnifiedApi;

use Inertia\PropsResolver;

class EagerPropsResolver extends PropsResolver
{
    /**
     * Unified API clients (mobile/desktop) have no partial-reload or
     * once-tracking machinery, so every prop — including deferred and
     * optional ones — must resolve immediately into the envelope data.
     */
    protected function excludeFromInitialResponse(mixed $prop, string $path): bool
    {
        return false;
    }
}
