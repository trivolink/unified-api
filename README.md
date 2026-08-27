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
- `meta` — `component` (screen hint) and `url`, each disableable via `unified-api.meta.*`; always a JSON object, never an array
- `message` — flashed `message` key when present, else `null`
- `version` — API contract version (`UNIFIED_API_VERSION`, default `v1`)

POST endpoints that redirect respond `200` (configurable) with
`meta.redirect` instead of a 302/303, so native clients never silently
follow redirects. Validation and HTTP errors keep their status code and
arrive wrapped: `{data: null, message, errors?, version}` — including
exception-rendered responses (401 unauthenticated, 404, validation 422,
throttle 429, server 5xx).

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

### Getting a first token: `POST /api/token`

Mobile/desktop clients bootstrap their bearer token with an email +
password exchange (stateless, throttled to 5/min by default):

```json
POST /api/token
{"email": "tania@example.com", "password": "...", "device_name": "iphone-15"}
```

```json
{"data": {"token": "1|abc123..."}, "meta": {}, "message": null, "version": "v1"}
```

Wrong credentials get the 422 envelope with `errors.email`. The route
requires the user model to use `Laravel\Sanctum\HasApiTokens` and can be
configured or disabled under `unified-api.token_endpoint`:

```php
'token_endpoint' => [
    'enabled' => env('UNIFIED_API_TOKEN_ENDPOINT', true),
    'path' => env('UNIFIED_API_TOKEN_PATH', 'api/token'),
    'middleware' => ['throttle:5,1'],
],
```

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
2. Bootstrap: `POST /api/token` with email + password, store `data.token`.
3. Authenticate every request with `Authorization: Bearer <token>`.
4. Read `version`; when it differs from your compiled-in contract
   (e.g. you shipped `v1`, server now sends `v2`), prompt the user to
   update.
5. On `meta.redirect`, navigate explicitly — do not rely on HTTP
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

## Contract testing

The envelope's `data` is your resolved page props — for native clients,
those props ARE the API. Freeze their **shape** with snapshot tests so a
web refactor that renames, retypes or drops a prop fails CI instead of
silently breaking shipped apps:

```php
use function envelopeSnapshot; // global helper, autoloaded

test('dashboard envelope contract', function () {
    $user = User::factory()->create();

    envelopeSnapshot('dashboard', fn () => $this
        ->actingAs($user)
        ->get(route('dashboard'), ['Accept' => 'application/json']));
});
```

The first run writes `tests/Snapshots/UnifiedApi/dashboard.json` (shape
only — key tree plus JSON types; values never recorded, so factory data
and timestamps cannot flake). Later runs compare. Non-2xx responses fail
immediately: error envelopes are not contracts.

When a snapshot diff appears in a PR, the change rule is two lines:

- **additive** (new keys only) — regenerate: `ENVELOPE_SNAPSHOT_UPDATE=1 vendor/bin/pest`
- **breaking** (remove/rename/retype) — bump `UNIFIED_API_VERSION`, update
  consumers, and regenerate in the same commit

Store the snapshot path override for unusual layouts with
`EnvelopeSnapshot::usingSnapshotPath(...)`; the default is
`base_path('tests/Snapshots/UnifiedApi')`.

## Development

```bash
composer install
composer test      # phpunit
composer lint      # pint
```
