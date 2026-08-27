# Publishing Guide

Everything between "code is done" and "installable from Packagist", in
execution order. Each section explains what to do, why, and gives the exact
commands or file contents to use.

> Steps 1–4 are **done**: license under "TrivoLink" (edit `LICENSE` +
> composer.json `authors` in one line each if you prefer a personal name),
> CI adopted with a PHP 8.2–8.5 matrix, and the package is named
> `triviumlabs/unified-api` to match the GitHub org (the PHP namespace
> stays `TrivoLink\UnifiedApi` — package name and namespace are
> independent). What remains for the maintainer: step 5 (push + tag) and
> step 6 (Packagist).

## Status

| # | Item | Status | Blocks publishing? |
|---|---|---|---|
| 1 | `LICENSE` file | ✅ done — MIT, TrivoLink | — |
| 2 | composer.json `authors` / `keywords` / `support` | ✅ done | — |
| 3 | `composer.lock` tracked in git | ✅ untracked + ignored | — |
| 4 | CI workflow | ✅ `.github/workflows/tests.yml` | — |
| 5 | Push + tag a release | user does this manually | yes |
| 6 | Packagist submission + auto-update hook | not done | yes — this is what makes `composer require` work |

---

## 1. Add the LICENSE file

`composer.json` already declares `"license": "MIT"`, but without the file
GitHub shows no license and the grant is not spelled out. Create `LICENSE`
in the package root:

```
MIT License

Copyright (c) 2026 <COPYRIGHT HOLDER>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

Replace `<COPYRIGHT HOLDER>` with your name or organization (this is open
decision #1). Once the file exists, GitHub renders the license on the repo
page automatically.

## 2. Complete composer.json metadata

Merge these keys into `composer.json` (keywords make the package findable in
Packagist search — `inertia`, `mobile`, `api` are the terms people actually
query; `authors` is how Packagist attributes you as maintainer):

```json
"keywords": [
    "laravel",
    "inertia",
    "inertiajs",
    "api",
    "json",
    "mobile",
    "desktop",
    "rest",
    "content-negotiation",
    "envelope",
    "sanctum"
],
"authors": [
    {
        "name": "<YOUR NAME>",
        "email": "<OPTIONAL EMAIL>"
    }
],
"homepage": "https://github.com/triviumlabs/unified-api",
"support": {
    "issues": "https://github.com/triviumlabs/unified-api/issues",
    "source": "https://github.com/triviumlabs/unified-api"
}
```

Drop the `email` line entirely if you prefer not to publish one. Validate
afterwards:

```bash
composer validate
```

## 3. Untrack composer.lock

Two reasons:

1. Library convention — locks are for applications; consumers resolve their
   own dependency tree.
2. This lock file references the local path repository
   `../inertia-laravel-3.x`, which does not exist on any other machine.
   Anywhere else (including CI), `composer install` from this lock fails.

```bash
git rm --cached composer.lock
echo "composer.lock" >> .gitignore
git commit -m "chore: stop tracking composer.lock"
```

The file stays on disk for local development — it is just no longer version
controlled.

## 4. CI workflow (recommended)

`.github/workflows/tests.yml` — runs Pint + PHPUnit on every push to
`main` and every pull request, across the supported PHP range:

```yaml
name: tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: true
      matrix:
        php: ['8.2', '8.3', '8.4', '8.5']
    name: PHP ${{ matrix.php }}
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      # Local-dev path repositories (../inertia-laravel-3.x) do not exist
      # on CI. Drop them so dependencies resolve from Packagist — which is
      # also what consumers get, so testing against it is the honest check.
      - name: Remove local path repositories
        run: composer config repositories --unset || true

      - name: Install dependencies
        run: composer update --no-interaction --no-progress

      - name: Enforce code style
        run: vendor/bin/pint --test

      - name: Run tests
        run: vendor/bin/phpunit
```

Notes:

- `composer update` (not `install`) — there is no lock on CI after step 3.
- Removing the `repositories` entry means CI tests against the released
  `inertiajs/inertia-laravel` from Packagist rather than the local fork.
  That is desirable: it matches what consumers install.
- This is open decision #2 — ship it or skip it. Shipping it enables the
  README badge below.

## 5. Push and tag the first release (manual — yours)

Prerequisite: create the **`triviumlabs`** organization on GitHub first
(<https://github.com/organizations/new>, plan: Free). The bare `trivium`
name is taken on Packagist (vendor claimed by `trivium/api`), which is why
the vendor carries the `-labs` suffix; both names are free as `triviumlabs`.

```bash
# from the package root, after steps 1–4 are committed
git remote add origin git@github.com:triviumlabs/unified-api.git
git push -u origin main

git tag -a v1.0.0 -m "Initial stable release"
git push origin v1.0.0
```

The GitHub org and the Packagist vendor are both `triviumlabs`, so
`composer require triviumlabs/unified-api` is the install command.

Tagging is what lets Packagist pick up an installable version.

## 6. Publish on Packagist

1. Create a Packagist account (sign in with GitHub) at
   <https://packagist.org>.
2. Submit the package: <https://packagist.org/submit> → paste
   `https://github.com/triviumlabs/unified-api`.
3. Enable auto-updates so future tags sync without manual work: install the
   **Packagist GitHub App** on your `triviumlabs/unified-api` repository
   (Settings → GitHub Apps → Packagist), or add the legacy webhook
   (`https://packagist.org/api/github?username=<your-packagist-username>`).

After this, `composer require triviumlabs/unified-api` works worldwide.

## 7. Optional polish

**README badges** (top of `README.md`, after CI exists):

```markdown
[![Tests](https://github.com/triviumlabs/unified-api/actions/workflows/tests.yml/badge.svg)](https://github.com/triviumlabs/unified-api/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/triviumlabs/unified-api)](https://packagist.org/packages/triviumlabs/unified-api)
[![License](https://img.shields.io/packagist/l/triviumlabs/unified-api)](LICENSE)
```

**`.gitattributes`** — slim down what `composer require` downloads by
export-ignoring dev files (create `.gitattributes` in the package root):

```
/docs export-ignore
/tests export-ignore
/.github export-ignore
/phpunit.xml export-ignore
/.gitignore export-ignore
```

## Pre-flight checklist

Run in order, then push:

- [ ] `LICENSE` created, `<COPYRIGHT HOLDER>` replaced
- [ ] composer.json: `authors`, `keywords`, `support`, `homepage` merged; `composer validate` passes
- [ ] `composer.lock` untracked and gitignored
- [ ] CI workflow committed (if adopted)
- [ ] `.gitattributes` added (optional)
- [ ] Full suite green locally: `composer test && vendor/bin/pint --test`
- [ ] Remote added, `main` pushed, release tagged
- [ ] Packagist submitted + auto-update hook enabled
