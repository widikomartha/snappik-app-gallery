# TASK-012 implementation evidence

Date: October 23, 2026 (Asia/Makassar).

Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. The owner explicitly requested TASK-012. TASK-006/TASK-008/TASK-011 acceptance remains open through the real Drive resource-key and sandbox payment gates. Final package approval remains pending in the [index](../index.md). This evidence does not authorize production publication, live payments, real messages, additional provider transfers or credential changes.

## Implemented boundary

- Owner-only overview separates current registered/trial/Pro/expired/suspended account counts, project/activity aggregates, pending order amounts, unique verified gross sandbox receipts, payment reviews, voucher holds/redemptions, failed imports/ZIPs and cleanup/queue age indicators. Revenue joins unique immutable grants to orders and never counts callbacks as purchases. Refund/review history remains visible; this is not net production revenue.
- Paginated accounts show current access state, project metadata, immutable order/access history and reasoned suspension/reactivation. A monotonic suspension version rejects stale forms under persisted user locks. Suspension changes new gallery requests immediately without altering access periods, publication or module deadlines; billing remains readable.
- Price edits compare the active Pro version under checkout's plan lock, append immutable positive whole-IDR terms with the fixed 30-day duration, and advance the new-order pointer. Existing prices/discounts/vouchers/durations remain immutable; an unreserved stale quote must be refreshed.
- Order detail shows minimized purchase terms and payment-event/review history. An investigation reason queues an actor-attributed authoritative sandbox recheck; unresolved events are shared. It cannot mark paid, edit finance, clear history or grant access from submitted values.
- Failed current imports and ZIPs support audited, idempotent retry. The action resolves the persisted owner/project/module/revision/job, checks current account rights and revision/source or delivery generation/publication/deadline gates, compares an operations version and dispatches through the existing transactional database queues. An expired account can retry an otherwise eligible delivery ZIP; imports still require mutation rights. Workers recheck gates after enqueue. Domain state, UUID-only dispatch and audit commit together; injected queue failure rolls all three back.
- Every new administrative mutation records actor/time/reason and before/after subject references. Read-only pages use explicit bounded metadata projections and fixed diagnostic remedies, excluding source URLs/resource keys, client capabilities, photo content/names, raw provider payloads/failures, checkout tokens, private object keys and credentials. Existing voucher administration/usage pages remain available from the overview.
- Existing theme/touch/focus/status primitives provide Indonesian forms and labeled records. Stale price/account input remains visible on failure until explicit reload; explicit form redirects work without Referer headers. No dependency, photo browsing console, paid content cap, arbitrary queue executor, impersonation or manual payment grant was added.

## Verification

Portable PHP is `.local-tools/php/php.exe`; browser runs prepend that directory to PATH and set `PLAYWRIGHT_BROWSERS_PATH` to `.local-tools/browsers`. Commands run at the repository root. Database tests and browser fixtures are guarded to `snappik_test`; database-writing suites run sequentially.

| Command | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter PlatformOperationsTest` | Initial 11 new feature tests passed, **208 assertions**, before two additional redirect/atomicity regressions were added |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final complete backend suite passed: **271 tests / 3,031 assertions / 1 skipped**; the skipped test is the opt-in above-4-GiB proof, not rerun for these operations changes |
| `npm run build` | TypeScript and production Vite build passed |
| `npm test` | **5 frontend tests passed** |
| `npm run test:browser:operations` | **4 passed**: 360px/1440px × light/dark, synthetic owner/provider fixtures and actual database queue consumption |
| `npm run test:browser:vouchers` | Existing owner voucher regression: **4 passed**, both widths/themes |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Test schema restored after PHPUnit, including operations versions/audit references |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive operations migration applied to local development PostgreSQL |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | PHP formatting passed |
| `node .local-tools/check-links.mjs`; `git diff --check` | Local Markdown links passed in 21 documents; patch whitespace passed |

The 13 TASK-012 feature tests cover current owner authorization and role revocation, price validation/immutability/stale quotes/forms, deterministic form redirects without a referrer, retry queue-insert rollback, immediate suspension/reactivation gates and unchanged access dates, duplicate verified-payment totals, current import resumption and idempotency, retired source/archive/expired-owner denial, ZIP generation/deadline/publication checks, post-enqueue suspension, payment recheck deduplication/no submitted financial effect, and minimized read projections. The full suite retains prior real PostgreSQL concurrency checks and provider-adapter contracts.

The browser demonstration signs in as a synthetic owner, inspects the overview, rejects a concurrent price edit while preserving input, loads the latest version and saves a new price, rejects a concurrent suspension while preserving its reason, reloads and reactivates, inspects project/access/order metadata, retries a failed import and ZIP, consumes their actual database queues with synthetic providers, and investigates a payment-review record through the actual sandbox adapter using local HTTP responses. The payment queue processes the recheck while review history remains visible. All target widths/themes verify no page-level horizontal overflow; narrow runs use reduced motion, and theme switching preserves an unsaved price form. No JavaScript page errors occurred.

Screenshots are generated under ignored `test-results/operations-{overview,account,failures,jobs,review}-{360,1440}-{light,dark}.png`. Visual inspection covers narrow dark account controls/history, narrow light failures and wide dark payment review. The screenshots contain synthetic accounts/correlation IDs only.

## Corrections and remaining gates

Initial feature-test failures were fixture expectations: gallery denials use the existing generic 404; protected account-access fixtures require explicit force-fill; a stale model needed refresh before simulating a new failure. Browser testing then exposed validation redirects relying on a missing Referer: all operations mutations now explicitly return to their own form, covered by an added regression. Stale account forms retain their original intended action/version and reason until reload. A browser fixture originally ran a payment worker before the recheck committed; it now waits for canonical event history and uses per-order synthetic responses. Final browser/backend runs passed after these corrections.

The sandbox blocked PHP formatter writes and Vitest temporary-cache renames. Authorized outside-sandbox formatter/test runs passed; failed invocations are not counted as successful verification. No provider credentials were printed or changed.

TASK-012 is not declared complete because TASK-006/TASK-008/TASK-011 prerequisite acceptance remains open. No real owner account was provisioned and no manual human-owner acceptance is inferred from the automated demonstration. Real Drive resource-key coverage, merchant QRIS/lifecycle/missed-notification acceptance, broader integrated product/security checks and production release remain their existing gates. No live charge, real message, new live-provider transfer, purchase or production publication occurred; hosted CI and production worker supervision are not claimed.
