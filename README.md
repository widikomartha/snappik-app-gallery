# SnapPik

SnapPik is a subscription application for photographer photo selection and finished-photo delivery. TASK-001 establishes the application foundation. TASK-002 adds a local/test-only Drive-to-R2 ZIP prototype; real-provider verification is blocked by missing configuration. Product workflows remain in later [backlog tasks](docs/planning/BACKLOG.md).

## Current state

Laravel 13, PHP 8.4+, React 19, TypeScript, Inertia 3, Vite 8, Motion 14 and PostgreSQL are configured at the repository root. Both package managers have real lockfiles. The skeleton was generated from official `laravel/laravel` v13.11.0, with the official Inertia adapter added separately to keep authentication workflows in TASK-003.

The owner requested TASK-001 and TASK-002 on October 22, 2026. Final approval of the entire documentation package remains pending in the [index](docs/index.md). No connected provider account, production deployment, or finished gallery is implied.

## Prerequisites

- PHP 8.4 or 8.5 with the Laravel-required extensions plus `pdo_pgsql`; Composer 2. Enable `curl`, `fileinfo`, `mbstring`, `openssl`, `intl`, and `zip` in local PHP installations.
- Node.js 24 LTS (or Node 22.12+) and npm.
- PostgreSQL 18, running locally, with separate development and test databases. Tests run against PostgreSQL, never SQLite.

Create the local role and databases from a PostgreSQL administrator session (`psql`), choosing your own local password:

```sql
CREATE ROLE snappik LOGIN;
\password snappik
CREATE DATABASE snappik OWNER snappik;
CREATE DATABASE snappik_test OWNER snappik;
```

## Fresh checkout setup

Run from the repository root. `php` and `composer` must be on PATH. Commands below work in PowerShell; use `cp` instead of `Copy-Item` on Linux/macOS.

```powershell
composer install --no-interaction --prefer-dist
Copy-Item .env.example .env
Copy-Item .env.testing.example .env.testing
```

Set local `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` in both ignored files. Keep `DB_DATABASE=snappik_test` in `.env.testing`; the suite refreshes that database. Never point tests at a development or production database. A guard rejects databases whose name does not end in `_test`, before test migrations run. Avoid exporting a development `DB_DATABASE` into the shell running tests because process variables override dotenv.

```powershell
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open [local SnapPik](http://127.0.0.1:8000). For frontend hot reload, run `npm run dev` in a second terminal while Artisan serves PHP. Keep the Vite development server local. The default database queue/cache/session tables are migrated. The TASK-002 migration adds isolated proof staging tables; product workers and schedules arrive with their owning tasks. No user is seeded automatically.

## Checks

```powershell
composer validate --strict
composer lint
composer test
npm run build
npm test
npx playwright install chromium
npm run test:browser
```

Playwright starts a local Artisan server when needed. It uses the development `.env` database and synthetic preview pages, without creating accounts. Do not run it against a production server. Screenshots are generated under ignored `test-results/`. CI installs locked dependencies, uses an isolated PostgreSQL 18 service with synthetic credentials, and runs the same checks without deployment or external-service secrets.

The initial Windows verification used portable PHP, Composer, PostgreSQL, and Chromium under ignored `.local-tools/`; these are machine-local tools, not shipped dependencies. See [TASK-001 evidence](docs/planning/TASK-001_EVIDENCE.md) for exact runtime versions, commands, outcomes and limits.

## TASK-002 Drive/ZIP proof

The code and synthetic checks exist; the authorized real Drive/R2 smoke test is **blocked**. See [TASK-002 evidence](docs/planning/TASK-002_EVIDENCE.md). The owner confirmed that test-source/provider configuration is unavailable. No scraping, photographer OAuth, service purchase or production bucket is substituted.

The standard PHP suite includes synthetic adapter/SDK transport contracts and real PostgreSQL staging tests. The large proof is opt-in because it temporarily writes and extracts over 4 GiB. It runs separately in CI without provider credentials:

```powershell
$env:SNAPPIK_LARGE_PROOF = '1'
php -d memory_limit=128M vendor/bin/phpunit --filter LargeZipProofTest
Remove-Item Env:SNAPPIK_LARGE_PROOF
```

This uses generated streams and a local HTTP transport fixture behind the real AWS SDK. It verifies every entry's length, CRC and original MD5, a second entry beyond the ZIP32 offset boundary, and ZIP64 records. It deletes the temporary object and writes ignored metrics to `storage/logs/large-zip-proof.json`. Allow at least 6 GiB of free temporary disk. This proves byte streaming, not real provider access or arbitrary catalogue-count memory.

When an authorized test folder and private R2 sandbox bucket become available, configure the empty `GOOGLE_DRIVE_API_KEY`, `DRIVE_PROOF_FOLDER_URL`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY` and `R2_ARCHIVE_BUCKET` variables in the ignored `.env`. Restrict the server API key to Drive API and the server's allowed access; use only a bucket-scoped R2 test token. Keep the bucket private with public development/custom-domain access disabled. No endpoint override or public object URL is supported. Never put the source capability or credentials in command arguments, source, screenshots or evidence.

Use `APP_ENV=local` and an existing **synthetic local test user's numeric ID** as `$proofOwnerId`. No user is seeded automatically. The following is the smoke-test procedure to execute after configuration, not a record of a completed provider run:

```powershell
php artisan config:clear
php artisan migrate
php artisan snappik:drive-proof --owner=$proofOwnerId
```

The command imports all descendants, reports supported/unsupported/shortcut counts, builds one private ZIP, then downloads the completed object to temporary diagnostic disk and independently extracts every identity with CRC/original-byte checks. It prints a run UUID and sanitized counts/timing/memory. Duplicate names gain deterministic identity suffixes. Original source files are never changed or retained as a library.

For an interrupted import or retryable failed ZIP, reuse that run UUID. Queueing and subsequent verification are also available:

```powershell
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --queue
php artisan queue:work --queue=drive-proof --tries=1 --timeout=0 --stop-when-empty
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --verify
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --cleanup
```

These commands refuse production execution and scope runs to the specified persisted local test user. The worker claims each eligible attempt once; it is a prototype, with no public gallery route, production access policy or crash lease. A failed input aborts/deletes the whole attempt. A cleanup failure becomes `cleanup_required` and needs operator investigation; it cannot be retried as a ready archive. Use the provider console to investigate orphaned multipart uploads if the process was killed. The product sweeper, durable cleanup retries and crash recovery remain TASK-008. Proof objects have a 24-hour application expiry, but this prototype has no automatic cleanup schedule: explicitly run `--cleanup` and configure the private test bucket's temporary-object/incomplete-upload lifecycle backstop.

The installed Windows libzip strict `CHECKCONS` checker rejects the large zero-header ZIP64 layout; normal libzip extraction with complete CRC/MD5 validation and Python's independent CRC reader passed. Compatibility across actual client extractors remains a gate for delivery productization; do not disable ZIP64 or split the archive to hide it.

## Boundaries and security

- `/` and `/login` render public/authentication shells. Login/registration/reset/verification actions are deliberately absent until TASK-003; the UI explains their availability.
- `/dashboard` requires a verified session; `/admin` additionally checks the persisted admin role through a server-side Gate. Role is excluded from user mass assignment. No default admin, provisioning command, or credentials exist.
- `/g/{capability}` fails closed with 404 until scoped capability authorization is implemented. It exposes no gallery data.
- `/_foundation/{public|auth|photographer|admin|client}` provides local/testing-only synthetic component previews. Production always returns 404, including when routes are cached. These are preview surfaces, not an authentication bypass to data.
- The web middleware retains Laravel's cookie encryption, session authentication, CSRF and request-forgery protection. Session cookies are HttpOnly/SameSite=Lax, database session payloads are encrypted, and production forces Secure cookies and disables debug. Configure HTTPS and trusted proxies explicitly before release; no wildcard proxy trust is enabled.
- Built assets use a nonce-based script CSP, frame denial, no-referrer, nosniff, and private/no-store responses. The before-paint theme initializer shares its controller with React. Vite hot reload uses its development CSP exception. No SSR service is enabled.
- Mail defaults to the in-memory array transport; no messages or password links are sent or logged. Local storage is private and has no automatic file-serving route. Drive/R2 have a local/test-only prototype; real accounts are not connected. Midtrans, product tenant policies, suspension, entitlement enforcement and login throttles remain later work.
- Self-hosted DM Sans and Raleway assets come from locked Fontsource packages under SIL OFL 1.1; licenses ship in `public/licenses/`. Photo pixels have no theme filter. Motion respects reduced-motion preferences at the root and on custom interactions.

Do not publish this bootstrap as a working product. Publication, production configuration and live transactions require the separate release gate and owner authorization.

## Documentation

- [Index and approval record](docs/index.md)
- [PRD](docs/product/PRD.md) — behavior and scope
- [Design System](docs/design/DESIGN_SYSTEM.md) — tokens, components and motion
- [Architecture](docs/engineering/ARCHITECTURE.md) — technical contracts
- [Project structure](docs/engineering/PROJECT_STRUCTURE.md) — existing and planned paths
- [Backlog](docs/planning/BACKLOG.md) — sequencing and evidence
- [AGENTS.md](AGENTS.md) — workflow safeguards
