# SnapPik Project Structure

**Classification:** TASK-001 foundation, TASK-003 authentication/access policy and TASK-002 local/test provider prototype exist; remaining project/gallery/billing paths are planned. [Package state](../index.md).

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

The root now contains generated `artisan`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/`, Laravel `config/`, `public/`, `storage/`, and the default users/cache/jobs migrations, factory and empty seeder. `app/Http/Middleware/` contains the Inertia and security-header middleware. `routes/web.php` defines public/authenticated/admin boundaries and a closed client route; `routes/console.php` has no domain schedules yet.

`resources/views/app.blade.php` owns the before-paint theme initializer and Inertia entry document. `resources/js/app.tsx`, `lib/appearance-init.js`, `lib/appearance.ts`, `lib/motion.ts`, `layouts/shell.tsx`, `components/ui/`, and `pages/{public,auth,photographer,admin,client}/` establish the shared presentation foundation. `resources/css/{tokens,app}.css` holds the theme tokens and styles. Self-hosted font licenses are in `public/licenses/`.

Real `composer.json`/`composer.lock`, `package.json`/`package-lock.json`, environment examples, TypeScript/Vite/Vitest/Playwright configuration and `.github/workflows/ci.yml` exist. Tests live in `tests/Feature/`, `tests/Browser/`, `tests/Integration/`, `tests/Support/` and frontend `*.test.ts(x)` files. Local downloaded runtimes and generated screenshots are ignored, not source deliverables.

TASK-003 adds `Actions/Auth/{VerifyAccount,ResetAccountPassword,SetAccountSuspension}.php`, `Http/Controllers/Auth/AccountController.php`, `Models/AccountAccess.php`, `Notifications/AccountLink.php`, `Services/Auth/AccountMail.php`, `Services/Access/{AccountAction,AccountPolicy,GalleryGate}.php`, `Policies/TenantResourcePolicy.php`, `Contracts/PhotographerOwned.php`, `Console/Commands/ProvisionAdmin.php` and additive account-access/audit migrations. `resources/js/components/auth-form.tsx` and `pages/auth/` contain the functional account forms. The dedicated `playwright.auth.config.ts` uses an isolated PostgreSQL test database for synthetic registration. PostgreSQL process-race workers live only under `tests/Support/`. These paths implement account policy; project, gallery, payment and voucher Actions/Models remain planned.

TASK-002 adds `app/Contracts/{DriveSource,ArchiveStorage,ArchiveUpload}.php`, `app/Integrations/{GoogleDrive,ArchiveStorage}/`, `app/Services/{Drive,Archives}/`, sanitized `ProviderFailure`, `app/Jobs/BuildDriveProofArchive.php`, `app/Models/DriveProof{Run,Node}.php`, their isolated PostgreSQL migration, `config/drive_proof.php` and `app/Console/Commands/DriveProof.php`. Dependency bindings live in `AppServiceProvider`. Provider snapshots remain inside adapters/encrypted prototype metadata; no provider code is imported by React. The command and prototype orchestration are local/test-only; these paths do not imply real-provider verification or finished gallery jobs.

`app/Services/Drive/DriveRequestPacer.php` now coordinates provider request starts through the shared cache/lock. `AppServiceProvider` injects its callback into the Drive adapter, keeping timing state out of controllers, models and UI code. Its operational interval lives in `config/drive_proof.php` and `.env.example`; tests cover independent instances, safe failure and pacing on retries/EOF metadata checks.

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

## Bootstrap boundary

TASK-001 establishes package lockfiles, manifests, environment examples, local setup and safe CI. [README](../../README.md) owns supported commands; Backlog and its linked evidence own actual verification results. Domain integration, concurrency, gallery and release evidence remain future work.
