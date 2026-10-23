# TASK-013 implementation evidence

Date: October 23, 2026 (Asia/Makassar).

Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. The owner explicitly requested TASK-013. TASK-006/TASK-008/TASK-011/TASK-012 acceptance remains open through the real Drive resource-key fixture and sandbox QRIS/lifecycle gates. Final package approval remains pending in the [index](../index.md). This work does not authorize public deployment, live payments, real messages, new provider transfers or credential changes.

## Implemented boundary

- Public onboarding explains the actual trial, Pro entitlement, Drive source/privacy, independent gallery gates, retained results and complete single-ZIP delivery. The Pro amount/duration comes from the current persisted plan version; the page identifies the current sandbox/release boundary. No hosted-upload allocation or rejected paid content quota is advertised.
- Shared photographer navigation connects projects, results and billing. Expired/suspended copy points to implemented account/history behavior. Existing owner overview/account/price/voucher/order/job flows retain their authorization and audit ownership.
- Every SnapPik shell retains the original shared before-paint appearance controller. Explicit browser preferences, system changes without an explicit choice, blocked storage, keyboard switching and native `color-scheme` use that same controller; theme changes do not key/remount the React tree. No preference API, database field or cross-device synchronization is introduced.
- Shared page fades, public reveals, native-dialog transitions and visible gallery entrance recipes use the existing Motion dependency and timing policy. Fades stay near opaque (0.96–1) to preserve readable contrast during transitions. Reduced motion removes travel/stagger. Closing a native dialog is immediate; there is no focusable exiting overlay or animation completion barrier. Focus returns to the invoker, or the main landmark when that control is unavailable. New route navigation focuses the main landmark without changing same-page drafts/polls.
- Selection/delivery keep the existing 36-photo server windows and stable file identities; only the first six tile candidates receive an entrance recipe, once when visible. Lazy images retain reserved geometry and `contain` presentation. Theme switching does not filter, invert, recolor or dim image elements.
- Status banners combine wording and decorative symbols; functional borders, wrapping actions, narrow panels and named form feedback use shared semantic tokens. Project, voucher and plan configuration warn on GET navigation to another URL and browser exit while dirty; same-page refreshes preserve drafts and form submissions remain usable.
- Safe 403/404/419/429/500/503 HTML pages use the same shell/appearance and Indonesian remedies, without exception text or resource details. Existing JSON and native media/archive responses retain their response boundaries. Error pages retain no-store/referrer/security headers and client no-index headers. Indonesian validation messages cover launch forms, including named fields and password rules.
- The integrated browser check reproduced a real password-return defect: with the no-referrer policy, native background/media GETs can replace Laravel's session previous URL. Password success could redirect to a data endpoint and leave the gallery gate apparently locked. Success and validation failure now explicitly return to the resolved project gate. Account and project validation also set their known form destinations, including after Inertia navigation. These changes do not alter authentication, module gates, entitlement or tenant scope.

## Verification environment and commands

Portable PHP is `.local-tools/php/php.exe`; browser commands prepend that directory to PATH and use `PLAYWRIGHT_BROWSERS_PATH=.local-tools/browsers`. This run used a writable temporary directory at `.local-tools/task013-temp` for `TEMP`/`TMP`: the first sandboxed Vitest attempt failed on its external temporary-cache rename; rerunning with workspace temporary storage passed. Native PHP checks needed execution outside the filesystem sandbox because its `is_readable`/`is_writable` checks rejected existing workspace files there. No dependency or application workaround was added for either runner restriction.

Backend and database-writing browser checks use guarded local PostgreSQL `snappik_test`. Browser suites run sequentially after backend refresh/migration. The public/foundation command uses its existing local development server without seeding accounts; dedicated product flows use isolated test servers. The new product command reuses the CLI-only synthetic delivery HTTP/disk bindings on loopback port 18772. No external request, message or charge is part of these product fixtures.

| Command | Inspected result |
| --- | --- |
| `npm run build` | TypeScript and production Vite build passed |
| `npm test` | 5 frontend tests passed with workspace temporary storage |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final complete suite: **276 tests / 3,218 assertions / 1 skipped** (opt-in above-4-GiB proof); PostgreSQL concurrency and existing provider contracts passed |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Composer manifest/lock validation passed |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Restored the test schema after the backend suite; no TASK-013 migration added |
| `npm run test:browser -- --output test-results/task013/foundation` | 21 passed; 41 dedicated scenarios intentionally skipped by this generic configuration |
| `npm run test:browser:auth -- --output test-results/task013/auth` | 6 passed |
| `npm run test:browser:projects -- --output test-results/task013/projects` | 4 passed |
| `npm run test:browser:selection -- --output test-results/task013/selection` | 4 passed |
| `npm run test:browser:progress -- --output test-results/task013/progress` | 4 passed |
| `npm run test:browser:delivery -- --output test-results/task013/delivery` | 4 passed |
| `npm run test:browser:archives -- --output test-results/task013/archives` | 4 passed |
| `npm run test:browser:vouchers -- --output test-results/task013/vouchers` | 4 passed |
| `npm run test:browser:checkout -- --output test-results/task013/checkout` | 4 passed |
| `npm run test:browser:operations -- --output test-results/task013/operations` | 4 passed |
| `npm run test:browser:product -- --output test-results/task013/product` | Final **8 passed**: 360px/1440px × light/dark × normal/reduced motion |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | PHP formatting passed |
| `node .local-tools/check-links.mjs`; `git diff --check` | Local Markdown links passed in 22 documents; patch whitespace passed |

All 11 browser commands passed. The generic command's 41 skipped cases are executed by the dedicated configurations; the auth configuration also repeats its five read-only form cases. This is 67 successful browser test executions covering 62 distinct scenarios. The final foundation/auth/product checks ran again after the last form-routing, transition-contrast and fixture-isolation follow-ups.

## Browser evidence and inspection

The foundation matrix covers all five shells at 360px/1440px, light/dark and normal/reduced motion. Additional checks cover first paint with application scripts blocked (system and stored preference), native control color scheme, live system changes, explicit keyboard preference/focus/persistence, blocked-storage navigation, and interrupted dialog entry/closing at CSS 200% zoom with forced colors. Screenshots were inspected for readable wrapping, visible focus, named errors and disabled/selected/frozen states. CSS zoom is a local reflow check, not an assertion of physical-device or assistive-technology certification.

The product matrix composes real application routes with synthetic accounts/providers at both widths, both themes and both motion settings: public login/navigation, unsaved project draft cancellation/acceptance, independent module passwords, empty review, delayed/aborted save with no premature success, explicit same-operation retry, preview keyboard/Escape focus, expired selection/results/filename export/billing, and eligible delivery/ZIP after expiry. It verifies unchanged decoded image pixels/source/rendering across theme switches, and that pending ZIP state survives a theme change. The separate selection suite verifies review/confirmation/reopening and 2,001 photos with only 36 mounted tiles, bounded animation candidates, paging and retained cross-page choices. Separate delivery/archive suites verify actual browser attachment bytes and complete ZIP directory contents, recovery, gates and expiry.

Screenshots and numeric contrast JSON are generated under ignored `test-results/`, with per-suite diagnostic output under `test-results/task013/` and ignored `.local-tools/task013-browser-*.txt`. They are local verification artifacts, not committed product assets. Browser assertions check page-level horizontal overflow, current computed text/surface pairs composited through ancestor opacity and control/focus token pairs; screenshots supply visual inspection of actual states. Filename suffixes identify the initial scenario preference; the pending/failed-save screenshot intentionally follows an in-place theme switch. Tests do not claim a measured frame rate or unrestricted real-provider capacity.

The final product run generated **64 state snapshots**, with **656 text samples** (minimum **5.06:1**) and **512 control/focus token-pair samples** (minimum **3.82:1**). Every sampled pair passed its 4.5:1 text or 3:1 control threshold. This is coverage of the rendered fixtures and shared token combinations, not a claim that every possible document/photo or assistive-technology combination has been independently measured. Representative inspected artifacts include `product-public-360-light-reduce.png`, `product-password-error-360-light-reduce.png`, `product-frozen-360-light-reduce.png`, `product-delivery-1440-dark-reduce.png`, `product-closed-360-light-no-preference.png`, `admin-360-dark-no-preference.png`, `operations-failures-360-dark.png` and `reflow-forced-colors-dark.png` under `test-results/`.

The initial full product matrix hit the actual aggregate loopback password limiter in two cases (six passed, two timed out after the intended throttling response). The CLI-only test fixture now clears only the aggregate synthetic loopback-IP counters between product scenarios in the disposable database; the application's limits remain unchanged. The final run is recorded in the table. CI includes the product command and allows 30 minutes for locked setup, existing large-stream proof and all sequential browser suites; hosted CI was not run here.

## Remaining acceptance gates

- TASK-002 still needs an authorized real Drive resource-key fixture; TASK-006/TASK-008 and their dependent prerequisite acceptance remain open.
- TASK-010/TASK-011 still need a configured authorized QRIS sandbox merchant and actual lifecycle/missed-notification evidence. Synthetic checkout/reconciliation behavior does not close that gate.
- TASK-014 owns the remaining adversarial/aggregate recovery and isolated backup-restore work. TASK-015 owns production-like infrastructure, target-device/provider acceptance, release configuration and explicit publication authorization.
- The opt-in above-4-GiB proof is not rerun for presentation work. Previous streaming evidence remains in TASK-002/TASK-008; no new large-stream performance claim is made.

No additional live-provider transfer, real message, payment, service purchase or deployment was performed. Final document approval remains pending independently of this implementation request.
