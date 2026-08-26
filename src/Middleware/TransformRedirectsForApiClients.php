<?php

namespace Spaseossr\UnifiedApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransformRedirectsForApiClients
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
