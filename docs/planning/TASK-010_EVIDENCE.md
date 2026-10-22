# TASK-010 — Server-priced checkout and voucher reservation evidence

Date: October 23, 2026 (Asia/Makassar). Status: **IMPLEMENTED / REAL SANDBOX QRIS ACCEPTANCE PENDING**. TASK-003 and TASK-009 are complete. The owner explicitly requested TASK-010 and confirmed that a Midtrans sandbox merchant is not configured, choosing synthetic provider verification. This does not authorize live payments, real messages or production publication; final document approval remains separate.

## Delivered behavior

Verified photographers can open `/billing` from the dashboard, review Pro's IDR 29,000 / 30-day offer, apply one optional normalized voucher, inspect exact whole-IDR base/discount/payable and continue to sandbox checkout. Initial purchases and renewals use the same path; expired accounts remain eligible. Suspension permits own billing history but denies quote/create/resume/open. Every order request scopes persisted ownership; admins have no photographer-order bypass.

The active Pro version is stored in PostgreSQL, with immutable price/duration history. A quote is encrypted/authenticated, account-bound and valid for 15 minutes for a new purchase. It binds plan/voucher identities, merchant/environment, channel minimum and all monetary/duration terms. Checkout accepts only the quote and UUID operation key. It locks persisted user/account access, then the existing order for replay or the current plan/voucher root for a new purchase, rechecking dates, current versions, integer totals and total/per-account held-plus-redeemed capacity. It inserts the order and optional held reservation in one transaction. Quotes do not reserve capacity.

The same account/key/terms returns the original order even after quote expiry, voucher deactivation or future price changes. A different quote under that key fails. Stale new quotes require a fresh price review. PostgreSQL constraints enforce order/key uniqueness, exact positive arithmetic, sandbox identity and account/voucher/version reservation ownership; triggers preserve purchase and plan-version history. TASK-009 usage fixtures now create matching synthetic orders before historical reservation rows, keeping the new foreign key effective in regression tests.

Provider creation uses `sp-{persisted order UUID}` and the persisted payable amount. A PostgreSQL advisory lock prevents overlapping creation; durable `creating` state precedes network work. Only QRIS is enabled. Snap token/URL are encrypted at rest and excluded from page props. A scoped native redirect rechecks ownership, suspension, merchant and opening window before revealing the saved sandbox URL. No Server Key or browser-provided destination reaches the page.

Timeouts, malformed/duplicate responses and crashes remain unknown and keep their holds. Recovery checks authenticated Core status before any repeat POST. Missing Core status can coexist with a Snap page: saved pages are reused; an unstored page can be recreated only under the same provider order identity within the original opening window. Status mismatches never permit creation. Known terminal observations enter review without grants/redemptions/release. Local 24-hour opening-window expiry and browser abandonment never establish nonpayment or release capacity. The application has no production payment endpoint, automatic replacement order, auto-debit or customer surcharge.

The browser retains its signed quote/operation key in session storage, with an in-memory fallback when storage is unavailable. A lost reply can replay that operation; canonical order history remains available after reopening. The order screen distinguishes uncertain, pending and review states without declaring payment success. Redirect/query parameters do not grant access. Verified settlement/grants, callback durability, reconciliation scheduling and provider-confirmed reservation release remain TASK-011.

## Provider contract sources

Implementation was checked against official Midtrans [Snap request parameters](https://docs.midtrans.com/reference/request-body-json-parameter), [authentication headers](https://docs.midtrans.com/reference/request-headers), [expiry objects](https://docs.midtrans.com/reference/json-objects), [Core transaction status](https://docs.midtrans.com/reference/get-transaction-status) and [Snap recovery behavior](https://docs.midtrans.com/docs/snap-advanced-feature). The adapter uses fixed sandbox hosts, bounded timeouts, disabled redirects, safe error classes and no automatic POST retries. It validates exact order/merchant/currency/amount/QRIS identity on status. Same-order token recreation follows the documented pre-payment behavior; no suffixed replacement identity is introduced.

## Verification performed

Commands used portable PHP 8.4.27 under ignored `.local-tools/php`, Node/npm and Chromium under `.local-tools/browsers`. Database tests use guarded PostgreSQL `snappik_test`; browser suites run sequentially after backend teardown and schema migration. Synthetic transports never contact Midtrans or send mail.

| Command/check | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | **234 passed / 1 opt-in large-ZIP test skipped (235 total), 2,540 assertions**, 98 MiB reported memory |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter CheckoutConcurrencyTest --stop-on-failure` | **4 passed / 39 assertions** using independent PHP processes and observed PostgreSQL lock waits |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter MidtransCheckoutTest --stop-on-failure` | **8 passed / 65 assertions** after the final expiry-payload review |
| `npm run build` | TypeScript and production Vite build passed |
| `npm test` | **5 passed** |
| `npm run test:browser:checkout -- --max-failures=1` | **4 passed**, 360px/1440px × light/dark; narrow runs use reduced motion |
| `npm run test:browser:vouchers -- --max-failures=1` | **4 passed**, owner create/version/usage/deactivation regression with the new order foreign key |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Test schema restored, including checkout/order/reservation foreign-key migration |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive checkout migration applied to local development PostgreSQL |
| `.\.local-tools\php\php.exe vendor/bin/pint --test`; Composer `validate --strict` | PHP formatting and Composer metadata passed |
| `node .local-tools/check-links.mjs`; `git diff --check` | Local Markdown file links and patch whitespace passed |

The 22 new backend tests cover exact quote/order/provider snapshots; monetary/owner/duration tampering; old-key replay and changed-input rejection; stale price/voucher/quote refusal; future-price preservation; positive/minimum totals; inactive/future/expired/exhausted vouchers; pending capacity across versions; tenant/role/suspension/expiry behavior; encrypted private provider state; immutable SQL history and composite reservation identity. Adapter tests cover create timeout → status → same-identity retry, interrupted `creating` recovery, stored-page reuse on Core 404, duplicate/malformed/untrusted URL rejection, merchant/amount/currency/order/channel/state mismatch, terminal review and local-expiry hold retention. No test grants paid access or consumes/releases a real reservation.

Independent-process races prove that two buyers cannot both reserve the last total slot, two orders cannot bypass one account's limit, one key produces one order/hold, and two resumers cause only one provider creation. Each worker is observed waiting on a real PostgreSQL lock before the barrier is released; activity snapshots are cleared while polling so cached statistics do not mask the wait.

Browser checks use real login/session/CSRF and application routes with a synthetic HTTP transport behind the actual sandbox adapter. They verify invalid-code feedback, exact 10% totals, quote preservation through theme switch/reload, a creation timeout plus deliberately lost browser reply, canonical one-order history, original-key replay after voucher deactivation, restored Snap opening, safe internal redirect and repeated resume. All four scenarios check no horizontal overflow or JavaScript page errors and assert one held reservation with no paid access. Screenshots under ignored `test-results/checkout-*.png` capture quote, unknown and pending states; the wide light quote and narrow dark unknown screen were visually inspected.

## Corrections and remaining gates

The initial sandboxed PHP run could not open the existing Composer bootstrap; Vitest could not rename its temporary cache. Authorized executions outside the Windows sandbox passed. Some automatic permission reviews timed out before commands launched; their allowed single retries succeeded. Test-development corrections renamed a PHPUnit-reserved helper, moved a null-error assertion to the uncertain stage, cleared PostgreSQL statistics snapshots while observing contention, accepted locale currency spacing and awaited browser navigation before capturing its URL. A provider request review removed an undocumented `start_time` field from `page_expiry`; original-start payment expiry remains explicit. Failed attempts are not counted as passing verification.

No real Midtrans credentials were configured, sandbox merchant page opened or QRIS payment simulated. The required **real sandbox QRIS checkout demonstration remains the TASK-010 completion gate**, as explicitly accepted by the owner for this implementation turn. Merchant QRIS enablement and minimum validation must be exercised when available. TASK-011 must implement verified grants/redemptions/release and durable reconciliation before the payment lifecycle is usable. TASK-012 owns future price administration. Product-wide accessibility/integration/release checks remain TASK-013–TASK-015. No live payment, real message, new external provider transfer or deployment occurred; hosted CI execution is not claimed.
