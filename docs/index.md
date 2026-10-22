# SnapPik Documentation Index

**Package state: READY FOR REVIEW**

**Requirements baseline approved: October 22, 2026, in the owner interview.**

**Final document approval: pending.**

**Implementation phase: TASK-001 implemented and verified; TASK-002 prototype implemented with provider integration blocked; TASK-003–TASK-015 not started.**

This file owns package status and approval tracking. Baseline approval authorized creation of this English documentation package in `D:\Website\snappik-app-gallery`. It did not approve unseen final documents, authorize deployment, or establish that future software is correct. Change package state to **APPROVED / IMPLEMENTATION READY** only after the owner explicitly approves the final documents and documentation checks pass.

On October 22, 2026, the owner approved incorporating the ZenGrid direction, Motion animation and light/dark appearance into the design documentation. [REQ-021 and REQ-022](product/PRD.md#req-021--light-and-dark-appearance) define the behavior; the [Design System](design/DESIGN_SYSTEM.md) defines the visual contract. This approval authorizes the documentation change, not application implementation or dependency installation. Final package approval is still pending.

Later on October 22, 2026, the owner explicitly requested implementation of TASK-001. That request authorizes the bootstrap and its dependencies/checks; it is not recorded as final approval of the entire documentation package or authorization for deployment.

The owner subsequently explicitly requested TASK-002, authorizing its provider contracts, local/test prototype, dependencies and checks. The owner confirmed that an authorized Drive test source and private R2 credentials are not configured and requested documenting that blocker. Synthetic adapter and large-ZIP checks are recorded in [TASK-002 evidence](planning/TASK-002_EVIDENCE.md). The real-provider acceptance gate remains open; this request does not approve the complete document package or authorize publication.

## Sources of truth

| Concern | Canonical document |
| --- | --- |
| Product behavior, permissions, commercial policy, acceptance criteria, Next Features | [PRD](product/PRD.md) |
| Visual tokens, component presentation, responsive geometry and motion recipes | [Design System](design/DESIGN_SYSTEM.md) |
| Components, persistence, integration contracts, concurrency, operational recovery | [Architecture](engineering/ARCHITECTURE.md) |
| Planned code placement and allowed dependencies | [Project structure](engineering/PROJECT_STRUCTURE.md) |
| Implementation sequencing and task completion evidence | [Backlog](planning/BACKLOG.md) |
| Reference-site observations and evidence limits | [Fastpik analysis](research/FASTPIK_ANALYSIS.md) |
| Agent workflow and safeguards | [AGENTS.md](../AGENTS.md) |
| Repository entry point and verified setup state | [README](../README.md) |

Other documents link to these authorities rather than defining competing versions of their rules. Detailed technical contracts are implementation designs for this review, not claims that the owner specified every database field or retry constant.

## Reading routes

For a feature, read its PRD requirement and corresponding backlog task. Add Architecture only for the boundaries the task changes. Add Project Structure when creating or relocating code, and Design System for visual components, themes or animation. The reference analysis is background evidence; it must never override an approved SnapPik decision.

## Glossary

| Term | Meaning |
| --- | --- |
| Platform admin | The owner operating SnapPik; different from a photographer's studio dashboard. |
| Photographer | One paying or trial account owning projects; MVP has no studio team membership. |
| Client | A visitor using a project capability link, with a gallery password when configured. |
| Selection gallery | The source catalogue clients choose from for editing. |
| Delivery gallery | The separate catalogue of finished photos clients can download. |
| Allowance | Maximum client choices for a project, independent of the number of photos displayed. |
| Catalogue revision | A published snapshot of Drive metadata with stable file identities. |
| Voucher reservation | A temporary capacity hold for a pending payment, not a successful redemption. |

## Review and launch boundaries

No unresolved owner choice blocks documenting the approved MVP. Hosting vendor/domain, merchant onboarding, service credentials, and sender configuration are deployment inputs, not invented facts. The launch task must verify them before public operation. Future hosted-storage capacities and prices are intentionally deferred with the upload feature.

The implementation must prove the public Drive integration and large single-ZIP path early. Application-level unlimited entitlements do not remove provider limits; the architecture defines transparent failure rather than silently adding a plan quota.

## Documentation validation

Validated on October 22, 2026 with the project-docs-architect documentation checker: **0 errors, 0 warnings; mechanical checks passed**, including the design-system update. Semantic review covered language, scope, authoritative links, requirement/task coverage, payment/voucher races, gallery access, complete ZIP behavior, tenant boundaries, design/motion ownership and evidence claims. Initial opaque color-pair contrast calculations passed the documented thresholds; rendered UI, animation and accessibility checks remain future implementation evidence.

The package contains 22 product requirements and 15 implementation tasks. Documentation validation does not prove provider integration or application correctness. The backlog records implementation status and links actual application verification evidence separately.
