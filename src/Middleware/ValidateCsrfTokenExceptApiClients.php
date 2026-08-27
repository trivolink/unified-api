<?php

namespace TrivoLink\UnifiedApi\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TrivoLink\UnifiedApi\ClientDetector;

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
     * The framework's CSRF middleware for the installed Laravel version:
     * PreventRequestForgery on Laravel 13+, ValidateCsrfToken on 11-12,
     * VerifyCsrfToken on legacy versions.
     */
    public static function csrfMiddleware(): string
    {
        if (class_exists(PreventRequestForgery::class)) {
            return PreventRequestForgery::class;
        }

        return class_exists(ValidateCsrfToken::class)
            ? ValidateCsrfToken::class
            : VerifyCsrfToken::class;
    }
}
