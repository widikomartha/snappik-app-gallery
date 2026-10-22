# TASK-009 — Owner voucher management evidence

Date: October 23, 2026 (Asia/Makassar). Status: **COMPLETE**. The owner's TASK-009 request authorizes this implementation and local/synthetic checks. Its TASK-003 prerequisite is complete. Final package approval, production publication and live payment authorization remain separate.

## Delivered behavior

Verified, unsuspended platform admins can create, edit, deactivate and reactivate vouchers at `/admin/vouchers`, reached from `/admin`. Codes normalize by trim/uppercase and remain permanent unique identities. Each save appends immutable terms with an identifiable version number/ID, author and creation time; the root pointer and audited reason commit atomically. Earlier terms remain readable. PostgreSQL rejects code renaming/deletion, version changes/deletion, mismatched root/version references and duplicate redemptions. Stale forms cannot overwrite newer terms. Authorization is checked on every route and again against the locked persisted actor during mutation.

Forms validate whole percentages 1–99, positive whole-IDR fixed discounts, ordered start/end instants, positive optional total/per-account limits and boolean enabled state. Empty limits mean unlimited uses. WITA date inputs normalize to UTC before storage, retaining their exact instants across subsequent edits. Codes cannot be reused to reset accounting. Owner reports paginate versions, reservation states, successful usage and audit reasons. Photographers, unverified/suspended admins and anonymous visitors cannot inspect or administer them.

Capacity checks hold the root lock and count held reservations plus successful usage across every version. Lowering either limit below occupied capacity is rejected; per-account checks consider the largest occupied count of any account. Released reservations do not occupy capacity. Deactivation creates new disabled terms and preserves previous pending-order contracts. All future reservation/payment writers must use the same root lock after their documented account/order locks.

`VoucherQuote` returns an immutable IDR base/discount/payable result with root/version identities. Percentage arithmetic rounds down and avoids multiplication overflow. Date eligibility is start-inclusive/end-exclusive. Nonpositive totals and totals below the supplied positive channel minimum are rejected, without clipping or free-order grants. This helper evaluates terms only; checkout must recheck the active root and reserve capacity. Settlement must honor an order's original snapshot instead of recomputing current eligibility.

Reservation/redemption tables exist for reporting, with a unique stable order UUID per reservation and one immutable redemption per reservation. TASK-010 must attach order foreign keys when orders exist; TASK-010/TASK-011 own production accounting writes and payment verification. Only guarded test fixtures populate usage today. No order, payment adapter, manual grant, automatic reservation release or public accounting-write endpoint is introduced.

## Verification performed

Commands used ignored portable PHP 8.4.27 under `.local-tools/php`. Browser execution added that directory to PATH and used Chromium under `.local-tools/browsers`. Tests use isolated PostgreSQL `snappik_test`, with database-writing browser suites run after PHPUnit and test-schema migration.

| Command/check | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | **212 passed, 1 opt-in large-ZIP test skipped (213 total), 2,317 assertions**, 98 MiB reported memory |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter VoucherManagementTest --stop-on-failure` | **12 passed / 224 assertions**, including exact WITA/UTC round trips and explicit validation redirects |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter VoucherConcurrencyTest --stop-on-failure` | **3 passed / 22 assertions**, separate PHP processes with real PostgreSQL contention |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Repository PHP formatting passed |
| `npm run build` | TypeScript check and production Vite build passed |
| `npm test` | **5 passed** |
| `npm run test:browser:vouchers -- --max-failures=1` | **4 passed**, 360px/1440px and light/dark; narrow runs use reduced motion |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive voucher schema applied to local development PostgreSQL |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Test schema restored after full-suite teardown |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Package metadata valid |
| `node .local-tools/check-links.mjs`; `git diff --check` | Local Markdown file links and patch whitespace passed |

Feature checks cover invalid amounts/limits/dates, normalized duplicates, immutable code/version history, create/edit/deactivate/reactivate audits, stale versions, released-versus-held/used accounting, cross-version and per-account capacity, report isolation/pagination and every route's role boundary. Direct SQL checks verify protected history, normalized uniqueness, root/version consistency and one redemption per reservation. The immutable quote tests verify IDR 29,000 with 10% gives IDR 2,900 discount and IDR 26,100 payable; IDR 29,009 still gives IDR 2,900; a fixed IDR 4,999 discount gives IDR 24,001. A 99% quote at `PHP_INT_MAX` stays integer-exact. Minimum equality succeeds while below-minimum, zero and negative outcomes fail.

Separate-process checks prove concurrent normalized creates produce one root/version/audit; competing editors cannot both replace the same version; and a limit edit waiting on a root lock rechecks a newly committed hold before saving. The last check observes an actual PostgreSQL `Lock` wait before committing the synthetic hold. Checkout last-slot reservation and verified-payment races remain TASK-010/TASK-011.

Browser checks use real login/session/CSRF/application routes with a synthetic owner. They create a normalized code, display synthetic held/used/released records, preserve entered values after a capacity error and theme switching, publish fixed-IDR terms, reject/recover a stale edit, deactivate the code, inspect retained versions/audits, and reject a duplicate code on the correct create form. Reload retains the final canonical version. Both widths have no page-level horizontal overflow and no JavaScript page errors. Screenshots under ignored `test-results/voucher-*.png` show validation, current terms and history; narrow dark validation and wide light current terms were visually inspected.

## Corrections and boundaries

Initial sandbox runs could not connect to local PostgreSQL or format files, and Vitest could not rename its temporary cache. Authorized executions outside that Windows sandbox passed. Test-development corrections included an idempotent PostgreSQL function definition for `migrate:fresh`, a test helper that accidentally removed the intended code-tampering input, a PHPUnit-reserved helper name and normalized datetime values for Playwright. Browser testing found and corrected real WITA timestamp drift during Eloquent persistence and validation returning to an unrelated session previous URL under the no-referrer policy. Dedicated backend and browser regressions now cover both defects. Failed attempts are not counted as passing evidence.

No real administrator was provisioned, mail sent, external provider transfer made, payment initiated or deployment performed. TASK-009 does not depend on TASK-002's real resource-key fixture; that separate gallery acceptance gate remains open. Product-wide accessibility/appearance integration and release checks remain TASK-013–TASK-015. Hosted CI execution is not claimed.
