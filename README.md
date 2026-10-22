# SnapPik

SnapPik is a subscription application for photographer photo selection and finished-photo delivery. TASK-001 establishes the application foundation; product workflows and provider integrations remain in later [backlog tasks](docs/planning/BACKLOG.md).

## Current state

Laravel 13, PHP 8.4+, React 19, TypeScript, Inertia 3, Vite 8, Motion 14 and PostgreSQL are configured at the repository root. Both package managers have real lockfiles. The skeleton was generated from official `laravel/laravel` v13.11.0, with the official Inertia adapter added separately to keep authentication workflows in TASK-003.

The owner requested TASK-001 on October 22, 2026. Final approval of the entire documentation package remains pending in the [index](docs/index.md). No provider account, production deployment, or finished gallery is implied by this bootstrap.

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

Open [local SnapPik](http://127.0.0.1:8000). For frontend hot reload, run `npm run dev` in a second terminal while Artisan serves PHP. Keep the Vite development server local. The default database queue/cache/session tables are migrated; domain workers and schedules arrive with their owning tasks. No user is seeded automatically.

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

## Boundaries and security

- `/` and `/login` render public/authentication shells. Login/registration/reset/verification actions are deliberately absent until TASK-003; the UI explains their availability.
- `/dashboard` requires a verified session; `/admin` additionally checks the persisted admin role through a server-side Gate. Role is excluded from user mass assignment. No default admin, provisioning command, or credentials exist.
- `/g/{capability}` fails closed with 404 until scoped capability authorization is implemented. It exposes no gallery data.
- `/_foundation/{public|auth|photographer|admin|client}` provides local/testing-only synthetic component previews. Production always returns 404, including when routes are cached. These are preview surfaces, not an authentication bypass to data.
- The web middleware retains Laravel's cookie encryption, session authentication, CSRF and request-forgery protection. Session cookies are HttpOnly/SameSite=Lax, database session payloads are encrypted, and production forces Secure cookies and disables debug. Configure HTTPS and trusted proxies explicitly before release; no wildcard proxy trust is enabled.
- Built assets use a nonce-based script CSP, frame denial, no-referrer, nosniff, and private/no-store responses. The before-paint theme initializer shares its controller with React. Vite hot reload uses its development CSP exception. No SSR service is enabled.
- Mail defaults to the in-memory array transport; no messages or password links are sent or logged. Local storage is private and has no automatic file-serving route. Drive, Midtrans, R2, tenant policies, suspension, entitlement enforcement and login throttles are later work, not connected services.
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
