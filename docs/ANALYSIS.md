# firozanam/unified-api — What It Solves, Why, and How

> One Laravel backend. Three clients — browser SPA, native mobile, native
> desktop. Same URLs, same controllers, one versioned contract.

This document is the analysis behind the package: the problem it exists to
solve, why you might choose it over the alternatives, exactly how it works,
and — just as important — when you should not use it. For installation copy,
see the [README](../README.md).

---

## 1. The problem

### One backend, three audiences

A typical product quickly grows past the browser:

- the **web app** — an Inertia SPA: server-side routing, React/Vue pages,
  SSR for SEO, session auth;
- a **mobile app** — needs data from the same features;
- maybe a **desktop app** — same again.

Inertia serves the first audience brilliantly and the other two not at all:
it is a web protocol. A request without the `X-Inertia` header gets the SSR
HTML document, and a request *with* it gets a page object full of
component names and asset hashes that only the Inertia JS client understands.
There is no JSON a native app can consume.

So every team building "Inertia app + mobile app" lands on the same fork in
the road:

1. build a **separate API layer** — new routes, new controllers (or a parallel
   `Api/` namespace), token auth, error formatting, versioning… for features
   that already exist; or
2. **hand-roll branching** inside the shared controllers — `if ($request->expectsJson())` on every action, re-implementing response shaping, redirects,
   and error formats one method at a time.

### The pain is real and recurring

This is not a hypothetical corner case. It surfaces constantly in the Laravel
community:

- r/laravel: *"Need to add a mobile app to my Inertia app — is it weird to
  not build a separate API?"* — the original poster calls a parallel API
  "redundant and I'd like to avoid it". Similar threads recur
  (*"Inertia or API?"*, *"Existing Laravel app now needs an API"*).
- Taylor Otwell's widely-discussed advice — *"Avoid separate SPAs consuming
  your Laravel API; use Livewire/Inertia"* — comes with an acknowledged gap:
  if mobile apps and the web consume the same backend, Livewire/Inertia alone
  don't solve it. That is precisely the paradox: keep the monolith (his
  advice) and you owe mobile an API anyway (the community's answer).
- The Rails world hit the same wall and answered at framework scale:
  **Hotwire Native** exists so a native shell can consume the web app instead
  of building a second backend. The pain was big enough for 37signals to
  build and maintain an entire framework around avoiding it.

The industry default today is still option 1 above — a separate API layer,
justified by separation of concerns. It works, but for first-party clients
of an Inertia app it means paying twice for the same features: two route
surfaces, two controller stacks, two auth stories to keep secure, two
response formats to keep in sync, and drift between them that no test
catches.

### Why the quick fixes don't fix it

| Attempt | Why it falls short |
|---|---|
| Send `X-Inertia: true` from the mobile app | The page object is a UI protocol, not an API: `component` refers to React/Vue files the native app doesn't have, `url`/`version` exist for browser history and asset hashing, and partial-reload/deferred/merge semantics assume the Inertia JS client. You'd be coupling native code to your frontend's internals. |
| `expectsJson()` branches in controllers | Works for one or two endpoints, then spreads everywhere. Every redirect, validation error, and flash message needs a second code path. Nothing standardizes the shape — each endpoint's JSON grows its own dialect. And on Inertia-rendered routes it needs deep surgery, because Inertia returns HTML to non-Inertia clients by design. |
| Separate API layer (the default) | Correct, but expensive for first-party clients: duplicated controllers/routes, duplicated auth wiring, response drift, double review surface. It optimizes for a boundary (public third-party API) most teams don't actually have. |

---

## 2. What the package is

`firozanam/unified-api` extends `inertiajs/inertia-laravel` so **one set of
URLs and controllers serves all three clients**, negotiated by the `Accept`
header:

| Request | Response |
|---|---|
| `Accept: text/html` | Inertia SSR HTML — stock behavior, unchanged |
| `X-Inertia: true` | Inertia page object — SPA navigation, unchanged |
| `Accept: application/json` | Envelope: `{data, meta, message, version}` |

```json
{
    "data":   { "users": 5, "auth": { "user": "Tania" } },
    "meta":   { "component": "Dashboard", "url": "/dashboard" },
    "message": "Profile updated.",
    "version": "v1"
}
```

Design principles, in priority order:

1. **The web app does not change.** Same routes, same controllers, same
   `Inertia::render()` calls, same SSR, same SEO. The package is invisible
   to browsers.
2. **One contract, everywhere.** Every JSON response — success, validation
   error, auth failure, 404, throttle, redirect — arrives in the same
   envelope with the same status-code discipline. Native clients parse one
   shape, ever.
3. **No new routes for existing features.** The URLs mobile calls are the
   URLs the browser visits.
4. **The contract is versioned and testable.** A `version` field clients can
   gate updates on, and snapshot tests that fail CI when the shape drifts.

---

## 3. Why use it

### What you stop paying for

- **A duplicate API surface** for features that already exist — no `Api/`
  controller namespace mirroring your real controllers.
- **A second auth implementation** — Sanctum dual-mode serves bearer tokens
  to native clients and keeps the session flow for browsers on the same
  routes.
- **Response-format archaeology** — no per-endpoint JSON dialects; the
  envelope (including error and redirect semantics) is uniform and tested.
- **Silent breakage of shipped apps** — contract snapshots make prop-shape
  drift a red CI run instead of a 3 a.m. crash report.

### Compared to the alternatives

| | Separate API layer | Hand-rolled branching | **unified-api** |
|---|---|---|---|
| Code duplication | high | low | **none** |
| Consistent native contract | you build it | per-endpoint drift | **guaranteed envelope** |
| Web behavior untouched | yes | mostly | **yes, byte-for-byte** |
| Auth for both client types | two wirings | possible | **one (Sanctum dual-mode)** |
| Effort to adopt | rewrite | grows per endpoint | **install + 3 small swaps** |
| Suits public 3rd-party API | **yes** | no | no (by design) |

### Honest trade-offs

This package is an opinionated corner, not the industry default — read this
part before adopting:

- **The envelope's `data` is your page props.** Native clients compile
  against your prop shapes, which serve the web UI first. The contract
  snapshot tests make changes loud, and `version` gates client updates, but
  the coupling itself is a fact of the design. If you need a contract fully
  independent of the web UI, keep a separate API layer (an opt-in
  transformer layer is a documented future extension; see §6).
- **First-party fit.** It shines when the consumers are apps you ship and
  can update. If you are publishing an API for third-party developers —
  external consumers who need OpenAPI docs, strict governance, and promises
  you can't deliver by changing your UI — build a real API.
- **Team convention.** Everyone touching props must internalize one rule:
  props of unified pages are a public contract (additive changes fine;
  renames/removals need a version bump). CI enforces it; culture decides
  how painful that is.

### When NOT to use it

- You already run a public, documented API for third parties.
- You need contract shapes decoupled from your web UI's needs.
- Your API team and web team are separate organizations with separate
  release cycles — the shared-surface coupling would be political, not
  technical.

---

## 4. How it works

The package hooks exactly one seam in Inertia and adds two middleware. No
forking, no macros you must remember:

```
GET /dashboard
  │
  ├─ Accept: text/html ──────────────► UnifiedResponse ──► parent (Inertia SSR HTML)
  ├─ X-Inertia: true ────────────────► UnifiedResponse ──► parent (page object)
  └─ Accept: application/json ───────► UnifiedResponse ──► envelope
```

1. **`UnifiedResponseFactory`** rebinds Inertia's `ResponseFactory` (loads
   after Inertia's own provider). Every `Inertia::render()` now returns a
   `UnifiedResponse` — a subclass of Inertia's `Response` that delegates to
   the parent for web clients and negotiates the envelope for JSON clients.
2. **`EagerPropsResolver`** resolves shared + page props — including
   deferred and optional props, so native clients always receive complete
   data (they cannot do partial reloads).
3. **`TransformRedirectsForApiClients`** (global middleware) converts
   redirect responses (302/303) into `200` (configurable) envelopes with
   `meta.redirect`, so native HTTP clients never silently follow a redirect
   as a GET.
4. **Error wrapping**: JSON responses with status ≥ 400 are re-wrapped into
   the same envelope (`{data: null, message, errors?, version}`) with the
   original status preserved — including exception-rendered 401/404/422/429.
5. **`ValidateCsrfTokenExceptApiClients`** (alias `unified.csrf`) drops the
   CSRF requirement for requests carrying `Accept: application/json` — safe
   because cross-site forms cannot set that header and cross-origin fetches
   cannot send it without passing a CORS preflight.
6. **`POST /api/token`** (optional, throttled) exchanges email + password
   for a Sanctum bearer token so native clients can bootstrap before
   anything else.

---

## 5. How to use it

### Install

```bash
composer require firozanam/unified-api
php artisan vendor:publish --tag=unified-api-config   # optional
```

### Auth: Sanctum dual-mode

```bash
composer require laravel/sanctum
php artisan install:api
```

- Add `Laravel\Sanctum\HasApiTokens` to your user model.
- Swap the middleware on your shared route group: `'auth'` → `'auth:sanctum'`.
  Bearer tokens win; the web session keeps working for browsers.

Native bootstrap:

```json
POST /api/token
{"email": "tania@example.com", "password": "…", "device_name": "iphone-15"}
```

### CSRF swap (`bootstrap/app.php`)

```php
$middleware->replaceInGroup(
    'web',
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, // Laravel 13+
    \TrivoLink\UnifiedApi\Middleware\ValidateCsrfTokenExceptApiClients::class,
);
// On Laravel 11–12, target ValidateCsrfToken::class instead.
```

### Write pages exactly as before

Nothing about authoring changes. `Inertia::render('Dashboard', $props)` is
the same call; browsers and native clients simply receive different
representations of it.

### Native client checklist

1. Send `Accept: application/json` on every request.
2. Bootstrap with `POST /api/token`; store `data.token`.
3. Send `Authorization: Bearer <token>` on every request.
4. Check `version` — a mismatch with the compiled-in contract means
   "prompt the user to update".
5. On `meta.redirect`, navigate explicitly; never rely on HTTP redirect
   following.

### Freeze the contract (recommended)

```php
test('dashboard envelope contract', function () {
    envelopeSnapshot('dashboard', fn () => $this
        ->actingAs(User::factory()->create())
        ->get(route('dashboard'), ['Accept' => 'application/json']));
});
```

Shape-only snapshots (values never recorded) land in
`tests/Snapshots/UnifiedApi/`. The change rule is two lines:

- additive (new keys) → regenerate with `ENVELOPE_SNAPSHOT_UPDATE=1`;
- remove/rename/retype → breaking → bump `UNIFIED_API_VERSION`, update
  clients, regenerate — same commit.

---

## 6. Limitations and the documented escape hatch

The known coupling (§3) has a designed-in exit: `UnifiedResponse::toEnvelopeResponse()`
is the single point where `data` is finalized. An opt-in per-component
transformer (API-resource) layer can be added there later — pages with
heavy or external consumers get an independent contract shape while the
rest stay props-as-contract. It is deliberately not built yet (YAGNI); the
seam is stable and tested.

---

## References

- r/laravel — [Need to add a mobile app to my Inertia app](https://www.reddit.com/r/laravel/comments/r6p6eg/need_to_add_a_mobile_app_to_my_inertia_app_is_it/)
- r/laravel — [Taylor Otwell: "Avoid Separate SPAs consuming Laravel API"](https://www.reddit.com/r/laravel/comments/kkzdkw/taylor_otwell_avoid_separate_spas_consuming/)
- Laravel Daily — [Web and API: same or separate controllers?](https://laraveldaily.com/post/laravel-web-api-same-separate-controllers)
- Hotwire Native — [native shells over a web backend, no separate API](https://native.hotwired.dev/)
- Inertia.js — [inertiajs/inertia-laravel](https://packagist.org/packages/inertiajs/inertia-laravel)
