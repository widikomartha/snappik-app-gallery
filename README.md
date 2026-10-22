# SnapPik

SnapPik is a subscription application for photographer photo selection and finished-photo delivery. TASK-001 establishes the application foundation; TASK-003 adds authentication, one trial, roles and shared access policy. TASK-004 implements project configuration, separate module gates, capability management and background catalogue revision publication. TASK-005 adds client photo browsing, gated previews, shared autosave, review, confirmation and photographer reopening. TASK-006 adds progress/history/export; TASK-007 adds finished-gallery browsing and protected individual original downloads. TASK-008 adds complete background ZIP jobs, protected downloads and recovery/cleanup. TASK-002's Drive-to-R2 prototype verified one complete 384-photo ZIP with independent CRC/MD5 checks and cleanup; a real resource-key fixture remains pending, so TASK-004–TASK-008 acceptance also remains open. Billing workflows remain in later [backlog tasks](docs/planning/BACKLOG.md).

## Current state

Laravel 13, PHP 8.4+, React 19, TypeScript, Inertia 3, Vite 8, Motion 14 and PostgreSQL are configured at the repository root. Both package managers have real lockfiles. The skeleton was generated from official `laravel/laravel` v13.11.0, with the official Inertia adapter added separately to keep authentication workflows in TASK-003.

The owner requested TASK-001–TASK-007 on October 22, 2026, and configured and authorized the recorded TASK-002 provider smoke testing. TASK-004–TASK-008 acceptance remains dependent on TASK-002's real resource-key fixture. The owner requested TASK-008 on October 23, 2026. Final approval of the entire documentation package remains pending in the [index](docs/index.md). Provider test access does not imply production deployment or complete real-provider product acceptance.

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

Open [local SnapPik](http://127.0.0.1:8000). For frontend hot reload, run `npm run dev` in a second terminal while Artisan serves PHP. Keep the Vite development server local. Migrations include database queue/cache/session, isolated TASK-002 proof staging, account/access, project/catalogue, selection history, mutation receipts and delivery archives. No user is seeded automatically. The catalogue worker command is below; the ZIP worker and maintenance schedule are described below.

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
npm run test:browser:projects
npm run test:browser:selection
npm run test:browser:progress
npm run test:browser:delivery
npm run test:browser:archives
```

Playwright starts a local Artisan server when needed. It uses the development `.env` database and synthetic preview pages, without creating accounts. Do not run it against a production server. Screenshots are generated under ignored `test-results/`. CI installs locked dependencies, uses an isolated PostgreSQL 18 service with synthetic credentials, and runs the same checks without deployment or external-service secrets.

The separate `test:browser:auth` command starts a fresh loopback server on port 18763 using `.env.testing`, forces `DB_DATABASE=snappik_test`, database sessions/cache and in-memory mail, and exercises synthetic registration/resend/logout/login. Run it after PHPUnit, not concurrently with database-refreshing tests. It leaves synthetic test accounts in that disposable database until the next test refresh. No existing server is reused. The ordinary browser suite skips that write scenario; it still checks account form layout and invalid-login/recovery feedback. PostgreSQL verification/reset races use separate PHP processes in the backend suite.

The initial Windows verification used portable PHP, Composer, PostgreSQL, and Chromium under ignored `.local-tools/`; these are machine-local tools, not shipped dependencies. See [TASK-001 evidence](docs/planning/TASK-001_EVIDENCE.md) for exact runtime versions, commands, outcomes and limits.

## Project configuration and catalogue imports

Sign in with a verified photographer account, open **Kelola proyek**, and create a project with its name, positive client choice allowance and public selection Drive folder. WhatsApp contact, passwords and module deadlines are optional. The delivery folder can be added later and must use a separate source tree. A trial permits three unarchived projects including drafts; Pro adds no project/photo/byte quota. The UI explains public Drive source privacy independently of SnapPik passwords.

Apply migrations and run the product metadata worker in a separate local terminal:

```powershell
php artisan migrate --no-interaction
php artisan queue:work catalogues --queue=catalogues --timeout=0 --tries=0
```

This worker uses the existing restricted `GOOGLE_DRIVE_API_KEY` and paced official Drive adapter. Its named connection deliberately shares the domain PostgreSQL database. It stores metadata only, traverses all descendant pages and retains encrypted checkpoints across queue redelivery. Provider errors become visible failed imports; **Segarkan** requests a fresh attempt. It never downloads originals, writes to Drive or uses R2. New module publication requires a complete catalogue; a successful refresh swaps an already published module's revision atomically. A failed refresh retains its old catalogue and choices with stale status. Excluded files/shortcuts and missing choices have complete paginated diagnostics. The project page polls persisted import status without resetting unsaved form fields.

Each module has separate publication/password/deadline settings. Archive closes links while preserving all records; restore rechecks access/trial slots. Capability rotation closes the old link. `/g/{capability}` provides gated module status/counts and password entry, with links into selection and finished galleries. The finished gallery includes TASK-008 ZIP status and downloads. [TASK-004 evidence](docs/planning/TASK-004_EVIDENCE.md) records its synthetic checks; final acceptance is pending TASK-002's real resource-key fixture.

`npm run test:browser:projects` starts a separate loopback server on port 18764, forces `snappik_test`, uses database sessions and synthetic accounts, and consumes real database queue jobs with a test-only synthetic Drive binding. Run it after PHPUnit and separately from the auth suite; never run either while another test refreshes the database. It leaves synthetic records until the next PHPUnit refresh. No external provider requests or real messages are made by these browser fixtures. Review screenshots mask source/share fields.

## Client selection

Apply the additive migration with `php artisan migrate --no-interaction`. Open a published selection module from its client page, enter its optional password, then choose photos across folders. The grid and review paginate at 36 photos; the folder navigator paginates at 20 records. These are page sizes, not content quotas. Revisions imported before TASK-005 retain all-photo browsing; the photographer must explicitly refresh the catalogue once to populate stable folder-parent identities for folder navigation.

Thumbnail requests obtain fresh official Drive metadata and proxy supported Google image hosts without exposing provider links. Enlargement streams the verified original through the same project/module gate. Provider failures offer explicit retry. Finished-photo attachment/range downloads use the separate TASK-007 delivery routes described below. No permanent photo library is created.

Choices belong to one shared project set. The UI distinguishes saving, acknowledged, failed/uncertain and conflicting writes. A lost acknowledgement retains its operation ID for explicit retry; no offline queue sends changes automatically. Reload or **Periksa pilihan di server** reads canonical choices. Review can confirm one through the configured allowance and locks an immutable snapshot. Stale reviews must reload before confirming. Expired accounts retain read-only choices; module deadlines, archive, password changes, suspension and rotated capabilities deny new requests.

The photographer project page shows saved draft/locked counts and the first 20 choice records. **Buka kembali pemilihan** requires current entitlement, expected selection version/epoch and a still-valid deadline. It retains choices and earlier confirmation history while creating a new editing epoch. Complete progress/history/export screens are described below.

`npm run test:browser:selection` starts a fresh loopback server on port 18765 using `snappik_test`, synthetic accounts/queue imports and intercepted synthetic browser images. Backend tests separately verify preview proxy bytes, URL restrictions and gate failures with synthetic provider transports. Run this suite after PHPUnit and separately from the auth/project browser suites; all use the same disposable database. [TASK-005 evidence](docs/planning/TASK-005_EVIDENCE.md) records checks and remaining prerequisites. No real-provider preview smoke transfer was performed for TASK-005.

## Photographer progress and filename copying

Apply the additive acknowledgement-time migration with `php artisan migrate --no-interaction`. The photographer dashboard and project list now show selection counts/status, separate publication/closure/expiry context and the last recorded selection update in WITA. **Lihat hasil dan salin nama berkas** opens the full paginated draft/current result and immutable confirmation history. A reopened selection is no longer currently confirmed; earlier snapshots and unavailable original records remain readable. Expired accounts can view/copy their own results without gaining edit/reopen rights.

Progress refreshes every 15 seconds while the page is visible, or with **Periksa progres / Periksa hasil terbaru**. A failed request retains the last acknowledged data with a stale warning. Failed catalogue imports have a separate warning because availability comes from published metadata, rather than a live source check.

**Siapkan daftar nama berkas** loads the complete saved list across all pages; **Salin nama berkas** then copies it. Duplicate names use folder paths, with file IDs when paths also repeat. Backslashes and CR/LF are escaped to retain one entry per line. Clipboard denial leaves the labeled textarea selected for manual copying. Copy never confirms or changes a selection. Current exports check the displayed version/revision; if choices changed, refresh before preparing again. Older reopen/deleted-choice times were not recorded, so migration backfill uses only known choice/confirmation times.

`npm run test:browser:progress` uses a fresh loopback server on port 18766, the guarded `snappik_test` database and CLI-only synthetic fixtures. Run it after PHPUnit and separately from the other database-writing browser suites. It makes no provider requests or real messages. [TASK-006 evidence](docs/planning/TASK-006_EVIDENCE.md) records checks and the open prerequisite gate.

## Finished galleries and individual downloads

Use the project's separate **Hasil akhir** public Drive source, complete its catalogue import and publish that module. Clients open **Buka hasil akhir** from the shared project page after entering its independent password when configured. The gallery provides paginated folders/photos, filenames, enlarged originals, previous/next/Escape controls and preview retry. The 36-photo/20-folder response windows are page sizes, not content quotas. Legacy imports need an explicit photographer refresh for folder identities.

**Unduh berkas asli** streams the published JPEG/PNG through SnapPik as a native browser attachment. Safe filenames retain their extension/case, with unsafe characters/device names sanitized. The browser reports transfer completion. Provider failures before response output appear as a clear text error in a new tab; they are never saved as a JPEG/PNG attachment. Interrupted or late integrity failures leave an incomplete transfer for explicit retry. A valid delivery remains readable/downloadable after ordinary account expiry. Its own deadline, password generation, capability rotation, archive, suspension and publication still gate every new request, including HEAD and Range.

Single byte ranges support closed/open-ended/suffix forms and strong If-Range matching. Unsatisfiable supported ranges return 416; malformed/unsupported/multipart ranges and unmatched If-Range validators fall back to the full original. Downloads check fresh Drive permission/metadata and response type/length; full originals verify MD5 and partial originals verify boundaries/length with metadata checked again at EOF. There is no permanent originals cache. No migration or new production environment setting is required for TASK-007. Download All uses the complete ZIP job described below.

`npm run test:browser:delivery` starts a dedicated loopback PHP server on port 18767 against `snappik_test`. Its CLI-only test router injects synthetic upstream HTTP responses behind the real Drive adapter for both images and attachments; no external provider request is made. Run `php artisan migrate --env=testing --no-interaction` after PHPUnit teardown and before the browser suite. Run database-writing browser suites sequentially. [TASK-007 evidence](docs/planning/TASK-007_EVIDENCE.md) records checks and outstanding real-provider/deployment gates.

## Complete delivery ZIP jobs

Apply migrations, then run a dedicated worker and the scheduler in separate local terminals:

```powershell
php artisan migrate --no-interaction
php artisan queue:work archives --queue=archives --timeout=0 --tries=0
php artisan schedule:work
```

The worker uses the same restricted Drive API key and private R2 archive bucket configured below. It snapshots every supported photo in the published delivery revision, including subfolders and all UI pages, into an immutable encrypted manifest. **Siapkan ZIP semua foto** creates or reattaches to one persistent job. Closing the tab does not cancel it. The finished gallery restores status and polls while visible, showing measured file progress, failure, readiness and expiry. Repeated requests reuse active/valid ready output; failed or expired output can be explicitly recreated with a fresh attempt key. A new revision or gate generation never reuses an older ZIP. Ordinary subscription expiry permits valid ZIP creation/download.

**Unduh ZIP lengkap** downloads one private ZIP through SnapPik, with safe attachment headers and per-request authorization for GET, HEAD and Range. It never redirects to Drive or splits the ZIP. Source identity/permission/version/length failures fail the whole attempt. ZIP64 handles large byte offsets and entry counts; central-directory records spill to temporary disk after 1 MiB. Multipart buffers and bounded private read ranges prevent whole-object RAM buffering. Native provider limits remain explicit failures, without a photo/project byte or count quota.

Ready output expires exactly 24 hours after readiness, using server time even if deletion is delayed. Keep the scheduler running: every minute it fails abandoned leased attempts after checking their process lock, expires old output and retries exact-attempt object/multipart cleanup. Run maintenance manually with `php artisan snappik:archives-maintain`. A killed worker becomes a visible failure after its 15-minute lease expires and maintenance runs; a live slow worker retains its session lock. Recreate the failed ZIP explicitly. Production requires supervised workers/scheduler, private bucket policy, permissions to list/abort multipart uploads, and adequate temporary disk. A deployment lifecycle rule is an additional backstop, not the access-control clock. Maintenance only targets persisted `archives/{job}/{attempt}.zip` identities, never backup prefixes.

`npm run test:browser:archives` uses a dedicated loopback server on port 18768, guarded `snappik_test`, real queue consumption, and synthetic HTTP/disk provider bindings shared with the delivery browser fixture. Run it after PHPUnit teardown/migration and sequentially with other database-writing browser suites. [TASK-008 evidence](docs/planning/TASK-008_EVIDENCE.md) records tests and open real-provider/deployment acceptance gates.

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

These commands refuse production execution and scope runs to the specified persisted local test user. The worker claims each eligible attempt once; it is a prototype, with no public gallery route, production access policy or crash lease. A failed input aborts/deletes the whole attempt. A cleanup failure becomes `cleanup_required` and needs operator investigation; it cannot be retried as a ready archive. Use the provider console to investigate orphaned multipart uploads if the process was killed. TASK-008 implements a separate product sweeper, durable cleanup retries and crash recovery for product archive attempts; it does not adopt these prototype proof objects. Proof objects have a 24-hour application expiry, but this prototype has no automatic cleanup schedule: explicitly run `--cleanup` and configure the private test bucket's temporary-object/incomplete-upload lifecycle backstop.

The installed Windows libzip strict `CHECKCONS` checker rejects the large zero-header ZIP64 layout; normal libzip extraction with complete CRC/MD5 validation and Python's independent CRC reader passed. Compatibility across actual client extractors remains a gate for delivery productization; do not disable ZIP64 or split the archive to hide it.

R2 verification reads the same object in contiguous ranges of at most 8 MiB, pinned by `If-Match` to the HEAD ETag. Response status, Content-Range, length and ETag must match before bytes are accepted. Each bounded response completes through the SDK's normal transport. Transient range failures have a three-attempt budget; malformed or changed-object responses stop verification. Each request has a 300-second deadline, without imposing a total-object transfer deadline. These internal requests do not create multiple ZIP files.

## Boundaries and security

- `/` renders the public shell. `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` and `/email/verify` provide functional account flows. Login/mail requests are throttled; recovery responses are generic. The trial starts once, for exactly 168 hours, on successful signed email verification. Password reset tokens expire in 60 minutes and can be consumed once, including concurrent requests.
- `/dashboard` requires a verified photographer session and shows active/expired/suspended status; `/admin` additionally checks the current persisted admin role. Registration cannot assign privilege, verification, suspension or entitlement fields. No default admin or credentials are shipped.
- `/projects` and its configuration/diagnostic/command/reopen routes require verified photographer ownership and current access decisions. `/g/{capability}` resolves an encrypted capability by its digest and exposes only published, unarchived, unsuspended, unexpired modules; separate password generations gate status, selection data, mutations, confirmation and previews. New media requests recheck gates even if a previous URL was cached.
- `/_foundation/{public|auth|photographer|admin|client}` provides local/testing-only synthetic component previews. Production always returns 404, including when routes are cached. These are preview surfaces, not an authentication bypass to data.
- The web middleware retains Laravel's cookie encryption, session authentication, CSRF and request-forgery protection. Session cookies are HttpOnly/SameSite=Lax, database session payloads are encrypted, and production forces Secure cookies and disables debug. Configure HTTPS and trusted proxies explicitly before release; no wildcard proxy trust is enabled.
- Built assets use a nonce-based script CSP, frame denial, no-referrer, nosniff, and private/no-store responses. The before-paint theme initializer shares its controller with React. Vite hot reload uses its development CSP exception. No SSR service is enabled.
- `AUTH_MAILER=array` keeps verification/reset messages in memory during local development and tests. Account mail rejects log/composite failover transports and does not log transport exceptions or raw links. Configure an authorized SMTP sender with `AUTH_MAILER=smtp` and the existing `MAIL_*` SMTP settings when real delivery is authorized. `APP_URL` must be the canonical reachable origin, because account links are pinned to it. Real SMTP delivery has not been verified.
- `AccountPolicy`, `TenantResourcePolicy`, `GalleryGate` and persisted selection/delivery resolution own account/ownership/module decisions. Expiry preserves own metadata/results/billing and eligible client reads/downloads while blocking new choices/confirmation/reopening; suspension allows status/billing only and closes client access/checkout. Project/configuration/import, capability-status, selection/preview and delivery/media routes apply these decisions. Future order/ZIP routes must also apply them. Local proof importer/build/verifier reject suspended owners before provider work. Midtrans and product ZIP remain later work.
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
