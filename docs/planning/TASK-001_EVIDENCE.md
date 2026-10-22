# TASK-001 verification record

Date: October 22, 2026 (Asia/Makassar). Scope: application bootstrap. The owner's explicit TASK-001 request is recorded in [package status](../index.md); final approval of the document package remains pending.

## Implemented foundation

Official Laravel skeleton, React/TypeScript/Inertia entry, PostgreSQL migrations and isolated tests, database queue/cache/encrypted-session configuration, empty seeder, protected page boundaries, semantic light/dark tokens, self-hosted fonts/licenses, accessible button/field/status/dialog primitives and root `MotionConfig reducedMotion="user"`. Authentication actions, gallery authorization and provider workflows remain future tasks.

| Dependency/runtime | Verified version |
| --- | --- |
| Official `laravel/laravel` skeleton | 13.11.0 |
| PHP / Composer | 8.4.27 / 2.10.3 |
| Laravel / Inertia Laravel adapter | 13.38.0 / 3.5.2 |
| PostgreSQL | 18.6, Windows x64 |
| Node / npm | 24.18.0 / 11.16.0 |
| React / Inertia React adapter | 19.3.1 / 3.9.1 |
| TypeScript / Vite | 5.9.3 / 8.3.5 |
| Motion / Vitest | 14.5.0 / 4.1.12 |
| Playwright / Chromium | 1.64.1 / 156.0.8078.4 |

PHP and Composer downloads were checked against published SHA-256 values before execution. PostgreSQL came from EDB's official binary archive. Portable tools, keys, databases and dependencies are ignored. No production/reference credentials were used.

## Commands and inspected results

The workspace lacked PHP, Composer, PostgreSQL and Chromium on PATH. Verification used ignored local tools with these PowerShell process settings:

```powershell
$env:PATH = 'D:\Website\snappik-app-gallery\.local-tools\php;' + $env:PATH
$env:PLAYWRIGHT_BROWSERS_PATH = 'D:\Website\snappik-app-gallery\.local-tools\browsers'
```

Where README uses `composer`, this machine used `php .local-tools/composer.phar` from the root, or `php ../composer.phar` from the clean-source directory.

| Actual command | Inspected result |
| --- | --- |
| `php .local-tools/composer.phar validate --strict` | Valid manifest and lockfile |
| `php .local-tools/composer.phar lint` | Pint passed |
| `php .local-tools/composer.phar audit` | No security vulnerability advisories |
| `php artisan migrate --no-interaction` | All three users/session, cache and jobs migrations passed on PostgreSQL |
| `php .local-tools/composer.phar test` | 8 feature tests passed, 67 assertions |
| `npm run build` | Strict TypeScript check and Vite production build passed |
| `npm test` | 2 files, 5 tests passed |
| `npm run test:browser` | 11 tests passed, 30.4 seconds on final browser run |
| `npm ci` in clean source copy | Locked install passed; 0 npm vulnerability advisories |
| `php artisan route:list --except-vendor` | Seven application routes inspected; no auth mutations/provider routes |
| `git check-ignore` on keys/tools/dependencies/builds/screenshots | All inspected private/generated paths ignored |

A negative safety check set process `DB_DATABASE=snappik` and ran `php vendor/bin/phpunit --filter test_real_postgresql_schema_and_password_security`. Inspected the expected exception: `Tests require a dedicated PostgreSQL database ending in _test.` The guard runs during application creation, before `RefreshDatabase` migrations. Normal tests used `snappik_test`.

## Clean source setup

Created `.local-tools/fresh-check/` from tracked and non-ignored source paths, without dependencies, built assets, local keys or runtime caches. This was a clean source-copy check, not a remote checkout or hosted CI run. Created `.env` and `.env.testing` from examples, using new `snappik_fresh` for setup and `snappik_test` for tests. The isolated PostgreSQL instance listened only on `127.0.0.1:55432`, with local loopback trust authentication; README instructs a normal password-protected local role. Application PostgreSQL connections explicitly use UTC.

From `D:\Website\snappik-app-gallery\.local-tools\fresh-check`, executed:

```powershell
php ../composer.phar install --no-interaction --prefer-dist
php artisan key:generate --quiet
php artisan key:generate --env=testing --quiet
php artisan migrate --no-interaction
npm ci
npm run build
npm test
php ../composer.phar test
```

Composer installed 108 locked packages; migrations, npm install, build and five frontend tests passed. The first clean-source PHPUnit run found a reference to the removed empty `tests/Unit` directory, which Git would not preserve. Removed the unused suite, copied the corrected `phpunit.xml` into the clean directory and reran the test command. No placeholder test or SQLite substitution was introduced.

## Browser and security coverage

- Five synthetic shells × 360/1440px × light/dark × normal/reduced motion: 40 combinations. Each checked no horizontal page overflow, rendered text contrast at least 4.5:1 across body/button/status/error/selection samples, keyboard theme activation, draft preservation, modal Tab containment, Escape and focus restoration. No JavaScript page errors were observed.
- Stored theme survives Inertia navigation/reload. Initial theme applies while the application bundle is blocked. Switching preserves drafts with blocked storage. Additional frontend tests cover system changes, invalid stored values and in-memory subscriptions.
- Forty screenshots were generated under ignored `test-results/`. The 360px light public and 1440px dark client foundation captures were visually inspected for spacing, wrapping, focus and state readability. These are component previews, not completed product pages.
- Server tests cover verified-session gates, photographer denial at admin, role mass-assignment exclusion, persisted admin access, closed client access, preview denial in production, PostgreSQL schema/password hashing/UTC and CSRF rejection.

The first browser run exposed Tab escaping page focus from the native dialog; explicit first/last control wrapping fixed it. Vitest was upgraded to patched 4.1.12 after initial npm audit identified two moderate mocker advisories; the final locked install reports zero advisories.

## Limits

The GitHub Actions workflow configures locked installs, PostgreSQL 18, PHP 8.4, Node 24 and the local check commands with read-only repository permissions. It has not run on GitHub; no remote or deployment is configured. Windows sandbox restrictions required approved execution outside the sandbox for PHP writable checks, temporary test files, loopback connections and Chromium.

No Drive/Midtrans/R2/SMTP integration, real client selections/jobs/photos, large-gallery performance, subscription policy, domain races, live messages/payments or publication is claimed. Those remain with their owning backlog tasks. No paid content quotas, hosted uploads, storage tiers or ZIP behavior were introduced.
