# SnapPik

SnapPik is a subscription application for photographer photo selection and finished-photo delivery. TASK-001 establishes the application foundation; TASK-003 adds account authentication, one trial, roles and shared access policy. TASK-002 adds a local/test-only Drive-to-R2 ZIP prototype; a complete 384-photo ZIP, independent CRC/MD5 verification and cleanup have passed against the configured providers. A real resource-key fixture remains pending. Project/gallery/billing workflows remain in later [backlog tasks](docs/planning/BACKLOG.md).

## Current state

Laravel 13, PHP 8.4+, React 19, TypeScript, Inertia 3, Vite 8, Motion 14 and PostgreSQL are configured at the repository root. Both package managers have real lockfiles. The skeleton was generated from official `laravel/laravel` v13.11.0, with the official Inertia adapter added separately to keep authentication workflows in TASK-003.

The owner requested TASK-001, TASK-002 and TASK-003 on October 22, 2026, and configured and authorized the recorded provider smoke testing. Final approval of the entire documentation package remains pending in the [index](docs/index.md). Provider test access does not imply production deployment or a finished gallery.

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
php artisan migrate --env=testing
npm run test:browser:auth
```

Playwright starts a local Artisan server when needed. It uses the development `.env` database and synthetic preview pages, without creating accounts. Do not run it against a production server. Screenshots are generated under ignored `test-results/`. CI installs locked dependencies, uses an isolated PostgreSQL 18 service with synthetic credentials, and runs the same checks without deployment or external-service secrets.

The separate `test:browser:auth` command starts a fresh loopback server on port 18763 using `.env.testing`, forces `DB_DATABASE=snappik_test`, database sessions/cache and in-memory mail, and exercises synthetic registration/resend/logout/login. Run it after PHPUnit, not concurrently with database-refreshing tests. It leaves synthetic test accounts in that disposable database until the next test refresh. No existing server is reused. The ordinary browser suite skips that write scenario; it still checks account form layout and invalid-login/recovery feedback. PostgreSQL verification/reset races use separate PHP processes in the backend suite.

The initial Windows verification used portable PHP, Composer, PostgreSQL, and Chromium under ignored `.local-tools/`; these are machine-local tools, not shipped dependencies. See [TASK-001 evidence](docs/planning/TASK-001_EVIDENCE.md) for exact runtime versions, commands, outcomes and limits.

## TASK-002 Drive/ZIP proof

The configured public Drive catalogue imported **384 photos / 1,130,185,579 source bytes** and produced **one 1,130,302,773-byte R2 ZIP**. Independent readback/extraction matched every entry's size, CRC and original MD5. The test object was then deleted; authenticated HEAD confirmed absence and no multipart uploads remained. See [TASK-002 evidence](docs/planning/TASK-002_EVIDENCE.md#complete-canonical-route-build-and-r2-readback-follow-up) for measured results, earlier native failures and remaining gates. TASK-002 remains incomplete because an authorized real resource-key fixture has not been exercised; the configured tree supplied none.

The standard PHP suite includes synthetic adapter/SDK transport contracts and real PostgreSQL staging tests. The large proof is opt-in because it temporarily writes and extracts over 4 GiB. It runs separately in CI without provider credentials:

```powershell
$env:SNAPPIK_LARGE_PROOF = '1'
php -d memory_limit=128M vendor/bin/phpunit --filter LargeZipProofTest
Remove-Item Env:SNAPPIK_LARGE_PROOF
```

This uses generated streams and a local HTTP transport fixture behind the real AWS SDK. It verifies every entry's length, CRC and original MD5, a second entry beyond the ZIP32 offset boundary, and ZIP64 records. It deletes the temporary object and writes ignored metrics to `storage/logs/large-zip-proof.json`. Allow at least 6 GiB of free temporary disk. This proves byte streaming, not real provider access or arbitrary catalogue-count memory.

For another local environment, configure `GOOGLE_DRIVE_API_KEY`, `DRIVE_PROOF_FOLDER_URL`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY` and `R2_ARCHIVE_BUCKET` in the ignored `.env`. Restrict the server API key to Drive API and the server's allowed access; use only a bucket-scoped R2 test token. Keep the bucket private with public development/custom-domain access disabled. No endpoint override or public object URL is supported. Never put the source capability or credentials in command arguments, source, screenshots or evidence.

Use `APP_ENV=local` and an existing **synthetic local test user's numeric ID** as `$proofOwnerId`. No user is seeded automatically. The following is the supported smoke-test procedure; the evidence distinguishes the completed catalogue proof from remaining fixture and compatibility checks:

```powershell
php artisan config:clear
php artisan migrate
php artisan snappik:drive-proof --owner=$proofOwnerId
```

The command imports all descendants, reports supported/unsupported/shortcut counts, builds one private ZIP, then downloads the completed object to temporary diagnostic disk and independently extracts every identity with CRC/original-byte checks. It prints a run UUID and sanitized counts/timing/memory. Duplicate names gain deterministic identity suffixes. Original source files are never changed or retained as a library.

Drive requests now share a credential-scoped pacing clock through Laravel's configured cache and lock. `GOOGLE_DRIVE_REQUEST_INTERVAL_MS` defaults to **1000 ms** between request starts, including metadata checks, media requests and retries. The operational setting accepts 100–10000 ms; it does not limit the number or size of photos/projects. Keep `CACHE_STORE=database` for cross-worker coordination. A cache/lock failure stops the request instead of sending it without pacing. This reduces request bursts; it cannot override a provider refusal. Automated-query HTML blocks remain terminal, while documented JSON rate-limit responses retain bounded backoff. API keys remain in headers; no credential, network or provider-endpoint rotation is implemented.

Original-media URLs now use Google's documented `/download/drive/v3/` route (`useMediaDownloadService=true`); metadata/listing retain `/drive/v3/`. A direct comparison reproduced old-route 403 while the corrected route matched the same original's full bytes/MD5, followed by the complete catalogue proof above. The owner chose to retain API keys; no service account or other credential model was enabled. Earlier failed attempts, the limits of pacing alone and R2 readback recovery are recorded in the evidence. Automated-query blocks remain terminal; no HTML fallback or network rotation is supported.

For an interrupted import or retryable failed ZIP, reuse that run UUID. Queueing and subsequent verification are also available:

```powershell
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --queue
php artisan queue:work --queue=drive-proof --tries=1 --timeout=0 --stop-when-empty
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --verify
php artisan snappik:drive-proof --owner=$proofOwnerId --resume=$proofRunId --cleanup
```

These commands refuse production execution and scope runs to the specified persisted local test user. The worker claims each eligible attempt once; it is a prototype, with no public gallery route, production access policy or crash lease. A failed input aborts/deletes the whole attempt. A cleanup failure becomes `cleanup_required` and needs operator investigation; it cannot be retried as a ready archive. Use the provider console to investigate orphaned multipart uploads if the process was killed. The product sweeper, durable cleanup retries and crash recovery remain TASK-008. Proof objects have a 24-hour application expiry, but this prototype has no automatic cleanup schedule: explicitly run `--cleanup` and configure the private test bucket's temporary-object/incomplete-upload lifecycle backstop.

The installed Windows libzip strict `CHECKCONS` checker rejects the large zero-header ZIP64 layout; normal libzip extraction with complete CRC/MD5 validation and Python's independent CRC reader passed. Compatibility across actual client extractors remains a gate for delivery productization; do not disable ZIP64 or split the archive to hide it.

R2 verification reads the same object in contiguous ranges of at most 8 MiB, pinned by `If-Match` to the HEAD ETag. Response status, Content-Range, length and ETag must match before bytes are accepted. Each bounded response completes through the SDK's normal transport. Transient range failures have a three-attempt budget; malformed or changed-object responses stop verification. Each request has a 300-second deadline, without imposing a total-object transfer deadline. These internal requests do not create multiple ZIP files.

## Boundaries and security

- `/` renders the public shell. `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` and `/email/verify` provide functional account flows. Login/mail requests are throttled; recovery responses are generic. The trial starts once, for exactly 168 hours, on successful signed email verification. Password reset tokens expire in 60 minutes and can be consumed once, including concurrent requests.
- `/dashboard` requires a verified photographer session and shows active/expired/suspended status; `/admin` additionally checks the current persisted admin role. Registration cannot assign privilege, verification, suspension or entitlement fields. No default admin or credentials are shipped.
- `/g/{capability}` fails closed with 404 until scoped capability authorization is implemented. It exposes no gallery data.
- `/_foundation/{public|auth|photographer|admin|client}` provides local/testing-only synthetic component previews. Production always returns 404, including when routes are cached. These are preview surfaces, not an authentication bypass to data.
- The web middleware retains Laravel's cookie encryption, session authentication, CSRF and request-forgery protection. Session cookies are HttpOnly/SameSite=Lax, database session payloads are encrypted, and production forces Secure cookies and disables debug. Configure HTTPS and trusted proxies explicitly before release; no wildcard proxy trust is enabled.
- Built assets use a nonce-based script CSP, frame denial, no-referrer, nosniff, and private/no-store responses. The before-paint theme initializer shares its controller with React. Vite hot reload uses its development CSP exception. No SSR service is enabled.
- `AUTH_MAILER=array` keeps verification/reset messages in memory during local development and tests. Account mail rejects log/composite failover transports and does not log transport exceptions or raw links. Configure an authorized SMTP sender with `AUTH_MAILER=smtp` and the existing `MAIL_*` SMTP settings when real delivery is authorized. `APP_URL` must be the canonical reachable origin, because account links are pinned to it. Real SMTP delivery has not been verified.
- `AccountPolicy`, `TenantResourcePolicy` and `GalleryGate` own central account/ownership/module decisions. Expiry preserves own metadata/results/billing and valid client delivery while blocking writes; suspension allows status/billing only and closes client access/checkout. Future project/media/order endpoints must apply these boundaries when implemented. The existing local proof importer/build/verifier now reject suspended owners before provider work. Midtrans and full project/gallery/billing workflows remain later work.
- Self-hosted DM Sans and Raleway assets come from locked Fontsource packages under SIL OFL 1.1; licenses ship in `public/licenses/`. Photo pixels have no theme filter. Motion respects reduced-motion preferences at the root and on custom interactions.

Do not publish this bootstrap as a working product. Publication, production configuration and live transactions require the separate release gate and owner authorization.

## Owner administration

Register and verify a dedicated owner account, then run the following only from the trusted server operator console, replacing the synthetic example address:

```powershell
php artisan snappik:provision-admin owner@example.test
```

The command requires an existing verified, unsuspended account, accepts no password, revokes its database sessions and records an idempotent provisioning audit. Sign in again to use `/admin`. No actual owner account was provisioned during implementation. Suspension/reactivation has an audited admin-only application action; its operator screens arrive in TASK-012. This is not a manual payment/entitlement grant path. See [TASK-003 evidence](docs/planning/TASK-003_EVIDENCE.md) for verification and remaining boundaries.

## Documentation

- [Index and approval record](docs/index.md)
- [PRD](docs/product/PRD.md) — behavior and scope
- [Design System](docs/design/DESIGN_SYSTEM.md) — tokens, components and motion
- [Architecture](docs/engineering/ARCHITECTURE.md) — technical contracts
- [Project structure](docs/engineering/PROJECT_STRUCTURE.md) — existing and planned paths
- [Backlog](docs/planning/BACKLOG.md) — sequencing and evidence
- [AGENTS.md](AGENTS.md) — workflow safeguards
