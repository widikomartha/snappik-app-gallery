# SnapPik Project Structure

**Classification:** documentation paths below exist; application paths are planned and do not exist yet. [Package state](../index.md).

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

## Planned application layout

Use a Laravel application at the workspace root with the React/TypeScript Inertia frontend. Preserve the documentation during bootstrap. Generate framework-owned files with the chosen supported starter kit; the following is a placement contract, not an exhaustive list of scaffolding to create.

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

No package manager lockfile, application manifests, environment example, runtime, or CI workflow is verified in this repository yet. TASK-001 establishes them. Update README with actual supported commands after bootstrap, and keep this document's planned/verified classification accurate.
