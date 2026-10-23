# SnapPik workflow comparison with Fastpik

Audit date: October 24, 2026 (Asia/Makassar).

Follow-up: this document captures the working tree before the owner's subsequent copy/layout refinement request. The resulting [design contract](../design/DESIGN_SYSTEM.md#task-hierarchy-and-product-language) and [implementation evidence](../design/LANDING_DIRECTION.md) supersede the presentation observations where they overlap. In particular, project sharing now has a copy action, optional access settings are expandable and internal terminology is reduced. The provider pacing, selection-request and dashboard-query optimization findings remain outstanding; presentation work does not establish performance parity.

## Conclusion and evidence limits

SnapPik implements the core selection and delivery lifecycle, but its current interaction design and photographer workspace do not yet match the workflow breadth or convenience observed in Fastpik. Visual consistency and passing functional tests do not establish equivalent usability or performance.

This audit reviews the current working tree, including existing uncommitted presentation changes. It does not change application behavior, approve additional scope, or replace the PRD/backlog. Findings below are implementation observations and proposed follow-up work, not completed improvements.

Fastpik evidence comes from the [authorized October 21 account audit](FASTPIK_ANALYSIS.md). The [official public page](https://fastpik.id/id) was also retrieved through web search during this audit; it describes creation, sharing and receiving selections, plus tracking, folders, batch mode, WhatsApp templates, extra photos and print selection. Public descriptions do not prove those features end to end. No signed-in Fastpik session or side-by-side performance benchmark was run during this audit. Its feature subpage could not be retrieved directly.

## Workflow comparison

| Stage | Fastpik evidence | Current SnapPik implementation | Assessment |
| --- | --- | --- | --- |
| Create a project | Drive creation/edit tested; cover/layout/notes/contact/module controls observed | One configuration form exposes basics and both selection/delivery settings, then creates a draft and queues import | Core source/allowance/gate behavior exists. Basic creation competes with settings for later stages; the next required action is not presented as a guided sequence. Optional contacts are an intentional SnapPik improvement. |
| Publish and share | Client entry and copy feedback tested; sharing templates inspected | Import status polls every three seconds while busy; explicit module publication; client URL in a read-only field near the bottom | Safe publication exists. No dedicated copy-link action, preview action or concise share step in the project page. Automatic import polling already exists; status inspection is not exclusively manual. |
| Client entry | Project cover/name and module menu tested | Generic gallery heading and available module cards; module-specific password form | Access/menu behavior exists. Project identity and presentation are sparse; branded covers are deferred scope. |
| Browse and choose | Grid selection, count, allowance and review tested; viewer includes thumbnails and selection controls | Paginated 36-photo grid, folders, lazy thumbnails and separate select/enlarge buttons | Core selection exists, but a save disables all choices and awaits a full gallery-data fetch before interaction resumes. |
| Enlarged viewer | Navigation and selection context tested; not all gestures/keyboard actions verified | Dialog loads an original, navigates only within the current page, and has no selection toggle | Clients must close the viewer to choose. No filmstrip or arrow-key/swipe photo navigation is implemented in these pages. This is a concrete interruption to photo review. |
| Review, confirm, reopen | Underfilled confirmation and retained choices after reopening tested | Canonical review, confirmation lock, photographer reopening, confirmation history and filename export | Core behavior is aligned. SnapPik preserves its server version/idempotency contracts; UX changes must retain them. |
| Photographer follow-up | Totals, progress, upcoming deadlines and recent projects inspected; state-change notices observed | Dashboard and project list reuse the same overview; status/count/last-update cards and 15-second polling | Tracking exists, but no summary totals, actionable deadline queue, project search/filter/sort controls or notifications in the inspected workspace. The photographer must open individual projects to proceed. |
| Finished-photo delivery | Individual and selected-photo ZIP tested; Download All redirected to Drive in the observed sample | Separate delivery source, protected original downloads and persistent complete-ZIP jobs | Deliberate product difference. SnapPik must preserve one complete ZIP within the app. The prior provider proof verified all 384 entries; it does not benchmark the current product UI against Fastpik. |
| Manage many projects | Folder creation, bulk move and two-row batch creation tested | Flat paginated project cards; no batch or project-folder workflow | Substantial breadth gap, explicitly deferred by the PRD. |
| Extra, print, notes and integrations | Extra/print/note flows tested; integration controls mostly inspected | Outside current MVP | Scope gap, not evidence of a broken implemented MVP feature. |

Relevant implementation sources: [project configuration](../../resources/js/pages/photographer/project.tsx), [overview](../../resources/js/components/project-progress.tsx), [client entry](../../resources/js/pages/client/project.tsx), [selection](../../resources/js/pages/client/selection.tsx), [delivery](../../resources/js/pages/client/delivery.tsx), [progress service](../../app/Services/Selections/ProjectProgress.php).

## Optimization findings

### 1. Selection has a global wait and an extra data round trip

In `selection.tsx`, `send()` sets global `busy`, posts a mutation, then awaits `load()` on success. `writable` depends on that same global flag. Consequently, one normal selection takes a mutation request followed by a gallery-data request, while all selection controls remain disabled. This is a source-confirmed dependency, not a measured latency result. It does not reload the whole browser document, and it does not necessarily reload every unchanged image.

Proposed improvement: update the affected tile and canonical count/version from the acknowledged response without refetching the whole page on every ordinary mutation. Keep a deliberate strategy for review-page removal, uncertain acknowledgements, other-session changes and confirmation. If accepting rapid pending interactions, serialize/rebase them against acknowledged versions and stop on access changes; never label an unacknowledged choice as saved or silently replay an offline queue after a lock.

### 2. Interactive media shares one provider pacing budget with jobs

[`PublicDriveSource::thumbnail()`](../../app/Integrations/GoogleDrive/PublicDriveSource.php) fetches fresh metadata with `thumbnailLink`, validates the expected identity/version, then fetches the thumbnail. Both requests invoke the same pacing callback. [`AppServiceProvider`](../../app/Providers/AppServiceProvider.php) supplies the same adapter/pacer to preview, original and range contracts, including background work. [`DriveRequestPacer`](../../app/Services/Drive/DriveRequestPacer.php) coordinates requests across processes using the API-key digest. The configured default is one second between starts; the actual local/provider interval was not read from credentials or measured.

This makes interactive thumbnails, import requests and archive source requests contend for one request budget. Two paced provider calls per thumbnail can accumulate substantial wait when many images are requested. Lazy loading reduces initial demand, and actual delay depends on configuration, visible images, worker activity and provider/network timing. This audit does not claim a measured gallery load time or Fastpik speed advantage.

Media responses use `no-store, private`, and no reusable preview cache is present in the inspected path. Enlarged previews request original bytes rather than a bounded display-sized image. These are optimization candidates, with authorization and source-change semantics to preserve. Removing pacing or exposing public cached media is not an acceptable shortcut.

Proposed next step: instrument provider-call count, pacing wait, thumbnail time-to-first-byte, original bytes and failures with sanitized identifiers. Measure interactive media both with and without a concurrent ZIP/import. Then review request deduplication, safe short-lived metadata/preview reuse and scheduling within the existing official provider boundary. Any cache design must recheck gallery gates and define source permission/version freshness and invalidation; hosted originals remain outside MVP.

### 3. Project polling repeats per-project queries

[`ProjectProgress::overview()`](../../app/Services/Selections/ProjectProgress.php) paginates projects, then invokes `summary()` separately for each. Each summary queries modules, the requested revision when present, choice count and confirmation count. This query fan-out repeats on 15-second overview polling. Browser polling already skips hidden documents; that optimization exists.

Proposed improvement: batch/eager-load scoped modules/revisions and aggregate counts for the current page. Verify query count remains bounded as the page grows, and retain tenant isolation and coherent read semantics. No database latency/load benchmark was performed here.

### 4. Initial gallery navigation has a separate data fetch

Selection and delivery `show()` return the Inertia page without the first gallery payload. The mounted page then requests `/data`. This creates a rendering/data waterfall before the first gallery content. Evaluate supplying the first authorized page in the initial response, while retaining scoped pagination and safe refresh handling. The existing lazy page imports and bounded photo pagination are useful optimizations and should be preserved.

## Proposed order of work

These priorities are recommendations, not newly approved backlog tasks.

1. **Immediate: make selection responsive and establish media measurements.** Remove the unconditional post-save gallery fetch for ordinary choices; support choosing directly from the enlarged viewer. Measure provider pacing contention before changing request controls. Validate rapid choices, last-slot races, uncertain saves, concurrent confirmation and access expiry.
2. **Next: simplify the create-to-share journey.** Present basic project inputs first, put optional settings behind a secondary step, show import progress and a clear publish action, then provide copy-link/preview actions. Delivery remains a later stage of the same project, with separate publication and gates. Do not auto-publish partial imports.
3. **Next: make the workspace actionable and cheaper to poll.** Distinguish dashboard summaries from the project catalogue; propose status/deadline filters and next actions. Batch the repeated overview queries. New product behavior must be reconciled with the authoritative PRD/backlog before implementation.
4. **Later, by owner scope decision: add Fastpik workflow breadth.** Batch/folders, extras, print, notes, branded covers and integrations belong to the [explicit Next Features list](../product/PRD.md#next-features--explicitly-outside-mvp). They must not be silently folded into an optimization change.

Preserve paid unlimited content, optional contacts, separate selection/delivery catalogues, complete single-ZIP downloads, server authorization and sandbox-first integrations throughout.

## Verification performed

| Command/check | Actual result |
| --- | --- |
| `npm run typecheck` | Passed. |
| `npm test` | Initial attempt stopped before any test ran because the sandbox temporary-cache rename returned EPERM. Retried with process-local TEMP/TMP under ignored `.local-tools/workflow-audit-temp`; 3 files / 6 tests passed. |
| `.local-tools/php/php.exe vendor/bin/phpunit --filter 'ProjectConfigurationTest\|ClientSelectionTest\|PhotographerProgressTest\|ClientDeliveryTest\|ClientArchiveTest'` | Initial sandbox run could not read `vendor/autoload.php`. The approved retry outside the sandbox passed: 71 tests / 1,416 assertions, 25.012 seconds. |
| Existing browser test source inspection | `tests/Browser/selection.spec.ts` replaces preview responses with a synthetic one-pixel image. It exercises interaction contracts but does not measure the real Drive preview pipeline. No browser suite was rerun for this audit. |
| Prior real ZIP evidence | Reviewed [TASK-002 evidence](../planning/TASK-002_EVIDENCE.md): complete 384-photo ZIP with independent size/CRC/MD5 validation and cleanup. This evidence is historical; no new real-provider transfer was performed. |

These results establish selected functional checks on the current working tree. They do not establish usability equivalence, production readiness, real payment acceptance or a measured performance comparison. Existing approval and provider acceptance gates remain as recorded in the [documentation index](../index.md).
