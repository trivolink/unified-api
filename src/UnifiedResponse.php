<?php

namespace Spaseossr\UnifiedApi;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnifiedResponse extends Response
{
    /**
     * Negotiate the response format: unified JSON clients get the
     * envelope; everyone else gets stock Inertia behaviour (page
     * object for SPA visits, SSR HTML for initial visits).
     */
    public function toResponse($request)
    {
        if (ClientDetector::isUnifiedApiRequest($request)) {
            return $this->toEnvelopeResponse($request);
        }

        return parent::toResponse($request);
    }

    /**
     * Render the resolved props as a unified API envelope.
     */
    protected function toEnvelopeResponse(Request $request): JsonResponse
    {
        [$resolvedProps] = (new EagerPropsResolver($request, $this->component))
            ->resolve($this->sharedProps, $this->props);

        return Envelope::success(
            data: $resolvedProps,
            meta: $this->envelopeMeta($request),
            message: Inertia::pullFlashed($request)['message'] ?? null,
        )->toResponse($request);
    }

    /**
     * Build the meta object from config toggles.
     *
     * @return array<string, mixed>
     */
    protected function envelopeMeta(Request $request): array
    {
        $meta = [];

        if (config('unified-api.meta.component', true)) {
            $meta['component'] = $this->component;
        }

        if (config('unified-api.meta.url', true)) {
            $meta['url'] = $this->getUrl($request);
        }

        return $meta;
    }
}
