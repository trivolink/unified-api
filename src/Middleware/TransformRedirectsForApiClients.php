<?php

namespace Spaseossr\UnifiedApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spaseossr\UnifiedApi\ClientDetector;
use Spaseossr\UnifiedApi\Envelope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class TransformRedirectsForApiClients
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ClientDetector::isUnifiedApiRequest($request)) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            return Envelope::redirect(
                $this->relativeTarget($response->getTargetUrl(), $request),
                Inertia::pullFlashed($request)['message'] ?? null,
            )->toResponse($request);
        }

        if (config('unified-api.wrap_errors', true)
            && ($response->isClientError() || $response->isServerError())) {
            return $this->wrapError($response, $request);
        }

        return $response;
    }

    /**
     * Strip the app origin from internal redirect targets so they match
     * the relative form used elsewhere in the envelope (meta.url).
     * External targets (e.g. payment providers) stay absolute.
     */
    protected function relativeTarget(string $target, Request $request): string
    {
        $origin = $request->getSchemeAndHttpHost();

        if (str_starts_with($target, $origin)) {
            return substr($target, strlen($origin));
        }

        return $target;
    }

    /**
     * Re-wrap a rendered JSON error response into the unified envelope,
     * preserving the HTTP status code.
     */
    protected function wrapError(Response $response, Request $request): Response
    {
        if (! str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            return $response;
        }

        $original = json_decode($response->getContent(), true);

        if (! is_array($original)) {
            return $response;
        }

        return Envelope::error(
            message: $original['message']
                ?? (Response::$statusTexts[$response->getStatusCode()] ?? 'Error'),
            errors: $original['errors'] ?? null,
            status: $response->getStatusCode(),
        )->toResponse($request);
    }
}
