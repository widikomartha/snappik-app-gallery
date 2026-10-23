# SnapPik Project Structure

**Classification:** TASK-001 foundation, TASK-003 authentication/access policy, TASK-002 local/test provider prototype, TASK-004 project/catalogue, TASK-005 client selection/preview, TASK-006 progress/history/export, TASK-007 finished-gallery/individual-download, TASK-008 complete delivery ZIP, TASK-009 owner vouchers, TASK-010 checkout/reservations and TASK-011 verified payments/reconciliation implementations exist. TASK-004–TASK-008 acceptance awaits TASK-002's real resource-key fixture; TASK-010 awaits a real sandbox QRIS demonstration. TASK-011 also awaits real sandbox lifecycle acceptance. [Package state](../index.md).

## Existing documentation

```text
README.md
AGENTS.md
docs/
  index.md
  product/PRD.md
  design/DESIGN_SYSTEM.md
  engineering/ARCHITECTURE.md
  engineering/PROJECT_STRUCTURE.md
  planning/BACKLOG.md
  research/FASTPIK_ANALYSIS.md
```

## Existing application foundation

The root now contains generated `artisan`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/`, Laravel `config/`, `public/`, `storage/`, and the default users/cache/jobs migrations, factory and empty seeder. `app/Http/Middleware/` contains the Inertia and security-header middleware. `routes/web.php` defines public/authenticated/admin/project boundaries and scoped capability routes; `routes/console.php` schedules archive maintenance every minute.

`resources/views/app.blade.php` owns the before-paint theme initializer and Inertia entry document. `resources/js/app.tsx`, `lib/appearance-init.js`, `lib/appearance.ts`, `lib/motion.ts`, `layouts/shell.tsx`, `components/ui/`, and `pages/{public,auth,photographer,admin,client}/` establish the shared presentation foundation. `resources/css/{tokens,app}.css` holds the theme tokens and styles. Self-hosted font licenses are in `public/licenses/`.

Real `composer.json`/`composer.lock`, `package.json`/`package-lock.json`, environment examples, TypeScript/Vite/Vitest/Playwright configuration and `.github/workflows/ci.yml` exist. Tests live in `tests/Feature/`, `tests/Browser/`, `tests/Integration/`, `tests/Support/` and frontend `*.test.ts(x)` files. Local downloaded runtimes and generated screenshots are ignored, not source deliverables.

TASK-003 adds `Actions/Auth/{VerifyAccount,ResetAccountPassword,SetAccountSuspension}.php`, `Http/Controllers/Auth/AccountController.php`, `Models/AccountAccess.php`, `Notifications/AccountLink.php`, `Services/Auth/AccountMail.php`, `Services/Access/{AccountAction,AccountPolicy,GalleryGate}.php`, `Policies/TenantResourcePolicy.php`, `Contracts/PhotographerOwned.php`, `Console/Commands/ProvisionAdmin.php` and additive account-access/audit migrations. `resources/js/components/auth-form.tsx` and `pages/auth/` contain the functional account forms. The dedicated `playwright.auth.config.ts` uses an isolated PostgreSQL test database for synthetic registration. PostgreSQL process-race workers live only under `tests/Support/`. These paths implement the account policy used by the checkout/payment Actions documented below. TASK-009 voucher paths are recorded below.

TASK-002 adds `app/Contracts/{DriveSource,ArchiveStorage,ArchiveUpload}.php`, `app/Integrations/{GoogleDrive,ArchiveStorage}/`, `app/Services/{Drive,Archives}/`, sanitized `ProviderFailure`, `app/Jobs/BuildDriveProofArchive.php`, `app/Models/DriveProof{Run,Node}.php`, their isolated PostgreSQL migration, `config/drive_proof.php` and `app/Console/Commands/DriveProof.php`. Dependency bindings live in `AppServiceProvider`. Provider snapshots remain inside adapters/encrypted prototype metadata; no provider code is imported by React. The command and prototype orchestration are local/test-only; these paths do not imply real-provider verification or finished gallery jobs.

`app/Services/Drive/DriveRequestPacer.php` now coordinates provider request starts through the shared cache/lock. `AppServiceProvider` injects its callback into the Drive adapter, keeping timing state out of controllers, models and UI code. Its operational interval lives in `config/drive_proof.php` and `.env.example`; tests cover independent instances, safe failure and pacing on retries/EOF metadata checks.

TASK-004 adds `Actions/Projects/ManageProject.php`, `Http/Requests/ProjectConfiguration.php`, `Http/Controllers/Photographer/ProjectController.php`, `Http/Controllers/Client/GalleryController.php`, `Models/{Project,GalleryModule,CatalogueRevision,CatalogueEntry}.php`, `Services/Drive/ImportProjectCatalogue.php`, `Jobs/ImportCatalogue.php` and the additive project/catalogue/choice-snapshot migration. `pages/photographer/{projects,project,project-diagnostics}.tsx` provide project management and bounded diagnostics; `pages/client/project.tsx` provides gated module status/password entry. The `catalogues` database queue connection in `config/queue.php` deliberately shares domain PostgreSQL transactions. `tests/Support/{CatalogueDrive,project-browser-fixture}.php` and `playwright.projects.config.ts` support synthetic queue/browser checks, with no production fixture routes. TASK-005 extends these boundaries with selection/photo previews below; finished-photo downloads remain TASK-007.

TASK-005 adds `Actions/Selections/ManageSelection.php`, `Services/Access/SelectionAccess.php`, `Http/Controllers/Client/SelectionController.php`, `Contracts/DrivePreviewSource.php`, `Services/Drive/PreviewImage.php`, the additive selection-history/receipts/parent-identity migration and `pages/client/selection.tsx`. `PublicDriveSource` also implements the preview contract; provider URLs remain adapter-only. `tests/Feature/ClientSelectionTest.php`, `tests/Integration/SelectionConcurrencyTest.php`, `tests/Support/{SelectionFixture,selection-race-worker}.php`, `tests/Browser/selection.spec.ts` and `playwright.selection.config.ts` contain synthetic HTTP/browser and independent-process PostgreSQL checks. Confirmation snapshots/receipts use transactional database rows; no unused domain model scaffolding is introduced.

## Target application layout

The application uses Laravel at the workspace root with React/TypeScript through Inertia. Framework-owned files were generated from the official Laravel skeleton. The following remains a placement contract for the complete MVP; compare with the existing-foundation section before assuming a domain path exists. Do not create unused directories or implementations merely to match this tree.

```text
app/
  Actions/
    Projects/        # Create, archive, publish, refresh catalogue
    Selections/      # Per-photo mutation, confirmation, reopening
    Billing/         # Checkout, voucher reservation, payment application
  Contracts/        # Drive, payment gateway, archive storage boundaries
  Integrations/
    GoogleDrive/
    Midtrans/
    ArchiveStorage/
  Http/
    Controllers/
      Admin/
      Photographer/
      Client/
      Webhooks/
    Middleware/
    Requests/
  Jobs/             # Catalogue import, ZIP creation, reconciliation, cleanup
  Models/
  Policies/
  Services/         # Shared catalogue, access, money, archive primitives
config/             # Service configuration; no secrets committed
resources/css/
  app.css           # Shared styles and semantic component classes
  tokens.css        # Light/dark token definitions from Design System
database/
  migrations/
  factories/
  seeders/          # Synthetic local data; no production admin password
resources/js/
  components/       # Shared accessible controls and gallery components
    ui/             # Buttons, fields, dialogs, appearance control, status primitives
  hooks/            # Shared browser appearance and responsive behavior
  lib/
    appearance.ts   # Validated browser preference and root theme application
    motion.ts       # Shared Motion recipes and timing; reduced-motion variants
  layouts/
  pages/
    public/
    auth/
    admin/
    photographer/
    client/
  types/
routes/             # Session web routes and isolated webhook route
tests/
  Unit/
  Feature/
  Integration/      # Provider adapter contracts and sandbox checks
  Browser/          # Complete photographer/client/admin flows
storage/            # Framework/runtime scratch, never a public photo library
docs/               # Existing authoritative documentation
```

TASK-006 adds `Services/Selections/ProjectProgress.php`, `Http/Controllers/Photographer/ProgressController.php`, the additive acknowledgement-time migration, `pages/photographer/results.tsx`, `components/project-progress.tsx`, `hooks/use-progress.ts` and `lib/progress.ts`. Existing dashboard/project-list pages compose the shared progress view. `tests/Feature/PhotographerProgressTest.php`, `tests/Support/progress-browser-fixture.php`, `tests/Browser/progress.spec.ts` and `playwright.progress.config.ts` provide isolated state-transition/export/browser verification. Filename export uses private temporary text storage and existing selection snapshots; no provider integration or unused domain model is introduced.

TASK-007 adds `Http/Controllers/Client/DeliveryController.php`, `Services/Access/DeliveryAccess.php`, `Services/Drive/{DeliveryMedia,ByteRange}.php`, `Contracts/DriveRangeSource.php` and `pages/client/delivery.tsx`. `PublicDriveSource` implements partial original requests through the existing provider boundary; `VerifiedOriginalStream` also verifies partial lengths and post-transfer metadata. `tests/Feature/ClientDeliveryTest.php`, `tests/Support/DeliveryFixture.php`, `tests/Support/delivery-browser-{fixture,router}.php`, `tests/Browser/delivery.spec.ts` and `playwright.delivery.config.ts` provide isolated verification. The dedicated CLI-server test router binds a synthetic HTTP transport behind the real adapter and is never included by application routes or the public front controller. No schema, permanent-original storage or product ZIP path is added.

## Ownership and dependencies

| Layer | Responsibility | Allowed dependencies |
| --- | --- | --- |
| React pages/components | Rendering, accessible interactions, visible save/job states | Typed application endpoints and view models; never provider secrets or direct database access |
| Requests/middleware/policies | Validate input and resolve authenticated/scoped actors | Models, access services, framework auth |
| Controllers | Translate HTTP input and responses | Requests, policies, Actions; no provider calls or commercial arithmetic |
| Actions | One transactional use case and domain invariants | Models, Services, Contracts, job dispatch after commit |
| Models/migrations | Persistence, constraints, relations | Framework persistence; no HTTP clients |
| Integrations | Provider protocol, error mapping, signatures, streaming | Contracts, configured HTTP/storage clients; no UI or entitlement policy |
| Jobs | Retryable background orchestration with authorization/version checks | Actions, Services, Contracts; never trust serialized user claims |
| Services | Reusable domain logic such as catalogue revisions and integer money | Contracts and scoped persistence as needed; no presentation code |

Provider payloads stay in their adapters. Frontend types describe application contracts, not raw Midtrans or Drive responses. Payment entitlement mutation has one Action owner, called by both callbacks and reconciliation; do not implement separate grant logic in controllers or scheduled jobs.

The [Design System](../design/DESIGN_SYSTEM.md) owns UI tokens and animation recipes. Shared appearance/motion configuration belongs outside page components; layouts compose it without duplicating theme state. The before-paint theme initializer belongs in the framework entry document and must agree with `appearance.ts`; verify its exact entry path after scaffold generation. CSS must not encode entitlement policy or alter photograph colors.

MVP gallery ZIP code belongs to archive services/jobs. Future photo-upload code must not be created as unused scaffolding, routes, or advertised UI. Provider-independent contracts may support the approved MVP without implementing the deferred product.

## Implemented TASK-008 delivery archives

`Models/{ArchiveJob,ArchiveEntry}.php`, the additive delivery-archive migration, `Services/Archives/{DeliveryArchives,ArchiveRecovery,BoundedZip}.php`, `Jobs/BuildDeliveryArchive.php`, `Http/Controllers/Client/ArchiveController.php` and `Console/Commands/RecoverArchives.php` implement product ZIP orchestration. `Contracts/DeliveryArchiveStorage.php` extends the existing storage boundary with pinned range reads and exact-attempt cleanup; the R2 adapter implements both. The `archives` database queue shares the PostgreSQL domain transaction. `routes/console.php` schedules maintenance every minute. `components/delivery-archive.tsx` supplies the persisted status/download UI inside the finished gallery.

`BoundedZip` isolates entry/footer encoding from the locked ZipStream dependency and spools central directory metadata to temporary disk. `BuildStreamingZip` uses it for both product and proof archives. `tests/Feature/ClientArchiveTest.php`, `tests/Integration/{ArchiveConcurrencyTest,ManyEntryZipTest}.php`, `tests/Support/{ArchiveDisk,DeliveryTransport}.php`, `tests/Support/archive-process-worker.php`, `tests/Browser/archives.spec.ts` and `playwright.archives.config.ts` add isolated verification. The delivery browser router and CLI fixture share synthetic provider bindings; production code never imports them.

## Implemented TASK-009 owner vouchers

`Actions/Billing/ManageVoucher.php`, `Models/{Voucher,VoucherVersion}.php`, `Services/Billing/{VoucherUsage,VoucherQuote}.php`, `Http/Requests/VoucherConfiguration.php` and `Http/Controllers/Admin/VoucherController.php` implement owner management, immutable terms, capacity checks, integer quotes and reports. The additive `2026_10_23_200000_create_vouchers.php` migration adds root/version/accounting tables, audit references, PostgreSQL constraints and history triggers. TASK-010 subsequently adds the order foreign key and held-reservation writer; TASK-011 now owns verified redemption/release.

`pages/admin/{vouchers,voucher}.tsx` compose the shared shell/controls; `/admin` links to management. `tests/Feature/VoucherManagementTest.php`, `tests/Integration/VoucherConcurrencyTest.php`, `tests/Support/{VoucherFixture,voucher-race-worker,voucher-browser-fixture}.php`, `tests/Browser/vouchers.spec.ts` and `playwright.vouchers.config.ts` provide isolated validation, authorization, accounting/version, process-race and rendered browser checks. No payment adapter, order model or unused grant endpoint is introduced.

## Implemented TASK-010 checkout and reservations

TASK-010 adds `Actions/Billing/{Checkout,ResumeCheckout}.php`, `Models/Order.php`, `Contracts/PaymentGateway.php`, `Services/Billing/GatewayCheckout.php`, `Integrations/Midtrans/SandboxGateway.php`, `Http/Controllers/Photographer/BillingController.php`, `config/billing.php` and the additive checkout-order migration. `pages/photographer/{billing,order}.tsx`, `components/purchase-totals.tsx` and `lib/billing.ts` compose the existing shell/primitives. `tests/Feature/CheckoutTest.php`, `tests/Integration/{CheckoutConcurrencyTest,MidtransCheckoutTest}.php`, `tests/Support/CheckoutFixture.php`, the checkout process/browser fixtures, `tests/Browser/checkout.spec.ts` and `playwright.checkout.config.ts` verify local/synthetic behavior. Test transports are CLI-only and never loaded by production routes. No new dependency or callback/grant scaffolding is introduced.

## Implemented TASK-011 verified payments

TASK-011 adds `Actions/Billing/ApplyPayment.php`, `Models/PaymentEvent.php`, `Services/Billing/{GatewayStatus,PaymentProcessing,PaymentRecovery}.php`, `Jobs/ProcessPaymentEvent.php`, `Http/Controllers/Webhooks/MidtransController.php`, `Http/Controllers/Admin/PaymentController.php`, `Console/Commands/ReconcilePayments.php`, isolated `routes/webhooks.php` and the additive payment-event/grant migration. The `payments` database queue shares domain PostgreSQL transactions as its durable outbox; `routes/console.php` schedules reconciliation. The existing Midtrans adapter owns signature/status verification, and checkout recovery delegates observations to the common financial Action. Current billing pages display verified paid/failed/review state using existing components.

`tests/Feature/PaymentProcessingTest.php`, `tests/Integration/PaymentConcurrencyTest.php`, `tests/Support/{PaymentFixture,payment-race-worker}.php` and the extended checkout browser fixtures/suite verify synthetic lifecycle, queue/crash recovery and independent-process PostgreSQL financial races. No new dependency, manual grant route or production transport is added. TASK-012 adds full admin review screens below; the owner recheck endpoints only queue an auditable provider lookup.

## Implemented TASK-012 platform operations

`Actions/Admin/OperatePlatform.php` owns transactional price versioning, scoped import/ZIP retries and deduplicated payment rechecks. `Actions/Auth/SetAccountSuspension.php` also compares an expected suspension version for operator forms. The additive operations migration adds monotonic account/job versions and safe audit subject/before/after references. `Services/Admin/OperationsRead.php` owns bounded metadata projections, aggregate accounting and diagnostic remedies; `Http/Controllers/Admin/OperationsController.php` translates validated owner requests and explicit form redirects. Existing domain services/workers retain authorization, provider and financial ownership.

`pages/admin/{dashboard,accounts,account,plan,orders,order,jobs,operations}.tsx` compose the existing shell, theme, fields, status and pagination components. Existing voucher screens remain authoritative for versioned voucher administration and usage history. `tests/Feature/PlatformOperationsTest.php`, `tests/Support/operations-browser-fixture.php`, `tests/Browser/operations.spec.ts` and `playwright.operations.config.ts` cover authorization, stale forms, immutable contracts, ledger totals, retry atomicity/gates and isolated synthetic owner browser flows. CI includes the new browser command. No dependency, production fixture route, arbitrary queue-payload executor or original-photo admin console is added.

## Bootstrap boundary

TASK-001 establishes package manifests/lockfiles, environment examples, local setup and safe CI. [README](../../README.md) owns supported commands; Backlog and its linked evidence own actual verification results. Additional integrated release evidence remains future work.
