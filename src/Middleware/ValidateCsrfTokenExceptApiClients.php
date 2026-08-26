<?php

namespace Spaseossr\UnifiedApi\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Spaseossr\UnifiedApi\ClientDetector;
use Symfony\Component\HttpFoundation\Response;

class ValidateCsrfTokenExceptApiClients
{
    /**
     * Stateless JSON clients (custom Accept: application/json header,
     * no X-Inertia) are authenticated via bearer token rather than
     * cookies — CSRF does not apply to them, since a cross-site
     * attacker can never make a browser send that custom header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (ClientDetector::isUnifiedApiRequest($request)) {
            return $next($request);
        }

        return app(static::csrfMiddleware())->handle($request, $next);
    }

    /**
     * Laravel 12+ renamed VerifyCsrfToken to ValidateCsrfToken;
     * support both across the supported framework range.
     */
    public static function csrfMiddleware(): string
    {
        return class_exists(ValidateCsrfToken::class)
            ? ValidateCsrfToken::class
            : VerifyCsrfToken::class;
    }
}
