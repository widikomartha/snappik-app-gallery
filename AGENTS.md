# SnapPik — Agent Instructions

## Scope

Implement the approved MVP in [PRD](docs/product/PRD.md). Before starting implementation, check the package approval state in [docs/index.md](docs/index.md); baseline approval and final document approval are different events. Do not claim that planned code, commands, integrations, or tests already exist.

Preserve owner decisions: paid accounts have no application quota on active projects, photo count, photo size, or project size; all-photo downloads produce one ZIP within SnapPik; hosted photo uploads and storage-based subscription tiers belong to Next Features. A client selection allowance is a photographer-configured choice limit, not a gallery storage quota. Product behavior is owned by PRD, not by the Fastpik reference.

## Read selectively

- Behavior, permissions, pricing, vouchers, or scope: open the relevant PRD sections.
- Visual components, colors, light/dark appearance, responsive layout or animation: use the relevant [Design System](docs/design/DESIGN_SYSTEM.md) sections; PRD owns behavior and access rules.
- Persistence, integrations, security, jobs, or concurrency: open the relevant [architecture](docs/engineering/ARCHITECTURE.md) sections.
- File placement and dependency direction: use [project structure](docs/engineering/PROJECT_STRUCTURE.md).
- Task selection, dependencies, and completion evidence: use [backlog](docs/planning/BACKLOG.md).
- Reference comparisons only: consult [Fastpik analysis](docs/research/FASTPIK_ANALYSIS.md). Observations and unavailable features are not SnapPik requirements.
- Use the index when routing is unclear. Do not read the entire documentation set for every task. Inspect relevant existing code and tests before editing.

## Operating safeguards

- No Fastpik login credentials, session cookies, payment keys, Google keys, tokens, or client capability links in source, fixtures, logs, or documentation. Use synthetic fixtures and environment variables.
- Use server-side authorization and tenant scoping for every read, write, media request, and job. Never trust a supplied photographer ID, price, discount, file ID, or payment redirect.
- Follow the payment and voucher transaction contracts in Architecture; duplicate callbacks must not duplicate access or redemption.
- Do not impose the rejected numerical gallery/file/project limits, silently split ZIPs, redirect all-photo downloads to Drive, or reintroduce hosted uploads in MVP.
- Ordinary implementation choices within these contracts may proceed. Escalate changes to product scope, commercial policy, external-service boundaries, or destructive behavior; do not invent owner decisions.
- Implement against sandbox services first. Live charges, real messages, credential changes, service purchases, and production publication need the user's authorization for those actions. Do not use the reference account as a development backend.
- Keep documentation in English. Keep claims about verification tied to commands actually run and results actually inspected. Run relevant checks, report remaining failures, and update only the authoritative documents affected by a change.
