# SnapPik MVP Backlog

**TASK-001, TASK-003 and TASK-009: COMPLETE. TASK-002: FULL-CATALOGUE ZIP VERIFIED / REAL RESOURCE-KEY FIXTURE PENDING. TASK-004–TASK-008: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING. TASK-010–TASK-011: IMPLEMENTED / REAL SANDBOX QRIS ACCEPTANCE PENDING. TASK-012–TASK-014: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING. TASK-015: LOCAL PREPARATION IMPLEMENTED / RELEASE GATES PENDING.** Product authority: [PRD](../product/PRD.md). Technical contracts: [Architecture](../engineering/ARCHITECTURE.md). [Package approval state](../index.md).

These are implementation tasks, not claims that the application exists. Dependencies are hard prerequisites for declaring a task complete; a task may contain a bounded exploratory prototype without advertising it as a finished feature. Technical feasibility proofs precede broad UI work. Production publication remains a separately authorized action.

## TASK-001 — Bootstrap the documented application

- Status: COMPLETE (explicitly requested and verified on October 22, 2026)
- Requirements: REQ-001, REQ-002, REQ-018, REQ-019, REQ-022
- Dependencies: None
- Scope: bootstrap Laravel/React/TypeScript/Inertia and PostgreSQL at the existing workspace root, preserving docs; choose compatible maintained versions, real lockfiles, local environment example, test infrastructure and safe CI. Add the shared public/authenticated/admin/client page boundaries without implementing deferred features.
- UI foundation: establish the [Design System](../design/DESIGN_SYSTEM.md) token layer, shared accessible primitives and root Motion/reduced-motion configuration. Record the actual compatible `motion` dependency in the generated manifest/lockfile; this task does not claim finished product pages.
- Acceptance: a fresh checkout can follow actual README setup/build/test commands; framework authentication/security configuration is explicit; no secrets/reference credentials are committed; a real PostgreSQL test database is supported. Application paths are reclassified from planned only after they exist.
- Out of scope: hosting purchases, production credentials, live deployment, hosted photo upload, extra storage tiers.
- Completion evidence: committed manifests/lockfiles and the exact successful fresh-setup/build/test commands with results.
- Verification record: [TASK-001 evidence](TASK-001_EVIDENCE.md). Fresh locked setup/build passed; 8 PostgreSQL feature tests (67 assertions), 5 frontend tests and 11 Chromium tests passed. Synthetic shells cover both themes, both widths and normal/reduced motion. Hosted CI and domain/provider workflows are not claimed.

## TASK-002 — Prove public Drive and one large ZIP end to end

- Status: FULL-CATALOGUE ZIP VERIFIED / REAL RESOURCE-KEY FIXTURE PENDING (October 22, 2026; not complete)
- Requirements: REQ-005, REQ-010, REQ-011, REQ-017
- Dependencies: TASK-001
- Scope: establish provider Contracts, an official public-Drive adapter, paginated descendant import and a worker prototype streaming source files into one private R2 ZIP64 object. Prove the architecture before polishing product screens.
- Acceptance: an authorized public folder with subfolders/resource-key cases imports fully; individual original bytes match; a multi-file archive contains every expected identity; a synthetic source above 4 GiB produces one extractable archive without holding it in RAM. Document measured memory and native provider failures. A failing second input never yields ready partial output. Unavailable credentials are stated as an integration blocker, not bypassed by scraping.
- Out of scope: broad photographer OAuth, storing originals permanently, several ZIP parts, a Drive-folder redirect.
- Completion evidence: adapter contract results, authorized provider smoke-test record, ZIP entry/CRC validation and measured streaming resource profile. Any infeasible boundary is resolved before dependent delivery tasks proceed.
- Verification record: [TASK-002 evidence](TASK-002_EVIDENCE.md). Official API adapter, encrypted PostgreSQL staging/checkpoints, local/test-only worker, private R2 multipart adapter, independent extraction verifier and smoke-test commands exist. Synthetic contracts and a source above 4 GiB were exercised. Following owner configuration and transfer approval, actual Drive import/pagination found all 384 photos. The corrected worker produced one **1,130,302,773-byte ZIP** containing all **1,130,185,579 original bytes**; independent extraction verified every size, CRC and MD5. The object was deleted and no multipart uploads remained. Build peak PHP memory was **54 MiB**; final readback/extraction peak was **66 MiB**. Actual R2 failed-input abort was also proven with generated data. Required real resource-key fixture coverage remains open: the authorized tree supplied none. TASK-004, TASK-007 and TASK-008 cannot treat this prerequisite as complete. The evidence retains the installed libzip strict-check compatibility observation and target-extractor/deployment privacy gates.
- Follow-up: pacing alone failed after 23 photos; a direct comparison then identified a correction to the documented `/download/drive/v3/` media path while retaining API keys. That route completed the entire catalogue. R2 readback now uses bounded, ETag-pinned ranges through the normal SDK transport with bounded retries. The final suite passed **68 tests / 236 assertions**. Earlier native failures and interrupted readbacks remain recorded rather than reclassified as successful tests; no service-account flow or network change was implemented.

## TASK-003 — Implement authentication, roles and access policy

- Status: COMPLETE (explicitly requested and verified on October 22, 2026)
- Requirements: REQ-001, REQ-002, REQ-003, REQ-015, REQ-019
- Dependencies: TASK-001
- Scope: email/password authentication, verification/reset mail, one trial record, secure sessions, owner-admin provisioning boundary, tenant policies, central access decisions and suspension states.
- Acceptance: repeated verification/reset events cannot restart trial; public registration cannot obtain admin role; expired versus suspended accounts receive their distinct documented access. Cross-tenant ID substitution is denied for read/write/job/media/billing paths. An owner can provision the admin without committing its credential.
- Out of scope: Google login, studio teams, impersonation, a free-order grant path.
- Completion evidence: auth/role feature tests, real PostgreSQL entitlement-boundary tests and sandbox email-flow demonstration.
- Verification record: [TASK-003 evidence](TASK-003_EVIDENCE.md). Email/password registration/login/logout, signed verification, one locked seven-day trial, one-use resets, session revocation, throttles, owner-admin provisioning, audited suspension/reactivation and shared tenant/gallery/account decisions exist. The final backend suite passed **90 tests / 501 assertions** with the opt-in large-ZIP test skipped; frontend tests/build passed. Browser regressions passed **16 tests** (one isolated-write test intentionally skipped), and the dedicated test-database account suite passed **6 tests**. Separate PostgreSQL processes proved concurrent verification and reset consumption. Real rendered verification/reset messages were exercised through the array transport without external delivery. Product project/media/order endpoints must apply these policies as they are implemented in subsequent tasks; their integration is not claimed by the test-only policy probes. No real administrator was provisioned or real mail sent.

## TASK-004 — Deliver project configuration and catalogue publication

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 22, 2026; TASK-002 remains incomplete)
- Requirements: REQ-003, REQ-004, REQ-005, REQ-006, REQ-019
- Dependencies: TASK-002, TASK-003
- Scope: photographer project creation/edit/archive/unarchive, independent module sources/gates, optional contacts, async import/refresh, complete catalogue revisions and client capability management.
- Acceptance: a root containing only subfolders imports; selection and delivery use separate non-overlapping source sets; failed refresh retains the previous revision; unsupported files and missing choices are visible. Two concurrent attempts cannot exceed the trial cap. Paid projects/metadata exceed the rejected count/byte thresholds without a package validation failure. Archive closes links without deleting original bytes or results.
- Out of scope: organizational folders, batch import, extra/cetakan modes, hosted upload.
- Completion evidence: photographer browser flow plus pagination, revision-publication, overlap, archive and concurrent trial-slot tests.
- Verification record: [TASK-004 evidence](TASK-004_EVIDENCE.md). Photographer project create/edit/archive/restore, separate module sources/passwords/deadlines/publication, encrypted capabilities and rotation, PostgreSQL checkpointed import jobs, atomic complete-revision replacement and paginated excluded-file/missing-choice diagnostics are implemented. Synthetic PostgreSQL/browser checks include real queue consumption, nested pagination, failed-refresh retention, source overlap, stale forms, checkpoint resumption, transaction rollback, paid metadata beyond rejected thresholds and separate-process create/restore races for the last trial slot. TASK-002's authorized real resource-key fixture remains a hard prerequisite to declaring this task complete. Client photo browsing/choice/confirmation/media actions remain in TASK-005/007; the capability page exposes only gated module status/counts at this stage.

## TASK-005 — Deliver client selection, autosave and confirmation

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 22, 2026; TASK-004 acceptance remains open)
- Requirements: REQ-002, REQ-006, REQ-007, REQ-008, REQ-015
- Dependencies: TASK-004
- Scope: client gallery grid/folders/enlargement, password sessions, deadlines, per-photo idempotent selection, canonical save states, review, atomic confirmation and photographer reopening.
- Acceptance: choices survive reload and appear before confirmation; two clients racing the final slot cannot exceed allowance; stale review cannot confirm unseen changes; edit/confirm races preserve one immutable snapshot. One of an allowance of two can confirm, zero cannot. Reopen keeps choices/history under a new epoch. Expiry across an open session blocks later writes without deleting acknowledged choices.
- Out of scope: separate per-person selections, offline replay, photo notes, extra/print selection.
- Completion evidence: browser demonstration and real-database race tests including replayed operation IDs, stale versions, module deadline and account expiry.
- Verification record: [TASK-005 evidence](TASK-005_EVIDENCE.md). Bounded gallery/folder/review pages, gated thumbnail/original previews, shared autosave, operation receipts, stale-version/revision conflicts, atomic immutable confirmations and photographer reopening are implemented. Synthetic HTTP/browser checks and separate-process PostgreSQL races exercise final-slot contention, replay, confirmation/edit serialization and gates changing while requests wait. TASK-004's open prerequisite acceptance remains a hard completion gate; no real-provider preview smoke transfer or production publication is claimed.

## TASK-006 — Deliver progress dashboard and filename export

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 22, 2026; TASK-005 acceptance remains open)
- Requirements: REQ-004, REQ-009, REQ-015, REQ-018
- Dependencies: TASK-005
- Scope: photographer project/progress overview, counts/current versus historical confirmations, stale-state handling, read-only expired account results and copyable filenames with fallback.
- Acceptance: draft choices are visible before confirmation; reopened selection is no longer shown as currently confirmed; unavailable original records remain in history; duplicate filenames are disambiguated. Clipboard denial shows selectable output; expired account can view/copy without gaining mutation rights.
- Out of scope: analytics suite, Telegram, automatic WhatsApp, freelancer messaging.
- Completion evidence: state-transition and export-format tests plus narrow/wide viewport demonstration.
- Verification record: [TASK-006 evidence](TASK-006_EVIDENCE.md). Tenant-scoped project/progress overviews, paginated current choices and immutable confirmation history, acknowledgement times, independent frozen/closed context, stale-refresh handling and full filename export with selectable clipboard fallback are implemented. Local PostgreSQL and browser checks cover state transitions, unavailable historical originals, duplicate names across export chunks, expiry without mutation access and narrow/wide light/dark views. TASK-005's prerequisite acceptance remains a hard completion gate; no new live-provider transfer or production publication is claimed.

## TASK-007 — Deliver finished galleries and individual downloads

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 22, 2026; TASK-004/TASK-005 acceptance remains open)
- Requirements: REQ-002, REQ-006, REQ-010, REQ-015, REQ-019
- Dependencies: TASK-004, TASK-005
- Scope: finished-photo publication/viewing and protected original-byte delivery with safe filenames and range handling.
- Acceptance: separate selection/delivery passwords and deadlines remain independent; original-byte fixture matches the downloaded file; revoked source/download permission is visible and never produces a misleading JPEG/HTML response. Another project's file ID and denied new Range requests are blocked. Account expiry alone does not block valid delivery.
- Out of scope: permanent originals cache, upload allocation, link redirect to a Drive folder.
- Completion evidence: downloaded-byte comparison, media authorization/range tests and a client browser download demonstration.
- Verification record: [TASK-007 evidence](TASK-007_EVIDENCE.md). Finished-gallery folder/grid/enlargement and protected original attachments are implemented using the existing independent delivery publication/password/deadline boundary. Synthetic PostgreSQL/provider/browser checks cover original-byte comparison, Range/If-Range, cross-project/revision denial, revoked rights, safe filenames, subscription expiry and late stream failure. TASK-004/TASK-005's prerequisite acceptance remains a hard completion gate; no new live-provider transfer or production publication is claimed.

## TASK-008 — Productize complete single-ZIP jobs

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 23, 2026; TASK-002/TASK-007 acceptance remains open)
- Requirements: REQ-002, REQ-011, REQ-015, REQ-017, REQ-018
- Dependencies: TASK-002, TASK-007
- Scope: immutable manifests, deduplicated persistent jobs, streaming worker, progress/status UI, protected ZIP download, expiry/recreation and retryable partial-object cleanup.
- Acceptance: Download All covers the entire published delivery revision including every subfolder, not merely the visible page. Duplicate names are preserved; missing/changed/truncated input fails the whole attempt. Closing/reopening the browser restores status; repeated clicks share one job; private storage and gate changes cannot be bypassed. An archive is inaccessible at its 24-hour application deadline even if cleanup is delayed. No application byte/count cap or multiple ZIP-part fallback is added.
- Out of scope: archives of RAW/video/unsupported files, permanent ZIP retention, best-effort incomplete archives.
- Completion evidence: actual ZIP entry/CRC/identity checks, large-stream measurement, restart/retry/deduplication tests, expiry tests and a browser download from SnapPik.
- Verification record: [TASK-008 evidence](TASK-008_EVIDENCE.md). Complete immutable encrypted manifests, transactional persistent deduplication, leased/advisory-locked streaming attempts, private range downloads, browser status restoration, exact 24-hour expiry/recreation and retryable exact-key object/orphan-upload cleanup are implemented. Synthetic PostgreSQL/SDK/browser checks and independent ZIP extraction are recorded in the evidence. TASK-002's authorized real resource-key fixture and TASK-007 acceptance remain hard completion gates; no additional live-provider transfer or production publication is claimed.

## TASK-009 — Deliver owner voucher management

- Status: COMPLETE (explicitly requested and verified on October 23, 2026)
- Requirements: REQ-002, REQ-013, REQ-016, REQ-019
- Dependencies: TASK-003
- Scope: voucher/version schema, owner admin creation/edit/deactivation, normalized unique codes, date/use limits, immutable quote rules and reservation/redemption reporting.
- Acceptance: invalid percentage/fixed/limit/date values are rejected; code normalization cannot create duplicates; limits span versions of the same code; a limit cannot be reduced below held plus used capacity. Old versions remain identifiable and changes are audited. No photographer can administer codes or inspect others' usage.
- Out of scope: stacking, cashback, referrals, zero-pay vouchers, per-user manual entitlement grants.
- Completion evidence: admin browser flow, validation/versioning/tenant tests and integer-discount example results.
- Verification record: [TASK-009 evidence](TASK-009_EVIDENCE.md). Owner-only create/edit/deactivate/reactivate screens, normalized permanent code identities, immutable terms, audited versions, stale-form protection, cross-version held/successful capacity checks and paginated usage reports are implemented. PostgreSQL feature/process-race checks and four narrow/wide light/dark browser flows verify the task. Integer quotes preserve exact IDR amounts and reject invalid payable totals. Reservation/redemption tables support reporting. TASK-010 subsequently adds orders, foreign keys and holds; TASK-011 owns redemption/release. TASK-009 itself introduces no manual grant or payment integration.

## TASK-010 — Deliver server-priced checkout and voucher reservations

- Status: IMPLEMENTED / REAL SANDBOX QRIS ACCEPTANCE PENDING (October 23, 2026; not complete)
- Requirements: REQ-003, REQ-012, REQ-013
- Dependencies: TASK-003, TASK-009
- Scope: Pro price snapshots, checkout quote, single-voucher application, total/per-user atomic holds, stable provider-order identity, checkout idempotency, resume and unknown-creation recovery through Midtrans adapter.
- Acceptance: displayed base/discount/payable equals order/provider snapshot; amount/owner/duration tampering fails. Two buyers racing the last voucher slot cannot both reserve; two pending orders cannot bypass one user's limit. Repeated submit uses one order. Browser abandonment/timeouts do not release an uncertain reservation or create a second provider order. Nonpositive/below-channel-minimum totals are rejected.
- Out of scope: auto-debit, customer surcharges, additional storage SKUs, free checkout.
- Completion evidence: PostgreSQL reservation-race tests, adapter timeout/resume contracts and sandbox QRIS checkout demonstration.
- Verification record: [TASK-010 evidence](TASK-010_EVIDENCE.md). Immutable server quotes/order terms, total/per-account holds, stable provider identity, idempotent creation, encrypted Snap pages, tenant-scoped billing and same-order unknown-creation recovery are implemented. Real PostgreSQL process races and synthetic Midtrans/browser verification are recorded there. The owner confirmed no sandbox merchant is configured and chose synthetic verification; the required real sandbox QRIS demonstration remains open. TASK-011 owns settlement/grants, durable callbacks/reconciliation and verified reservation release. No live payment or production publication is claimed.

## TASK-011 — Deliver verified payment grants and reconciliation

- Status: IMPLEMENTED / REAL SANDBOX LIFECYCLE AND PREREQUISITE ACCEPTANCE PENDING (October 23, 2026; not complete)
- Requirements: REQ-003, REQ-012, REQ-013, REQ-014, REQ-015, REQ-017
- Dependencies: TASK-010
- Scope: durable callback intake/outbox, signature and authoritative status verification, amount/currency/identity checks, unique entitlement/redemption application, safe terminal reservation release, scheduler reconciliation and review flags.
- Acceptance: invalid signature or mismatched amount grants nothing; duplicates extend/redeem once; two distinct simultaneous settlements extend twice with no lost days. Failed/expired provider-confirmed orders release holds, while uncertain ones retain them. Late legitimate payments honor their original contract. A crash between event receipt and grant recovers safely. Pending/old failure cannot regress success; refund/unknown states are surfaced for operator review without new grants.
- Out of scope: manual mark-paid, refund execution, automatic entitlement clawback, treating redirects as payment proof.
- Completion evidence: real PostgreSQL financial-race tests, complete Midtrans sandbox lifecycle evidence and a reconciled missed-notification case.
- Verification record: [TASK-011 evidence](TASK-011_EVIDENCE.md). Durable signature-checked callback receipts and transactional database outbox, authoritative QRIS verification, atomic immutable grants/redemptions, safe terminal release, same-order recovery coordination, bounded retries, scheduled reconciliation and review flags are implemented. Synthetic lifecycle/queue/crash/browser checks and real PostgreSQL process races are recorded there. TASK-010's real sandbox QRIS gate and TASK-011's real sandbox lifecycle/missed-notification acceptance remain open because no merchant is configured. No live payment, real message or production publication is claimed.

## TASK-012 — Deliver platform operations screens

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 23, 2026; TASK-006/TASK-008/TASK-011 acceptance remains open)
- Requirements: REQ-002, REQ-013, REQ-014, REQ-016, REQ-017
- Dependencies: TASK-006, TASK-008, TASK-009, TASK-011
- Scope: owner overview, account/suspension screens, plan-price versioning, transaction/voucher history, safe failed-job diagnostics/retry and audited administrative changes.
- Acceptance: overview separates pending from provider-verified paid revenue; no duplicate callback doubles revenue. A price change affects new orders only; suspension/reactivation affects new gallery requests correctly. Retrying jobs rechecks scope/gates. Admin can investigate failures without secrets/raw photo browsing or database edits. Stale admin forms do not overwrite newer data.
- Out of scope: paid gallery-size/count caps, impersonation, arbitrary client-choice editing, private source-Drive management.
- Completion evidence: admin/photographer authorization tests and owner-operated demonstration covering a failed import, ZIP and payment-review record.
- Verification record: [TASK-012 evidence](TASK-012_EVIDENCE.md). Owner-only overview, paginated accounts/project metadata/access/order history, versioned suspension/reactivation and Pro prices, payment-review/history and safe import/ZIP retries are implemented. Domain state, queue dispatch and actor/reason/before/after audit references commit together. PostgreSQL and synthetic browser checks cover authorization, preserved orders, stale forms, verified revenue deduplication, retry gates and safe diagnostics. TASK-006/TASK-008/TASK-011 prerequisite acceptance remains open; no real owner account, live payment, real message, new provider transfer or deployment is claimed.

## TASK-013 — Complete responsive integrated product flows

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 23, 2026; TASK-006/TASK-008/TASK-011/TASK-012 acceptance remains open)
- Requirements: REQ-001, REQ-006, REQ-009, REQ-010, REQ-011, REQ-012, REQ-015, REQ-018, REQ-021, REQ-022
- Dependencies: TASK-006, TASK-008, TASK-011, TASK-012
- Scope: compose complete public/photographer/client/admin journeys; Indonesian launch copy, readable error/freeze states, accessible focus/controls, mobile layouts and truthful job/payment/save feedback. Add the shared light/dark appearance control and browser-preference handling defined in REQ-021 across all SnapPik layouts.
- Acceptance: core flows work at 360px and 1440px without page-level horizontal scrolling; keyboard can select/review/confirm/close preview; password/error states are named and understandable. Slow/failed saves are not shown as committed. Expired account views still expose results and valid downloads; no WhatsApp action is required to finish selection/delivery. Download All stays inside SnapPik's flow.
- Acceptance for appearance: verify REQ-021's system default, explicit preference persistence, keyboard operation, contrast, blocked-storage fallback and state-preservation scenarios in both themes. Check first paint and unchanged photo rendering; do not require control over the provider-hosted checkout's theme.
- Acceptance for design and motion: apply [Design System](../design/DESIGN_SYSTEM.md) components/recipes across every SnapPik shell, including empty/error/locked/frozen states. Verify normal/reduced-motion operation, focus during interrupted transitions, no timer-driven success states, and bounded mounted/animated tiles for a large synthetic gallery. Theme switching must preserve forms, pending saves and jobs.
- Out of scope: multi-language UI, native apps, marketing automation, cross-device appearance synchronization.
- Completion evidence: browser suite results and screenshots in both themes at both target widths, with failure/empty/frozen states inspected, contrast measurements, appearance-preference checks, reduced-motion/focus checks and large-gallery rendering evidence.
- Verification record: [TASK-013 evidence](TASK-013_EVIDENCE.md). Integrated public/pricing/onboarding, photographer navigation/results/billing, client password/selection/delivery and owner operations use shared appearance, state, focus and responsive components. Shared finite Motion recipes, unsaved-configuration warnings, safe themed error pages and Indonesian validation are implemented. A browser-discovered password redirect defect after media/data requests is corrected with explicit project-gate redirects. Local backend and synthetic browser checks are recorded in the evidence; real resource-key and sandbox payment prerequisite acceptance remains open. No additional provider transfer, real message, live payment or deployment is claimed.

## TASK-014 — Verify security, concurrency and recoverability

- Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING (explicitly requested October 23, 2026; TASK-008/TASK-011/TASK-012/TASK-013 acceptance remains open)
- Requirements: REQ-002, REQ-003, REQ-005, REQ-007, REQ-008, REQ-011, REQ-012, REQ-013, REQ-014, REQ-017, REQ-019
- Dependencies: TASK-008, TASK-011, TASK-012, TASK-013
- Scope: targeted cross-boundary adversarial tests, aggregate races, unsafe-source URL rejection, memory/worker restart behavior, cleanup, log redaction and isolated database-backup restore.
- Acceptance: attempt cross-tenant project/file/archive/order access, arbitrary fetch destinations, stale epoch reuse, price manipulation, duplicate callbacks and last-slot races. Each preserves its documented invariant. Worker/process restart cannot publish partial content or duplicate money. A restored backup retains coherent choices/grants/voucher accounting; 24-hour cleanup cannot delete backup objects. Secret/capability scans cover source and representative logs.
- Out of scope: a claim of third-party security certification or unlimited physical provider capacity.
- Completion evidence: recorded reproducible negative/race/recovery checks, large-catalogue/stream results and an isolated restoration record with defects fixed.
- Verification record: [TASK-014 evidence](TASK-014_EVIDENCE.md). Cross-boundary identifier/money/source attacks, lock-wait epoch/capability races, actual import/ZIP/payment process interruption, bounded catalogue/ZIP64 checks, formatted application/emergency log redaction, source/log scanning and an authenticated encrypted PostgreSQL dump/isolated restore are verified locally with synthetic providers. The restore retains all 35 application tables, choices/history, grants, held/consumed/released voucher accounting and durable jobs; replay and immutable-ledger checks pass. Logging, production CSP and a shared payment-history trigger were hardened. Existing provider/prerequisite acceptance and TASK-015 deployment/backup operations remain open; no new live-provider transfer, charge, message or publication is claimed.

## TASK-015 — Prepare and authorize public release

- Status: LOCAL PREPARATION IMPLEMENTED / RELEASE GATES PENDING (explicitly requested October 23, 2026; owner confirmed services unselected and chose local preparation)
- Requirements: REQ-018, REQ-019, REQ-020
- Dependencies: TASK-014
- Scope: production-like configuration, actual host/domain/HTTPS, merchant/channel approval, SMTP, workers/scheduler, private R2, backups, redacted monitoring, legal/support copy and release run instructions derived from implemented commands.
- Acceptance: all release scenarios below pass; missing merchant approval, credentials, source integration proof, functioning workers, backup recovery, or archive integrity blocks public launch. Use sandbox until the owner explicitly authorizes production publication and any live transaction. Report service costs from actual selected configurations, rather than assuming unlimited plans mean free infrastructure.
- Out of scope: unauthorized deployment, service purchases, production charges, or declaring the documentation itself a working website.
- Completion evidence: release checklist with actual environment/commands/results, operator handoff and explicit publication approval.
- Verification record: [TASK-015 evidence](TASK-015_EVIDENCE.md). Read-only release checks, fresh scheduler/worker probes, private operational alarms, streaming GPG/PostgreSQL backups to independently configured private storage, safe owner-document links and local checks are implemented. The [release runbook](../operations/RELEASE_RUNBOOK.md) supplies uninstalled Linux templates, restore/incident procedures, all twelve scenario gates and an actual-service/cost register with unselected inputs explicitly pending. Real off-host/provider/HTTPS/mail/supervision/retention/alert evidence, production payment transport, TASK-014 prerequisite acceptance and final document/publication approvals remain launch blockers. No public release, purchase, real message or live transaction is claimed.

## Requirement coverage

| Requirement | Implementation tasks |
| --- | --- |
| REQ-001 | TASK-001, TASK-003, TASK-013 |
| REQ-002 | TASK-001, TASK-003, TASK-005, TASK-007, TASK-008, TASK-009, TASK-012, TASK-014 |
| REQ-003 | TASK-003, TASK-004, TASK-010, TASK-011, TASK-014 |
| REQ-004 | TASK-004, TASK-006 |
| REQ-005 | TASK-002, TASK-004, TASK-014 |
| REQ-006 | TASK-004, TASK-005, TASK-007, TASK-013 |
| REQ-007 | TASK-005, TASK-014 |
| REQ-008 | TASK-005, TASK-014 |
| REQ-009 | TASK-006, TASK-013 |
| REQ-010 | TASK-002, TASK-007, TASK-013 |
| REQ-011 | TASK-002, TASK-008, TASK-013, TASK-014 |
| REQ-012 | TASK-010, TASK-011, TASK-013, TASK-014 |
| REQ-013 | TASK-009, TASK-010, TASK-011, TASK-012, TASK-014 |
| REQ-014 | TASK-011, TASK-012, TASK-014 |
| REQ-015 | TASK-003, TASK-005, TASK-006, TASK-007, TASK-008, TASK-011, TASK-013 |
| REQ-016 | TASK-009, TASK-012 |
| REQ-017 | TASK-002, TASK-008, TASK-011, TASK-012, TASK-014 |
| REQ-018 | TASK-001, TASK-006, TASK-008, TASK-013, TASK-015 |
| REQ-019 | TASK-001, TASK-003, TASK-004, TASK-007, TASK-009, TASK-014, TASK-015 |
| REQ-020 | TASK-015 |
| REQ-021 | TASK-013 |
| REQ-022 | TASK-001, TASK-013 |

## Integrated release gate

1. A new verified photographer starts one trial, publishes a nested-source selection gallery without phone data, and cannot create a fourth trial project under concurrent requests.
2. Two guest browsers share acknowledged choices, cannot exceed allowance, handle save/confirmation races and see one current locked result. Photographer copies filenames and reopens without losing history.
3. Photographer publishes a separate finished gallery. Password/deadline gates remain independent. Individual downloads match source bytes; Download All yields one complete, inspectable ZIP, including duplicate names and files beyond traditional ZIP bounds.
4. Client closes/reopens during a ZIP job. The same job remains visible. Source failure never produces ready partial output. At 24 hours old output is denied and recreation works.
5. Initial and renewal checkout accept an eligible voucher with an exact server quote. Pending payment grants nothing. Duplicate and concurrent settlements grant/redeem the correct number of times; failed/expired payments release only verified holds.
6. A session spans subscription expiry: client selection becomes frozen, previously saved data remains, valid delivery/ZIP still works, and photographer metadata/export/billing stays readable. Renewal restores only actions otherwise permitted by gallery gates.
7. A paid account exceeds the rejected project/photo/byte thresholds without an application quota failure. Large catalogue and streaming behavior remains bounded in memory; actual provider limitations produce honest errors.
8. Admin can inspect accounts/orders/vouchers/failures, change future price, suspend/reactivate and retry safe work. No admin/tenant bypass, unverified payment grant, permanent source deletion or secret exposure succeeds.
9. Workers/scheduler restart safely; an isolated encrypted-backup restore succeeds; HTTPS, mail, source API, private archive delivery and monitoring are verified in the intended environment.
10. Light/dark switching works across all SnapPik surfaces with persistent browser preference, readable contrast and unchanged selections/forms/photos. Both modes pass the narrow/wide viewport and keyboard checks.
11. The approved shared illustrated direction, landing composition and Motion recipes follow the [Design System](../design/DESIGN_SYSTEM.md); reduced-motion users can complete every core journey. Large catalogues do not trigger catalogue-wide mounting/animation, and interrupted transitions preserve state and focus.
12. All required defects are resolved, actual commands and operating instructions are recorded, production service prerequisites are available, and the owner authorizes publication.

Next Features remain in [PRD](../product/PRD.md); they are not tasks or dependencies of this MVP backlog.

## Public landing follow-up — October 24, 2026

The owner approved the illustrated monochrome/Manrope landing and requested implementation after reviewing its complete Indonesian content. The [decision and verification brief](../design/LANDING_DIRECTION.md) records the implementation, scoped shell/styles, synthetic example and actual checks. This is a presentation follow-up to TASK-013; it does not close that task's prerequisite acceptance, approve the complete documentation package or authorize publication.

The owner then requested extending the accepted direction across the other pages. Shared product tokens/fonts, illustrated authentication, responsive workspace navigation, project/settings cards, billing/admin grouping and client gallery presentation are implemented. The [extension evidence](../design/LANDING_DIRECTION.md#shared-product-extension--october-24-2026) records actual frontend/browser checks and the corrected theme-transition contrast defect. Existing product and launch gates remain unchanged.
