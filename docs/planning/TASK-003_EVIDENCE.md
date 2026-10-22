# TASK-003 implementation evidence

Date: October 22, 2026 (Asia/Makassar). Scope authorized by the owner's explicit TASK-003 request. TASK-001 is complete; TASK-002's remaining resource-key fixture does not block this task. Final package approval and public release authorization remain pending in the [index](../index.md).

## Delivered boundary

- Functional Inertia account forms and server endpoints for normalized email/password registration, login/logout, signed email verification, resend, generic recovery and password reset. Public registration cannot assign role, verification, suspension or entitlement fields. PostgreSQL enforces normalized email uniqueness and allowed roles. Password validation rejects unsafe bcrypt byte lengths/NUL and requires at least 12 characters with letters/numbers for new passwords.
- `VerifyAccount` serializes verification on a persisted user lock and records one unique `account_access` trial lasting exactly 168 hours. Repeated verification, login, recovery/reset and reactivation retain that period. The timestamp equality boundary is expired. No content quotas, free-order grants or manual payment grants were added.
- Session rotation on login, invalidation/CSRF-token rotation on logout, hashed one-use 60-minute broker tokens and serialized reset consumption. Password reset rotates the remember token, deletes database sessions and invalidates old password-hash sessions on their next request. Login and account-mail endpoints have IP/identity throttles; auth mutations retain CSRF protection.
- `AccountPolicy`/`AccountAction` centralize fresh persisted account decisions. Expiry preserves read/results/billing/checkout and otherwise valid delivery but denies writes. Suspension permits account status/billing only and denies checkout/client access. Admin has no photographer-resource bypass. The dashboard exposes distinct account-state messages while project/billing screens remain future work.
- `TenantResourcePolicy`/`PhotographerOwned` re-resolve persisted ownership, return generic denial and reject unsaved owner substitution. Test-only read/write/job/media/billing probes exercise this common boundary against real persisted proof records. Future project/media/order endpoints do not yet exist and must install these policies in their owning tasks.
- `GalleryGate` is an internal decision primitive for capability, publication, archive, module deadline, password/generation and selection lock. Its persisted module/capability/session resolution remains TASK-004/005/007; the public client route still fails closed. Account expiry alone permits a valid download, while deadline/suspension/current password-generation failure denies a new request.
- Existing local/test proof import/build/readback paths now reject suspended owners before provider operations, and import/readback re-read ownership before accepting a supplied owner ID. The queued build derives its owner from the persisted run. These remain operator-driven prototypes; expiry does not impose a new product subscription requirement on the diagnostic command. Cleanup remains available to the operator.
- `snappik:provision-admin` promotes an existing verified, unsuspended dedicated owner account, accepts no password, revokes its database sessions and records one idempotent audit event. `SetAccountSuspension` transactionally checks a current admin, changes a photographer's suspension and audits only actual transitions without resetting access. Operator screens and stale-form/version handling remain TASK-012. The command was exercised only against synthetic accounts in the isolated test database.
- Account mail uses standard rendered Laravel verification/reset messages through `AccountLink`. `AUTH_MAILER=array` is the safe local default; SMTP/sendmail are configurable boundaries, while log/composite failover mailers are rejected. Verification mail failure leaves the account unverified with resend guidance. Recovery uses the same response for absent accounts, throttling and mail failure, with the broker's one-minute retry window. Raw transport exceptions are not logged. Links use configured `APP_URL`, and login resumes only an intended verification URL on that origin.

## Verification environment and commands

Used the existing ignored portable PHP 8.4.27, Composer, PostgreSQL instance and Chromium. Tests required the isolated `snappik_test` PostgreSQL database; no SQLite substitution, real mail, payment or provider transfer was used. Frontend dependencies were restored with `npm ci` from the unchanged lockfile; no dependency version was added or upgraded.

PowerShell runtime settings on this machine:

```powershell
$env:PATH = 'D:\Website\snappik-app-gallery\.local-tools\php;' + $env:PATH
$env:PLAYWRIGHT_BROWSERS_PATH = 'D:\Website\snappik-app-gallery\.local-tools\browsers'
```

| Actual command | Inspected result |
| --- | --- |
| `npm ci` | Restored 134 locked packages |
| `php artisan migrate --no-interaction` | Both additive account-access/audit migrations applied to local development PostgreSQL |
| `php artisan migrate --env=testing --no-interaction` | No pending test migrations |
| `php .local-tools/composer.phar validate --strict` | Valid manifest/lockfile |
| `php .local-tools/composer.phar lint` | Pint passed |
| `php .local-tools/composer.phar test` | 89 tests / 492 assertions passed before the final password-validation test; opt-in large-ZIP test skipped |
| `php vendor/bin/phpunit` | Final full suite: 90 passed / 501 assertions; 1 opt-in large-ZIP test skipped (91 total) |
| `php vendor/bin/phpunit --filter=AccountConcurrencyTest` | 2 tests / 15 assertions passed using separate PHP/PostgreSQL processes |
| `php vendor/bin/phpunit --filter='AuthenticationTest\|AccountConcurrencyTest'` | 14 tests / 147 assertions passed after the password-validation review |
| `npm run build` | Strict TypeScript and production Vite build passed |
| `npm test` | 2 files / 5 tests passed |
| `npm run test:browser` | 16 passed / 1 intentional skip; includes the existing 11 foundation regressions |
| `npm run test:browser:auth` | 6 passed against a fresh dedicated server/test database with array mail |
| `node .local-tools/check-links.mjs` | Local Markdown file links passed in 12 documents |
| `git diff --check` | Passed |

The standard browser suite skips synthetic registration writes. The dedicated auth configuration forces the test database, uses real encrypted database sessions/cache, pins mail to array, and does not reuse another running server. It leaves synthetic test accounts in the disposable database until the next PHPUnit refresh. Do not run it concurrently with database-refreshing tests. CI now includes this command; hosted CI has not been run.

## Inspected scenarios

Backend checks cover privilege injection, duplicate normalized email, password hashing/validation, signed-link tampering/expiry/wrong actor, no pre-verification trial, trial replay/reverification, login rotation/throttling, logout, reset expiry/replay, generic recovery, old-session rejection, malformed input and CSRF. The sandbox mail-flow test reads actual rendered messages from the array transport, follows the signed verification URL, extracts the reset link internally, consumes it and logs in with the new password. Links/tokens are never printed or written into evidence.

The PostgreSQL concurrency tests start two independent PHP workers, wait until both are ready while the parent holds the same user row lock, then release them. Concurrent verification produces one trial; concurrent reset returns one success and one rejection. Workers receive sensitive test inputs over stdin and emit only fixed result categories. Account-policy tests exercise one second before and exactly at the access deadline, suspended sign-in, retained billing, denied writes/client/media/checkout, reactivation, admin provisioning/audit and stale role/owner objects. Provider-access tests assert denial occurs before source/storage work.

Browser checks cover registration, verification resend, logout, session persistence through reload and login back to the unverified gate; invalid credentials show a named error and clear the password; recovery displays the generic message. Forms at 360px/1440px in light/dark preserve draft email during keyboard theme switching, have no horizontal overflow and raise no JavaScript page errors. Generated screenshots are ignored under `test-results/`. The 360px light registration and 1440px dark login screenshots were visually inspected. Foundation regressions also cover normal/reduced motion, contrast, first-paint appearance, blocked storage and dialog focus.

## Corrections and remaining gates

The initial local environment needed dependency restoration. Windows sandbox file-read/temp-rename/network restrictions prevented PHP/npm checks; approved execution outside that sandbox passed. The first dedicated browser port (8001) was unavailable on this host, so the test server moved to loopback port 18763. One browser assertion initially filled the old page's email during Inertia navigation; waiting for the recovery URL fixed the test synchronization. No product behavior was weakened to satisfy it.

Real SMTP reachability/inbox delivery, full project/gallery/media/order authorization integration, payment grants, trial project-slot enforcement and operator screens remain in their assigned tasks. No actual owner was promoted, credential changed, real message sent, live financial action taken or deployment performed. The unrelated opt-in large-ZIP proof was not rerun; its prior evidence and TASK-002 fixture/compatibility gates remain unchanged.
