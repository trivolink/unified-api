# spaseossr/unified-api

One Laravel backend, three clients. Web keeps Inertia SSR HTML (SPA +
SEO); mobile and desktop apps receive a standardized JSON envelope —
from the same URLs and the same controllers.

## Response matrix

| Request | Response |
|---|---|
| `Accept: text/html` | Inertia SSR HTML (unchanged) |
| `X-Inertia: true` | Inertia page object (unchanged SPA navigation) |
| `Accept: application/json` | Envelope: `{data, meta, message, version}` |

```json
{
    "data": { "users": 5, "auth": { "user": "Tania" } },
    "meta": { "component": "Dashboard", "url": "/dashboard" },
    "message": "Profile updated.",
    "version": "v1"
}
```

- `data` — fully resolved page props (shared + page props, eager: deferred/optional props included)
- `meta` — `component` (screen hint) and `url`, each disableable via `unified-api.meta.*`
- `message` — flashed `message` key when present, else `null`
- `version` — API contract version (`UNIFIED_API_VERSION`, default `v1`)

POST endpoints that redirect respond `200` (configurable) with
`meta.redirect` instead of a 302/303, so native clients never silently
follow redirects. Validation and HTTP errors keep their status code and
arrive wrapped: `{data: null, message, errors?, version}`.

## Install

```bash
composer require spaseossr/unified-api
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=unified-api-config
```

## Auth: Sanctum dual-mode

```bash
composer require laravel/sanctum
php artisan install:api
```

Swap the auth middleware on your shared (web) route group:

```php
Route::middleware(['auth:sanctum', ...]) // was: 'auth'
```

Sanctum's guard checks `Authorization: Bearer <token>` first (issue
personal access tokens to your mobile/desktop apps) and falls back to
the web session for browsers — your existing Fortify/session flow keeps
working untouched.

## CSRF

Browser POSTs still require CSRF tokens (Inertia sends them
automatically). Stateless JSON clients must not be blocked by CSRF, so
swap the framework middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        // ... your existing exceptions
    ]);
    // Laravel 13+ (the web group ships PreventRequestForgery):
    $middleware->replaceInGroup(
        'web',
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Spaseossr\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients::class,
    );
    // On Laravel 11-12, target ValidateCsrfToken::class instead.
})
```

This is CSRF-safe: the exemption requires the custom
`Accept: application/json` header, which cross-site forms can never set
and cross-origin fetches cannot send without passing a CORS preflight.
Bearer-authenticated requests carry no ambient cookie credentials for an
attacker to ride.

## Mobile/Desktop client checklist

1. Send `Accept: application/json` on every request.
2. Authenticate with `Authorization: Bearer <sanctum-token>`.
3. Read `version`; when it differs from your compiled-in contract
   (e.g. you shipped `v1`, server now sends `v2`), prompt the user to
   update.
4. On `meta.redirect`, navigate explicitly — do not rely on HTTP
   redirect following.

## Why not just send X-Inertia from mobile?

The Inertia page object is a UI protocol, not an API contract:
`component` names refer to React/Vue page components the native app
does not have, `url`/`version` exist for browser history and asset
hashing, and partial-reload/deferred/merge semantics assume the Inertia
JS client. The envelope is a stable, minimal contract purpose-built for
native consumers.

## Testing your app

Everything Inertia offers keeps working (`assertInertia` etc.). For
unified clients, assert on the envelope:

```php
$this->get('/dashboard', ['Accept' => 'application/json'])
    ->assertOk()
    ->assertJsonPath('version', 'v1')
    ->assertJsonPath('data.users', 5);
```

## Development

```bash
composer install
composer test      # phpunit
composer lint      # pint
```
