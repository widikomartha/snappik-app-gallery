# SnapPik Documentation Index

**Package state: READY FOR REVIEW**

**Requirements baseline approved: October 22, 2026, in the owner interview.**

**Final document approval: pending.**

**Implementation phase: TASK-001 and TASK-003 complete; TASK-002 full-catalogue ZIP verified, real resource-key fixture pending; TASK-004–TASK-008 implemented with local/synthetic verification, prerequisite acceptance pending; TASK-009–TASK-015 not started.**

This file owns package status and approval tracking. Baseline approval authorized creation of this English documentation package in `D:\Website\snappik-app-gallery`. It did not approve unseen final documents, authorize deployment, or establish that future software is correct. Change package state to **APPROVED / IMPLEMENTATION READY** only after the owner explicitly approves the final documents and documentation checks pass.

On October 22, 2026, the owner approved incorporating the ZenGrid direction, Motion animation and light/dark appearance into the design documentation. [REQ-021 and REQ-022](product/PRD.md#req-021--light-and-dark-appearance) define the behavior; the [Design System](design/DESIGN_SYSTEM.md) defines the visual contract. This approval authorizes the documentation change, not application implementation or dependency installation. Final package approval is still pending.

Later on October 22, 2026, the owner explicitly requested implementation of TASK-001. That request authorizes the bootstrap and its dependencies/checks; it is not recorded as final approval of the entire documentation package or authorization for deployment.

The owner subsequently explicitly requested TASK-002, authorizing its provider contracts, local/test prototype, dependencies and checks. The owner confirmed that an authorized Drive test source and private R2 credentials are not configured and requested documenting that blocker. Synthetic adapter and large-ZIP checks are recorded in [TASK-002 evidence](planning/TASK-002_EVIDENCE.md). The real-provider acceptance gate remains open; this request does not approve the complete document package or authorize publication.

Later on October 22, 2026, the owner configured Drive/R2 and explicitly approved transferring all 384 configured source photos into one private test ZIP, verifying it, and deleting it. Actual import/pagination and sampled original-byte checks passed. The first queued full build stopped after 16 photos because the official Drive media endpoint returned HTTP 403 HTML containing the fixed phrase “automated queries”; its R2 output and multipart upload were removed. Separate generated-data R2 completion/CRC/MD5 and failed-input cleanup checks passed. At that stage the blocker was complete-source media access, rather than missing credentials. The subsequent correction and remaining gates are recorded below and in [updated evidence](planning/TASK-002_EVIDENCE.md#authorized-provider-smoke-follow-up).

The owner then requested a fix. Shared request pacing was implemented and verified by 60 tests plus an actual cross-process cache check. A full paced retry still failed after 23 photos with `drive_automated_requests_blocked`; cleanup again left no completed object or multipart upload. Both documented API-key transports reproduced the new failure. No credential or network changes were made. The owner declined the optional service-account probe; the existing public-source credential contract remains in force.

The owner explicitly chose to retain API keys. Further investigation identified and corrected a mismatch with Drive's documented media-download path: originals now use `/download/drive/v3/`, while metadata/listing retain `/drive/v3/`, on the same Google API origin. A direct same-input comparison reproduced the old-route 403 and validated all original bytes/MD5 on the canonical route. The subsequent worker produced one ZIP containing all **384 photos / 1,130,185,579 original bytes**. After bounded R2 readback improvements, independent extraction verified every entry's size, CRC and MD5. The object was deleted and no multipart uploads remained. The final backend suite passed **68 tests / 236 assertions**. [Complete evidence](planning/TASK-002_EVIDENCE.md#complete-canonical-route-build-and-r2-readback-follow-up) preserves measurements and failed attempts. No service-account flow was installed or enabled. TASK-002 remains incomplete pending an authorized real resource-key fixture; target-extractor and deployment privacy checks remain explicit delivery gates.

On October 22, 2026, the owner explicitly requested TASK-003, authorizing authentication, trial persistence, roles, central access policy, local dependencies and checks. The implementation and sandbox mail/browser evidence are recorded in [TASK-003 evidence](planning/TASK-003_EVIDENCE.md). This request does not approve the complete documentation package, authorize real messages, provision a real administrator, or authorize deployment. Final document approval remains pending.

On October 22, 2026, the owner explicitly requested TASK-004, authorizing project configuration, catalogue publication, capability management, local dependencies and checks. Its implementation and verification are recorded in [TASK-004 evidence](planning/TASK-004_EVIDENCE.md). TASK-002's authorized real resource-key fixture remains unverified, so TASK-004 is not declared complete. This request does not approve the entire document package, authorize real messages/provider transfers, or authorize publication. Final package approval remains pending.

On October 22, 2026, the owner explicitly requested TASK-005, authorizing client selection, gated previews, autosave, confirmation/reopening, local dependencies and checks. [TASK-005 evidence](planning/TASK-005_EVIDENCE.md) records its implementation and verification. TASK-004 acceptance remains dependent on TASK-002's authorized real resource-key fixture, so TASK-005 is not declared complete. This request does not approve the entire document package, authorize real messages/provider smoke transfers, or authorize production publication. Final package approval remains pending.

On October 22, 2026, the owner explicitly requested TASK-006, authorizing the photographer progress overview, current/historical results, filename export, local migration and checks. [TASK-006 evidence](planning/TASK-006_EVIDENCE.md) records implementation and verification. TASK-005's prerequisite acceptance remains open through TASK-004/TASK-002, so TASK-006 is not declared complete. This request does not approve the entire documentation package or authorize production publication, real messages or new provider transfers. Final document approval remains pending.

On October 22, 2026, the owner explicitly requested TASK-007, authorizing finished-gallery browsing, protected individual original downloads, local dependencies and checks. [TASK-007 evidence](planning/TASK-007_EVIDENCE.md) records implementation and verification. TASK-004/TASK-005 acceptance remains dependent on TASK-002's authorized real resource-key fixture, so TASK-007 is not declared complete. This request does not approve the entire documentation package or authorize production publication, real messages or additional live-provider transfers. Final document approval remains pending.

On October 23, 2026, the owner explicitly requested TASK-008, authorizing complete delivery ZIP manifests/jobs, private streaming output/downloads, recovery/cleanup, client status UI, local migrations and checks. [TASK-008 evidence](planning/TASK-008_EVIDENCE.md) records implementation and verification. TASK-002's real resource-key fixture and TASK-007 prerequisite acceptance remain open, so TASK-008 is not declared complete. This request does not approve the entire document package or authorize additional live-provider transfers, real messages or production publication. Final document approval remains pending.

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
