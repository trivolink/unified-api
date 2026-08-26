<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contract Version
    |--------------------------------------------------------------------------
    | The API contract version embedded in every envelope. Bump this on
    | breaking envelope changes so old clients can detect and prompt an
    | update ("v2"), instead of silently mis-parsing responses.
    */

    'version' => env('UNIFIED_API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Meta
    |--------------------------------------------------------------------------
    | Which request-derived hints to embed in the envelope's meta object.
    | `component` is the Inertia page component name (useful as a screen
    | identifier hint); `url` is the current request path.
    */

    'meta' => [
        'component' => true,
        'url' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Wrapping
    |--------------------------------------------------------------------------
    | When enabled, JSON error responses (status >= 400) returned to
    | unified clients are re-wrapped into the standard envelope while
    | preserving the original HTTP status code.
    */

    'wrap_errors' => true,

    /*
    |--------------------------------------------------------------------------
    | Redirect Status
    |--------------------------------------------------------------------------
    | HTTP status used when a redirect response is transformed into an
    | envelope for unified clients. 200 keeps native HTTP clients from
    | silently following the redirect as a GET.
    */

    'redirect_status' => 200,

];
