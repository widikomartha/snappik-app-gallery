# Public landing direction

## Context and approval

The owner requested three directions after reviewing the project documentation, selected the first direction's monochrome palette, rejected its original font, selected Manrope in the interactive study, requested rounded illustrations with more animation, then requested small pastel accents and fuller landing content. On October 24, 2026, the owner approved the resulting complete preview and instructed implementation. Approval applies to this public landing, not a redesign of operational screens or final approval of the complete documentation package.

The audience is photographers evaluating SnapPik and their browser-based client selection/delivery workflow. The primary action is photographer registration. Conversation and product copy are Indonesian; durable documentation is English. [PRD](../product/PRD.md) owns all feature and commercial claims; [Design System](DESIGN_SYSTEM.md#approved-illustrated-direction-and-landing-treatment) owns final tokens, geometry, font and motion recipes.

## Approved decisions

| Aspect | Choice | Status |
| --- | --- | --- |
| Color | Direction 11's monochrome base; small sage, lavender and peach accents only in original illustrative artwork | approved |
| Typography | Manrope 700 headings, DM Sans body/UI, local font subset and preserved license | approved |
| Illustration | Rounded camera, photo cards, folders and ZIP artwork; unmodified real photos elsewhere | approved |
| Motion | Richer decorative landing illustration movement, with pause, reduced-motion and offscreen handling | approved |
| Composition | Complete responsive landing with hierarchy and working section navigation | approved |
| Copy | Indonesian hero, benefits, workflow, selection, delivery, controls, Drive explanation, pricing, FAQ and closing CTA | approved |

The reference palette's gray is decorative; the darker supporting-text derivative and dark-appearance counterparts are specified by the Design System. Illustration SVGs have no repeated definition IDs and are hidden from assistive technology. The interactive example is labeled synthetic; its three-choice allowance is a local demonstration value, never a plan cap.

## Implementation

- `resources/js/pages/public/welcome.tsx`: actual public landing and subscribed reduced-motion state.
- `resources/js/components/landing/selection-demo.tsx`: local synthetic choices; no API or domain writes.
- `resources/js/layouts/shell.tsx`: opt-in landing shell; existing operational shell behavior retained.
- `resources/css/landing.css` and scoped `tokens.css` overrides: visual contract without global theme replacement.
- `resources/fonts/manrope-latin-700.woff2` and `manrope-OFL.txt`: only the required heading subset and its license.
- `tests/Browser/landing.spec.ts`: browser coverage for content, contrast, responsive layout, example choices, themes, motion preference changes and registration navigation.
- `resources/js/components/landing/welcome.test.tsx`: a changed server price/duration and missing-offer rendering, kept outside the production page-discovery glob.

Prices and duration use the current server-provided plan. Missing pricing is explained instead of using a hardcoded offer. Trial timing, the three-project trial allowance, unlimited paid content, public Drive requirement, separate source catalogues and complete single-ZIP delivery remain unchanged. The footer preserves configured legal/support links; no contacts, testimonials, client counts or external-service claims were invented. Implementation is local and does not publish the application.

## Verification

Verified locally on October 24, 2026:

| Command | Inspected result |
| --- | --- |
| `npm run build` | Passed TypeScript and production Vite build. The 24.57 kB Manrope WOFF2 subset is emitted locally; the production page manifest contains no test page. |
| `npm test` | 3 files / 6 tests passed. TEMP/TMP were pointed at ignored `.local-tools/landing-temp` for the Windows sandbox. |
| `.local-tools/php/php.exe vendor/bin/phpunit --filter ProductPresentationTest` | 5 tests / 186 assertions passed, including persisted current-price changes. |
| `npx playwright test tests/Browser/landing.spec.ts --workers=1 --output test-results/landing-final` | 6 tests passed with `PLAYWRIGHT_CHANNEL=chrome`: 360px/1440px, light/dark, readable text contrast, navigation, synthetic choice limit/replacement, state preservation through theme changes, reduced-motion changes, FAQ, registration focus and 200% reflow. |
| `npx playwright test --config playwright.product.config.ts --output test-results/landing-product-regression` | 8 integrated synthetic product flows passed in 2.5 minutes using the isolated `snappik_test` database and bundled Chromium. Covers public entry, authentication, project drafts, client gates, expiry and ZIP states in both themes, both widths and both motion preferences. Final landing header styling was subsequently verified with the focused landing suite. |

Browser commands used the local PHP directory on PATH; the product suite used `.local-tools/browsers` as PLAYWRIGHT_BROWSERS_PATH. PHP/browser checks ran outside the restricted sandbox after its PHP readability check and localhost access failed; automatic approval allowed these local checks. The first frontend run failed while renaming its sandbox cache; using the workspace-local temp directory resolved it. The first landing browser matrix exposed a stale reduced-motion button label; subscribing through `useSyncExternalStore` fixed it, and the final six-test run passed. No remaining failure from these checks is unresolved.

Actual desktop, mobile and pricing screenshots were inspected. Text contrast, no page overflow and 200% reflow are checked in the browser suite. The example retains selections when appearance changes. Illustration animations pause offscreen, stop with the pause control and become static under reduced motion. No new live-provider, payment or messaging test was performed.

The final whitespace and relative-document-link checks passed. Existing provider, prerequisite-acceptance and public-launch gates remain tracked in the documentation index and backlog.

## Iconsax refinement — October 24, 2026

The owner requested removing unnecessary buttons/text and using Iconsax wherever icons clarify the page. Implemented the Linear style from [`iconsax-reactjs`](https://github.com/rendinjast/iconsax-react), pinned at 0.0.8 in the manifest and lockfile. Its npm metadata declares MIT licensing, React peer `*`, and no side effects. Package documentation and author attribution remain in the installed dependency; only imported components are included by the production build. This adds one dependency, without changing the React version or using peer-dependency overrides.

- The landing theme control now shows moon/sun with an accessible label and tooltip. Other shells retain their existing textual theme control.
- A compact pause/play button sits at the hero illustration; it is absent while reduced motion is enabled. The separate illustration-status caption is removed.
- The header's repeated registration CTA and the closing secondary pricing link are removed. Login and the primary registration CTAs remain.
- Icons now support the value strip, project feature headings, benefit lists, CTA arrows and FAQ disclosure controls.
- Repetitive simulation copy and the pricing note are shortened. The simulation remains identified, and Drive/privacy, trial and Pro policy disclosures remain available. The internal term "MVP" is removed from public FAQ copy.

Follow-up validation: `npm run build` passed and `npm test` passed 3 files / 6 tests. The first browser attempt passed five cases; the keyboard/reflow test needed to wait for the React page to render before interacting. With that readiness check, it exposed pricing-card overflow at 200% zoom. The pricing grid now permits column shrinkage and stacks below 760px. The final command `npx playwright test tests/Browser/landing.spec.ts --workers=1 --output test-results/landing-iconsax` passed all 6 tests in 12.7 seconds. Updated browser checks verify icon-only accessible controls, unchanged choices through theme switches, pause-control removal/restoration with reduced motion, and four feature icons, alongside the existing contrast/reflow/CTA/FAQ coverage. Desktop, mobile, feature and dark-appearance screenshots were inspected. Earlier PHP and integrated-product results above were not rerun for this icon/copy-only amendment.

## Shared product extension — October 24, 2026

The owner explicitly requested applying the accepted landing direction to the remaining pages, adapting layouts, illustrations, animation and design. This follow-up supersedes the earlier landing-only boundary. It does not change product behavior, provider boundaries, prerequisite acceptance, final document approval or publication authorization.

Implemented shared monochrome light/dark tokens, Manrope 700 headings, DM Sans UI, rounded panels/fields/pill actions, compact accessible Iconsax controls and original pastel-accented SVG scenes. The obsolete Raleway dependency was removed. `fonts.css` now owns the font declaration; `product.css` owns responsive product geometry. `PageHeader`, `EmptyState` and `Illustration` provide reusable composition without invented product data.

Authentication has an illustrated split layout; photographer/admin workspaces have an active sidebar that wraps into a navigation grid on mobile. Projects have compact server-backed selection cards, grouped settings/source controls and publication cards. Billing separates checkout and history; administration separates account/payment/job summaries. Client entry separates selection and delivery modules; photo pages use small illustrated headings, Iconsax preview/download/select actions and illustrated empty states while retaining original photo pixels and complete-fit previews. Product illustrations animate once with bounded 800ms motion and respect reduced motion; the public landing alone retains its pausable decorative loops.

Extension checks inspected locally:

| Command | Result |
| --- | --- |
| `npm run build` | Passed TypeScript and production build after the shared layouts, local font extraction and removal of preview clipping. |
| `npm test` | 3 files / 6 tests passed, with workspace-local TEMP/TMP. |
| `npx playwright test tests/Browser/foundation.spec.ts tests/Browser/landing.spec.ts --workers=1 --output test-results/product-design-foundation` | 22 passed: all five shells, 360px/1440px, both themes/motion preferences, storage failure, focus, forced colors, 200% reflow and landing interactions. |
| `npx playwright test --config playwright.product.config.ts --output test-results/product-design-final` | 8 passed in 3.0 minutes. Expanded inspection includes authentication, dashboard, project list/settings and billing, alongside client gates, selections, save recovery, preview focus, expiry and complete ZIP states. |
| `npx playwright test --config playwright.operations.config.ts --output test-results/product-design-operations` | 4 passed in 1.5 minutes: responsive admin navigation, accounts, price versions, suspension, retries and payment review. |
| `npx playwright test --config playwright.checkout.config.ts --output test-results/product-design-checkout-verified` | 4 passed in 1.9 minutes: both widths/themes, quoted amounts, voucher errors, interrupted checkout recovery, verification and provider-state history. |
| `npx playwright test --config playwright.auth.config.ts --output test-results/product-design-auth-verified` | 6 passed: responsive login/register/recovery, theme-preserved drafts, named errors, verification gate and logout. |
| `.local-tools/php/php.exe -l tests/Support/checkout-browser-fixture.php` | Passed syntax validation for the test-only fixture adjustment. |

The first integrated run passed reduced-motion cases and exposed four contrast failures in normal-motion cases. A new button background transition was interpolating between primary/disabled/theme surfaces while text changed immediately. Removing that background transition preserves readable foreground/background pairs; press feedback and illustration motion remain. The full eight-case rerun passed. Gallery frames have rounded backgrounds without clipping original photo corners; photo rendering retains `object-fit: contain` and no filters.

Desktop/mobile authentication, project settings, dashboard, client gate/gallery and billing screenshots were inspected in both appearances, along with desktop/mobile administrative overviews and the final account/checkout screenshots. Relative links and heading anchors in the seven affected documents and `git diff --check` passed. Browser checks use localhost, the portable PHP runtime and bundled Chromium; dedicated suites use synthetic fixtures in `snappik_test` and run sequentially. The checkout test now navigates through the shared Tagihan item instead of the removed duplicate dashboard link. Its first attempt was stopped at that stale selector. The next four cases exposed a pre-existing test-data dependency: the admin suite had persisted a different current price. The guarded test fixture now creates a new 29,000-IDR synthetic plan version through the existing admin action before each checkout case, preserving historical orders. The final four-case checkout rerun passed. Production backend code was not changed, and no separate backend unit suite or live-provider verification was run for this presentation extension. All 44 final browser cases and 6 frontend tests passed; no failure from these checks remains unresolved.

## Workflow copy and layout refinement — October 24, 2026

After the Fastpik comparison, the owner identified ambiguous wording, insufficient explanations and weak layout. This authorizes refinement of the existing product presentation within the accepted visual direction. It does not approve deferred Fastpik features, final documentation or deployment. The [Design System](DESIGN_SYSTEM.md#task-hierarchy-and-product-language) owns the resulting presentation rules.

Implemented changes:

- Project setup explains reading a Drive folder, publishing and sharing as separate steps. Name, allowance and the selection source are initially visible; optional contacts, unconfigured delivery, passwords/deadlines and Drive setup guidance use native disclosures. Relevant validation errors and existing delivery sources expand their sections. Descriptions distinguish the client allowance from gallery capacity and make clear that finished photos can be configured later.
- Existing projects separate configuration from publication/sharing. The sidebar precedes the form in narrow-screen visual and DOM order. Sharing includes an actual clipboard action, acknowledged success and selected-text fallback. Archive and link rotation are secondary controls. Empty-choice guidance distinguishes a published gallery from one still requiring publication.
- Client entry explains selection versus finished-photo delivery and gives each module a direct opening action. Selection consolidates count/review navigation, makes routine save feedback quieter and uses plain-language recovery actions. Uncertain saves offer retry or retrieval of saved choices without a redundant refresh action. Confirmation explains the resulting lock and photographer reopening.
- Delivery explains one complete ZIP and individual originals without a slogan or worker terminology. Folder controls use compact toolbars. Results distinguish draft, current confirmation and historical confirmation; export has its own column on wide screens and technical file details are expandable.
- Dashboard/project descriptions, account guidance and subscription descriptions explain actual tasks. Operational headings use smaller supporting illustrations. Existing monochrome/pastel roles, photo rendering, themes and motion preferences are retained.

Production backend actions, permissions, database contracts, quotas and provider pacing were not changed. The earlier audit's selection-request, media-contention and overview-query findings remain follow-up work. Clipboard support does not send messages. No new integration, dependency, live-provider transfer, payment or production publication was introduced.

Verification inspected for this follow-up:

| Command | Result |
| --- | --- |
| `npm run build` | Passed TypeScript and production Vite build, including the compact form refinement. |
| `npm test` | 3 files / 6 tests passed with workspace-local TEMP/TMP. |
| `npx playwright test --config playwright.product.config.ts --output test-results/workflow-copy-product` | 8 passed in 3.0 minutes: integrated public/auth/workspace/client/error/expiry/ZIP flows, 360px/1440px, both themes and both motion preferences. Includes text/control contrast and horizontal-overflow checks. |
| `npx playwright test --config playwright.selection.config.ts --output test-results/workflow-copy-final-selection` | 4 passed in 1.4 minutes: uncertain acknowledgement/retry, review, shared choices, confirmation, reopening and pagination in both widths/themes. |
| `npx playwright test --config playwright.progress.config.ts --output test-results/workflow-copy-final-progress` | 4 passed in 35.4 seconds: draft/current/history distinctions, stale-read recovery, duplicate-name export and clipboard fallback in both widths/themes. |
| `npx playwright test --config playwright.projects.config.ts --output test-results/workflow-copy-projects-compact` | 4 passed in 1.1 minutes after the compact-form refinement: both widths/themes, optional source/access controls, publication, clipboard success/fallback, link rotation and archive/restore. Creation text/control contrast and no horizontal overflow passed. |

The integrated matrix preceded the last small wording/spacing adjustments and compact-project-form refinement. The subsequent selection/progress suites exercise the final client/result wording. Project checks additionally cover optional-section discovery, text/control contrast, disabled sharing before publication, clipboard success/denial and link rotation/archive recovery. Browser checks run sequentially against synthetic `snappik_test` data, using local PHP and bundled Chromium outside the restrictive sandbox. The earlier audit's backend test results are separate historical evidence; no backend code or live-provider check changed in this follow-up.

Final project-creation desktop/mobile screenshots, project settings, client save recovery/delivery and photographer results screenshots were inspected. Screenshot fixtures use synthetic names and images; they do not benchmark real Drive latency. Twenty distinct browser scenarios and six frontend tests passed across the commands above. Relative documentation file links and `git diff --check` passed; existing provider and publication gates remain open.
