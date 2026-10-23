# SnapPik Product Requirements

**Baseline approved:** October 22, 2026. **Document approval:** see [package state](../index.md). All application behavior below is planned.

## Goal and success

Photographers need a browser gallery through which clients can choose photos for editing and receive finished photos without recording filenames manually. SnapPik serves photographers as subscribers, their clients as link-based visitors, and the owner as platform admin.

Release success means the integrated acceptance checks below pass in a production-like environment, the owner can operate accounts/billing/vouchers, and public launch dependencies in Backlog are satisfied. It does not mean external services can never fail. Failure must be observable and recoverable without losing saved choices or granting incorrect paid access.

## Authoritative commercial and access policy

| Topic | MVP rule |
| --- | --- |
| Trial | Seven consecutive 24-hour days from first successful email verification; at most three unarchived projects, including drafts. No repeated trial after re-registration/login verification for the same account. |
| Pro | Initially IDR 29,000 for 30 consecutive 24-hour days. No auto-debit or recurring mandate. |
| Paid unlimited | No SnapPik plan quota on active project count, gallery photo count, bytes per photo, or bytes per project. The previously discussed 50-project, 2,000-photo, 25-MB and 20-GB limits were rejected. Do not implement them. |
| Client choice allowance | A photographer-defined positive maximum number of choices, independent of unlimited gallery content. Confirmation permits one through the configured maximum; exact-fill selection is not required in MVP. |
| Storage | Source photographs remain in the photographer's publicly shared Drive. SnapPik stores metadata/choices and temporarily copies bytes to create download archives. It provides no hosted-photo upload allocation in MVP. |
| Renewal | A verified purchase activates Pro immediately and adds the purchased 30-day duration to the later of its verified payment time and the account's existing access end, including remaining trial time. A lapsed account starts from verified payment time. No time is credited for pending payments. |
| Plan changes | Admin price changes affect newly created orders. An existing order retains its plan version, price, duration, and valid voucher snapshot. No retroactive repricing. |
| Expired subscription | Photographer can log in, view metadata/results, copy results, and renew. Project creation, configuration, catalogue refresh, publication, archive, reopening and client-choice writes are blocked. Existing published client galleries remain viewable/downloadable, subject to gallery gates. |
| Gallery gates | Module publication, project archive state, account suspension, password and module deadline are evaluated independently of paid expiry. A closed/expired module cannot be accessed merely because the account is paid. |
| Retention | Subscription expiry never automatically deletes project metadata or choices. Original Drive files are never deleted by SnapPik. Temporary ZIPs expire 24 hours after readiness and are cleaned up; this is distinct from project retention. |
| Single archive | Download All means one complete ZIP of the published delivery catalogue, including its supported photos in subfolders. Never substitute several ZIP parts or a Drive-folder redirect. |
| Voucher | One percentage or fixed-IDR voucher per order; available for both initial purchases and renewal. It must leave a payable amount that meets the enabled payment channel's minimum. No free/100% voucher flow. |

Unlimited is a product entitlement, not an assertion of infinite infrastructure. Provider errors and documented native capacity limits must be explained and recoverable where possible. Concurrency/rate controls may protect the service, but must not be disguised numerical plan caps or silently truncate catalogues/archives.

## Actors and authorization

| Actor | Allowed scope |
| --- | --- |
| Platform admin | Owner-only role, provisioned outside public registration. View operations, accounts, subscription/order history, project metadata and failures; suspend/reactivate accounts, version the Pro price, manage vouchers, retry safe failed jobs. Changes are audited. No impersonation, arbitrary client-choice editing, manual mark-paid shortcut, or source-Drive deletion. |
| Photographer | One account owns its projects/catalogues/results/orders. While entitled, create/configure/publish/archive projects and reopen client choices. No studio-team or cross-photographer access in MVP. |
| Client | Capability-link access to one project's published module, plus password when configured. Can read allowed previews; choose/confirm while entitled and open; download allowed delivery photos/ZIPs. No account or billing/admin access. |
| Background system | Acts only on persisted, scoped job/order identities. Cannot acquire rights from a browser request claiming an owner or payment result. |

Suspension is different from ordinary subscription expiry: a suspended photographer may sign in to see account status and read their billing history, but mutations/checkout and all public galleries are disabled until admin reactivation. Admin receives metadata and operational diagnostics by default; no general original-photo browsing or bulk-download privilege is added.

## Main flows

| Flow | Actor, trigger and preconditions | Success and data effects | Failure/cancel behavior |
| --- | --- | --- | --- |
| Onboard | Photographer registers and verifies a reachable email | Verified account obtains its one trial; sessions support login/logout/reset | Invalid/expired verification or reset token does not create another trial; cancelled registration has no project effect |
| Publish selection | Entitled photographer creates a project and supplies a public Drive folder | Background import completes a catalogue revision; photographer publishes the selection module and shares its opaque link | Invalid/private source or interrupted import leaves a draft/previous revision, with retry guidance; no partially imported public catalogue |
| Select and confirm | Client passes gallery gates, account access is active, selection is open | Per-photo changes autosave; review shows canonical choices; confirmation locks a nonempty selection within allowance | Network failure shows unsaved/conflict state; expired access or concurrent confirmation rejects later writes without erasing prior choices |
| Review results/reopen | Owner photographer views results and, while entitled, explicitly reopens | Saved filenames are copyable; reopening retains choices and permits editing under a new version | No other photographer can reopen; lapsed account can only read/copy; client sees lock/freeze reason |
| Deliver | Entitled photographer supplies a separate finished-photo folder and publishes delivery | Client passes delivery gates, downloads one file or requests one complete ZIP | A missing file or unavailable source produces an actionable failure; never silently skip files or open Drive as fallback |
| Subscribe with voucher | Verified, unsuspended photographer checks out at a server-quoted price | Pending order/reservation then provider-verified payment, one redemption and one access grant | Invalid/exhausted voucher leaves checkout editable; provider timeout stays reconcilable; failed/expired orders release reservations after verification |
| Operate | Platform admin opens account/payment/voucher/job screens | Audited changes and safe job retries | Permission errors, stale forms, capacity races and integration errors are visible; no automatic financial side effects from viewing screens |

## Functional requirements

### REQ-001 — Public entry and photographer authentication

- Actor/trigger: a visitor reads the product/pricing entry pages or registers, signs in/out, verifies email, or requests password recovery.
- Behavior: show SnapPik's actual launch offering and gates; use email/password account authentication, email verification, expiring one-use reset tokens, and generic account-recovery responses. A valid verified session is required for photographer management and checkout.
- Data/authorization: public registration can create only a photographer role. Trial start is recorded once after verification. Passwords are hashed; credentials and raw tokens never enter operational logs.
- Failure/cancel: invalid credentials, duplicate email, expired token, mail failure and logout have explicit outcomes. Mail failure provides retry without starting another trial. Pending unverified accounts cannot publish or pay.
- Acceptance: verification starts exactly one trial despite repeated callbacks; a reset token cannot be replayed; public role injection cannot create an admin; logout invalidates that session.

### REQ-002 — Tenant isolation and central access decisions

- Actor/trigger: any account or guest request addresses a project, module, media item, result, order, voucher action, or job status.
- Behavior: enforce the actor table and commercial policy on the server. An opaque project link grants only that project's client functions; it does not grant photographer access. Derive ownership from authenticated identity/persisted relations, never from caller-supplied owner IDs.
- Data/authorization: all tenant records are scoped to their owning photographer; guest modules additionally use the capability, password session, publication state and deadline. Admin routes require the admin role.
- Failure/cancel: unauthorized resources return a generic denial without names, filenames, choices, or payment data. Suspension overrides subscription status and client links.
- Acceptance: changing project/file/job/order identifiers cannot cross tenants; an authenticated photographer opening another client's link gains only the same guest rights as a client; a suspended account's existing link is denied.

### REQ-003 — Trial, paid entitlements and unlimited content

- Actor/trigger: verification starts trial; project creation/restoration checks allowance; verified payment changes access.
- Behavior: implement the policy table using server time. Count drafts and published unarchived projects against the trial's three-project allowance. Archived projects do not expose public galleries. Paid users have no content/project size/count quota in application logic or database validation.
- Data/authorization: trial and paid access periods are durable and derived from grants; trial count check and project creation are atomic per photographer.
- Failure/cancel: concurrent fourth-project attempts cannot both pass; an unsuccessful create releases its draft slot only when the draft is explicitly archived or the create transaction failed entirely. Login does not reset trial.
- Acceptance: a trial account cannot have four counted projects after concurrent requests; paid accounts can exceed 50 active projects and 2,000 photos and can represent files/projects beyond the rejected byte thresholds; no storage tier is advertised at launch.

### REQ-004 — Project lifecycle and separate gallery configuration

- Actor/trigger: an entitled photographer creates, edits, publishes, unpublishes or archives a project.
- Behavior: collect a client/project name, selection source, positive choice allowance, optional module passwords and deadlines. WhatsApp contacts are optional. Delivery can be configured/published later using a separate, non-overlapping source folder. One project contains both modules; configuring delivery does not consume a second trial slot.
- Data/authorization: states are draft, published and archived; each module has an independent publication flag and gate settings. Archive closes client access and retains metadata/results. Unarchive is an entitled action subject to trial allowance. No source file is moved, renamed or deleted.
- Failure/cancel: cancellation does not publish; unsaved edits are identified. Reducing the allowance below existing choices, overlapping source catalogues, or replacing the selection source while its confirmation is locked is rejected with resolution guidance. Reopen before replacing a selection source; source changes never silently discard saved choices. A confirmed selection does not prevent configuring the separate delivery source.
- Acceptance: a usable project can be created without a phone number; selection and delivery gates operate independently; archiving closes both modules; unarchiving cannot bypass the trial cap.

### REQ-005 — Public Drive catalogue import and refresh

- Actor/trigger: an entitled photographer supplies a public folder link or requests refresh.
- Behavior: validate a Google Drive folder link, discover JPEG/PNG photos and descendant folders, page through all provider results, and build a complete revision before publication. Preserve names, folder paths, stable file IDs and relevant versions. Unsupported files are reported and excluded from the photo catalogue; shortcuts are reported rather than traversed outside the configured tree.
- Data/authorization: Drive is read-only. SnapPik metadata is a versioned snapshot, not an automatic continuous mirror. Manual refresh publishes atomically after successful import; previously saved selections reference stable identities and retain their filename snapshots.
- Failure/cancel: private/removed/empty folders, inaccessible descendants, resource-key failures and quota errors identify an appropriate remedy. Never call a partial traversal complete. A failed refresh retains the previously published revision, marks it stale, and identifies affected unavailable photos.
- Acceptance: a root with only subfolders works; import crosses multiple API pages without truncation; duplicate filenames remain distinct; an interrupted refresh leaves the previous revision usable and never resets choices. Removing a selected file preserves its result record and shows an unavailable warning rather than erasing the choice.

### REQ-006 — Client gallery gates and navigation

- Actor/trigger: a client opens the shared project link or enters a module.
- Behavior: show only published/available modules, a grid with folder navigation, filenames, enlarged preview, selection count where relevant, and clear loading/error/empty states. Each password session is scoped to one project/module. A missing password remains optional, not a validation error. Configured deadlines deny new access when server time reaches the deadline; no deadline means no module deadline.
- Data/authorization: gallery passwords are hashed. Password/gate changes invalidate previous module sessions. Module deadlines and subscription expiry have distinct messages/behavior. Exclude client galleries from search indexing.
- Failure/cancel: incorrect passwords are throttled; unavailable previews offer retry; closing zoom does not change choices. Capability tokens must not leak to analytics/referrer logs. Photo-source privacy limitations are disclosed to the photographer.
- Acceptance: a selection password does not unlock delivery with a different password; cached media URLs cannot bypass a newly denied request; an expired selection module does not close a still-valid delivery module. Renewal alone does not extend a module deadline.

### REQ-007 — Shared autosaved selection and allowance enforcement

- Actor/trigger: a client selects or deselects a photo while selection is open and the photographer has current trial/paid access.
- Behavior: maintain one shared set per project, not per person/browser. Each acknowledged mutation persists on the server and returns the canonical count/version. Show saving, saved, failed and conflict states. Reload restores acknowledged choices. Clients can choose across subfolders up to the photographer's allowance.
- Data/authorization: accept only eligible file identities from the project's revision. Duplicate requests are idempotent; concurrent changes cannot exceed allowance. Stale edits are rejected/refreshed rather than replacing the whole set.
- Failure/cancel: a failed request is never displayed as saved. When access expires or confirmation wins a race, subsequent mutations fail with the new read-only/locked state. No offline-write queue silently replays changes after a lock.
- Acceptance: simultaneous attempts at the last slot yield one valid canonical set; retrying the same selection does not double-count; a client reload and photographer view show the same acknowledged choices.

### REQ-008 — Review, confirmation and reopening

- Actor/trigger: client reviews/explicitly confirms, or entitled photographer explicitly reopens a confirmed selection.
- Behavior: review shows the canonical selected photos and allows returning to editing. Confirmation requires one through the allowance, verifies the current set/version, records a snapshot/time and locks mutation. Reopening retains the choices, preserves earlier confirmation history, increments the edit version and clears the current confirmed state.
- Data/authorization: only that photographer can reopen. Reopening is blocked on expired/suspended accounts and does not bypass module deadlines. Client cannot reopen from a stale request or merely by revisiting the link.
- Failure/cancel: cancelling confirmation changes nothing. A stale review must refresh before confirmation. Repeat confirmation of the same version returns the same result without another snapshot.
- Acceptance: zero choices cannot confirm; one of an allowance of two can confirm; a write racing confirmation cannot modify its snapshot; reopening preserves prior choices and a historical result without leaving a misleading current-confirmed badge.

### REQ-009 — Photographer progress and filename export

- Actor/trigger: photographer opens dashboard/project results or copies filenames.
- Behavior: display not-started, selecting, confirmed and frozen/closed context without conflating them. Show selected/allowed count and last acknowledged update; poll/refresh can provide current state without requiring a websocket service. Copy the full original filenames, with folder paths where needed to disambiguate duplicates, one entry per line.
- Data/authorization: snapshots and live draft state are distinguished. Expired photographers retain read/copy access to their own saved results. Unknown/unavailable original files remain visible in historical results.
- Failure/cancel: failed refresh keeps the last known data marked stale; clipboard rejection leaves a selectable text fallback. Copying never locks or alters the selection.
- Acceptance: draft choices appear before confirmation; reopening changes the current status; identically named files remain identifiable; an expired account can copy its historical list.

### REQ-010 — Finished-photo delivery and individual download

- Actor/trigger: entitled photographer publishes a delivery revision; client requests one photo.
- Behavior: deliver finished JPEG/PNG files from the distinct delivery catalogue. Browser downloads originate through SnapPik; no Drive-folder navigation or login is required for a publicly accessible source. Preserve original bytes and safe filenames.
- Data/authorization: every request checks that the photo belongs to the authorized delivery revision and satisfies current delivery gates. A subscription lapse alone does not block delivery reads/downloads.
- Failure/cancel: revoked Drive access, unavailable files and download restrictions return a visible failed state. Do not return an HTML error document disguised as a JPEG or silently switch to a lower-resolution preview. Interrupted transfers can retry; range requests must recheck authorization.
- Acceptance: a downloaded file's bytes match the source fixture; another project's file ID is denied; module expiry blocks new requests; account expiry alone still permits the same delivery download.

### REQ-011 — Complete single-ZIP download

- Actor/trigger: authorized client selects Download All in a published delivery module.
- Behavior: freeze a manifest of every supported photo in that revision; create one ZIP in a background worker. Show queued, processing with file progress, ready, failed and expired states. The job continues if the browser closes. Use ZIP64 when necessary; all requested files must be present before readiness.
- Data/authorization: private temporary archive objects are scoped to project/module/revision. Reuse an authorized ready archive for the same manifest within its 24-hour lifetime. A retry or repeated click does not start duplicate identical jobs. ZIP download rechecks current gallery access, even after account expiry.
- Failure/cancel: one missing/changed/failed source file fails the complete archive; do not publish a partial success. Clean incomplete output. Failed/expired jobs can be recreated after the source is available. Never split into multiple archives or redirect to Drive. Underlying provider limits are explicit errors, not hidden paid-plan quotas.
- Acceptance: expected and archived file identities/counts match; duplicate filenames are retained safely; source failure cannot produce a ready incomplete archive; a source larger than 4 GiB uses one readable ZIP64 archive; a page reload restores job status; an expired archive is not downloadable and can be requested again.

### REQ-012 — Server-priced checkout and order lifecycle

- Actor/trigger: verified, unsuspended photographer buys Pro or renews, optionally applying one voucher.
- Behavior: show plan price, discount and final payable IDR amount before redirecting/opening Midtrans checkout. The server calculates integer money, snapshots the plan/voucher, reserves voucher capacity, and creates one order under an idempotency key. QRIS is the required launch payment channel; additional channels are not required by MVP.
- Data/authorization: order is bound to the photographer's immutable account ID, not a browser email field. Return visits can reopen/reconcile that same pending order. No automatic repeat billing or hidden customer fee is added; gateway charges are an operator expense for launch.
- Failure/cancel: invalid/expired/exhausted voucher leaves checkout editable. Provider creation timeout retains an unknown/pending order for reconciliation, rather than making a duplicate. Leaving a checkout page is not evidence of provider cancellation. Unknown payment state grants no access.
- Acceptance: changing client-submitted amounts/codes/owner IDs cannot reduce payment; repeated submit creates one provider order; pending checkout can resume after reload; displayed amount equals provider gross amount and persisted snapshot.

### REQ-013 — Voucher administration and redemption policy

- Actor/trigger: admin creates/edits/deactivates a voucher; photographer applies it during checkout.
- Behavior: configure a unique code, percentage or fixed IDR amount, start/end instants, total-use limit, per-user limit and enabled state. Percentages are whole numbers 1–99; fixed amounts are positive whole IDR. Admin can omit total/per-user limits for unlimited uses; these are voucher-use limits, not gallery limits. New code availability is start-inclusive/end-exclusive.
- Data/authorization: normalize codes by trim and uppercase; retain immutable versions for orders. Both initial purchase and renewal are eligible. One voucher per order; no stacking, cashback, transfer or free-order route. Discount is rounded down to whole IDR; reject any result below the channel's configured minimum or at/below zero rather than silently clipping it.
- Failure/cancel: invalid admin values cannot save. Concurrent checkout must reserve the last eligible total/per-user use atomically. Only verified successful payment consumes a redemption; provider-verified failure/expiration releases its reservation. An edited/deactivated voucher cannot reprice an existing valid pending order. Settled usage is not silently restored after an external refund.
- Acceptance: two buyers racing one remaining use cannot both reserve it; one user cannot evade a per-user limit with two pending orders; failed payment releases capacity; duplicate settlement consumes one use; 100%/zero-pay outcomes are rejected. With base IDR 29,000, 10% yields IDR 2,900 discount and IDR 26,100 payable.

### REQ-014 — Verified payments, renewals and reconciliation

- Actor/trigger: Midtrans callback, scheduled reconciliation, or manual safe status recheck reports an order state.
- Behavior: verify callback authenticity and confirm authoritative provider state, merchant/order identity, IDR currency, and exact gross amount before recording payment. Only verified successful settlement grants access. Apply one order's grant and voucher redemption atomically; serialize concurrent successful orders for the same account. Use the policy-table renewal calculation with authoritative verified paid time.
- Data/authorization: enforce one grant and one redemption per order. Keep a durable payment event/result history and immutable purchase terms. A browser redirect or admin edit cannot mark an order paid. Pending or older failed events cannot overwrite an established success.
- Failure/cancel: invalid signatures and mismatched amounts never grant. Lost/delayed/duplicate/out-of-order callbacks are reconciled without double access. A late settlement is checked against provider history and the order's original voucher snapshot; it is not discarded merely because a local timer elapsed. Unexpected refund/reversal/dispute states are flagged for admin review, not treated as a new successful payment.
- Acceptance: repeated settlement extends once; two different concurrent paid orders extend twice without lost days; an unpaid pending order grants nothing; a delayed legitimate payment retains its contracted discount and duration. External refund execution and automatic entitlement clawback are outside MVP; review remains an operator action with an audit trail.

### REQ-015 — Expiry and reactivation behavior

- Actor/trigger: server time crosses trial/paid end, or a verified renewal restores access.
- Behavior: use the policy table on every relevant request, not only a scheduled status badge. Preserve acknowledged draft choices, confirmations, projects and delivery catalogues at expiry. Clients become read-only for selection and can still access eligible previews/downloads, including requesting a new ZIP. Renewal unfreezes only the actions otherwise permitted by module state/deadlines/confirmation.
- Data/authorization: expiry itself does not change gallery deadlines, unlock confirmed selections, publish drafts, or delete records. Ordinary expiry does not hide billing/result history from the photographer.
- Failure/cancel: writes that arrive after access end are rejected even if the page opened earlier; pending payment alone cannot unfreeze. Existing authorized responses can finish; every new request rechecks gates.
- Acceptance: a session spanning expiry cannot mutate choices; the same delivery remains downloadable; after renewal an unconfirmed and unexpired selection can resume, while a confirmed or deadline-expired one remains locked/closed.

### REQ-016 — Platform administration and operational visibility

- Actor/trigger: platform admin opens dashboard, account, plan, order/voucher history, or job screens.
- Behavior: show registered/trial/paid/expired/suspended accounts, project/activity aggregates, provider-verified revenue/order state, voucher reservations/redemptions, and failed imports/ZIPs. Support account suspension/reactivation, Pro price versioning, voucher management and idempotent job retry. Do not expose a paid content-count/byte cap setting.
- Data/authorization: require owner-admin role; record actor/time/reason and before/after references for administrative changes. Display only needed metadata; no impersonation or arbitrary original-file download console.
- Failure/cancel: stale administrative edits must not overwrite newer versions; failed jobs show actionable error class without secrets. A retry cannot skip current authorization or change settled order amounts.
- Acceptance: photographer cannot access admin screens/endpoints; price edits preserve old orders; suspend/reactivate affects new gallery requests immediately; operational totals distinguish pending from paid and avoid duplicate revenue.

### REQ-017 — Background recovery without hidden content caps

- Actor/trigger: imports, ZIP builds, webhook processing or cleanup encounter interruptions or provider limits.
- Behavior: use persistent jobs, bounded retries, worker leases and visible recoverable failure. Process catalogues/bytes incrementally with bounded memory and pagination; operational scheduling/rate control does not truncate content. Archive cleanup makes 24-hour-old ready output inaccessible immediately and removes temporary objects through retryable cleanup.
- Data/authorization: publish revisions/archives only after complete success; orphaned partials and expired objects are tracked for cleanup. Provider requests use a scoped adapter with approved destinations.
- Failure/cancel: restarts recover or explicitly fail abandoned work; browser closure does not cancel server ZIP work. Provider failure never credits a payment or clears choices.
- Acceptance: kill/restart an import/ZIP worker and observe a recoverable consistent job; run large metadata fixtures without count truncation; expired archive access is denied even if object deletion is delayed.

### REQ-018 — Responsive and understandable interaction states

- Actor/trigger: photographer/admin/client uses the web app on a narrow or wide viewport.
- Behavior: use readable Indonesian launch UI and browser-native downloads; docs stay English. Core flows work at 360px and 1440px viewport widths without page-level horizontal scrolling. Buttons/fields have names, keyboard focus and inline error feedback; selection and failure are not conveyed by color alone.
- Data/authorization: displayed save/progress/payment states reflect the last canonical server acknowledgement. No claim of offline editing or instant source synchronization.
- Failure/cancel: slow loads, empty folders, frozen selection, denied password and failed payments/jobs provide a clear next action. Copy failure supplies text fallback. Navigating away from unsaved configuration warns before discarding.
- Acceptance: keyboard can select/review/confirm and close preview; narrow-screen pages expose all core actions; simulated failed save is visibly unsaved; no extra-app installation or WhatsApp message is required for success.

### REQ-019 — Data ownership, privacy and operational safeguards

- Actor/trigger: any workflow stores/serves data or an operator restores service.
- Behavior: source files belong to their Drive owner; SnapPik owns the authoritative metadata/selection/billing records for its functions. Explain that a public Drive source remains accessible to someone with its source link regardless of a SnapPik password. Keep public source links out of client navigation and indexing; this does not promise source secrecy.
- Data/authorization: hash passwords, protect sessions, prevent CSRF/injection/SSRF, throttle abusive requests, keep secrets outside repository, redact logs and isolate private archives. Preserve financial/audit history and backup recoverability. Archive instead of permanently deleting project data in MVP; self-service permanent account erasure is not shipped in this scope.
- Failure/cancel: operational or billing errors must not expose credentials, tokenized links, client selections or other photographers' data. Backups are restored in isolation before being trusted.
- Acceptance: security-negative tests cover direct-object access and unsafe fetch destinations; secret scans find no reference credentials; an isolated backup restore preserves grants, voucher accounting and saved selections.

### REQ-020 — Public-release verification

- Actor/trigger: operator prepares public publication.
- Behavior: complete Backlog's integrated release gate using sandbox payment evidence, an authorized public Drive smoke test, private archive storage, running workers/scheduler, configured transactional email, HTTPS, backups and error monitoring. Owner supplies actual legal/support content and production provider/domain configuration before launch.
- Data/authorization: deployment and real transactions require separate authorization; this documentation approval does not purchase services or publish anything.
- Failure/cancel: unresolved permission, payment, archive-integrity, secret-leak or recovery failures block release. Provider approval/credentials that are absent are deployment blockers, never described as already connected.
- Acceptance: recorded end-to-end evidence proves photographer onboarding through client selection/delivery and discounted renewal; restart/retry/duplicate callback cases pass; owner can locate a failed job and account history without direct database edits.

### REQ-021 — Light and dark appearance

- Actor/trigger: any visitor, photographer, client or platform admin changes the appearance using a visible, keyboard-accessible light/dark control.
- Behavior: provide both themes across SnapPik's public, authentication, photographer, client and admin pages, including previews, dialogs, forms and loading/error states. On a browser's first visit, follow its system preference; an explicit light/dark choice overrides that default and persists across navigation and reloads in that browser. Theme choice remains available when a subscription expires.
- Data/authorization: appearance is a browser preference, available without login and independent of the shared project selection. It does not change access rights, saved choices, gallery configuration or other visitors' appearance. Cross-device account synchronization is outside MVP. Original photos retain their colors and bytes; do not recolor or dim them to implement dark mode.
- Failure/cancel: if preference storage is unavailable, switching still works for the current page without blocking gallery or account actions. Avoid a contrasting-theme flash during initial page loading. Third-party hosted payment screens follow the provider's available appearance controls; do not promise SnapPik's theme there.
- Acceptance: switch themes with mouse and keyboard on public, photographer, client and admin views at 360px and 1440px. Reload/navigation retain an explicit choice; a fresh browser follows its system preference. Text meets WCAG AA contrast (4.5:1 normal text, 3:1 large text); controls/focus and selected/disabled/error states remain distinguishable in both themes. Switching while choices or a form draft are present preserves their state and does not interrupt a download or pending job. Disabled browser storage does not break the page.

### REQ-022 — Consistent illustrated presentation and accessible motion

- Actor/trigger: any visitor, photographer, client or platform admin navigates or interacts with SnapPik.
- Behavior: apply the owner-approved illustrated monochrome direction through the shared [Design System](../design/DESIGN_SYSTEM.md): neutral photo-led layouts, Manrope/DM Sans typography, rounded components, original pastel-accented illustrations and light/dark visual tokens. Use Motion for React for coordinated interaction transitions across public, authentication, photographer, client and admin views. Respect system reduced-motion preferences; essential content and actions never depend on an animation finishing.
- Public landing amendment approved October 24, 2026: the public marketing entry uses the scoped monochrome/Manrope, rounded pastel-illustration treatment and pausable illustration animation defined in the Design System. The owner subsequently requested extending the approved visual language to authentication, photographer, client and administrator pages, with task-appropriate layouts and finite operational illustration motion. This visual/content amendment does not change any product entitlement, provider boundary or launch gate.
- Data/authorization: animation presents application state without changing selections, allowances, permissions, quoted prices or payment/job outcomes. Keep photographs unmodified. Do not show a save, payment or ZIP as successful before the canonical server result.
- Failure/cancel: interrupted animation or navigation must not lose acknowledged choices, remount a form unnecessarily or block access to an action. Large catalogues animate only mounted visible content; loading/windowing must not impose a product photo-count cap. Reduced-motion sessions remove travel, stagger and decorative loops while preserving understandable status.
- Acceptance: shared components and core journeys follow the Design System in both themes at 360px and 1440px. Keyboard/touch users can finish the same flows with normal and reduced motion; dialog focus returns correctly. A large synthetic gallery avoids catalogue-wide animation/mounting; slow/failed requests retain honest pending/error state. Inspect theme switching and interrupted transitions with form, selection and job state present.

## Next Features — explicitly outside MVP

| Future area | Intent and deferred decisions |
| --- | --- |
| SnapPik-hosted photo upload | Upload through SnapPik into the owner's personal Google Drive; storage-based subscription tiers differ by total account GB allocation, while project/photo counts remain unlimited. Capacity, tier pricing, per-user accounting, owner OAuth, retention and migration require a later approved specification. Temporary ZIP storage in MVP is not this feature. |
| Batch and project folders | Spreadsheet/multi-row imports, organizational nesting, drag-and-drop and bulk actions. Their successful reference tests do not authorize MVP implementation. |
| Extra photo selection | Separate extra allowance and primary-choice locking; pricing/payment for extras remains undecided. |
| Print selection | Size/quantity templates, per-size allowances and optional exact-fill confirmation. No physical printing/fulfilment workflow is approved. |
| Photo notes | Per-photo editing instructions and their presentation/export. |
| Notifications/integrations | Telegram, automated WhatsApp, Client Desk, NAS/WebDAV and custom domains. Sharing a copied link manually is sufficient in MVP. |
| Additional recommendations | Branded covers, richer selection history, multi-language UI, team accounts and advanced analytics can be considered after the MVP; none are release dependencies. |

No RAW/video processing, photo editing, AI selection, physical fulfilment, mobile native app, recurring auto-debit, multi-user studio roles, self-service refunds or platform impersonation is included. Product behavior must not be expanded by reference screenshots or backlog tasks.
