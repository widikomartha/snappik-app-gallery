# TASK-005 implementation evidence

Date: October 22, 2026 (Asia/Makassar). Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. The owner explicitly requested TASK-005. TASK-004 acceptance still depends on TASK-002's authorized real resource-key fixture; this task is not declared complete while that hard prerequisite remains open. Final package approval and public release remain separate decisions in the [index](../index.md).

## Implemented boundary

Clients can open a published selection module after its independent optional password gate, browse folders/photos, enlarge a photo, choose across pages, reload acknowledged choices, review the shared canonical set, and explicitly confirm one through the allowance. Photographer configuration shows draft/locked counts and the first 20 saved choice records; its explicit reopen action preserves choices/history under a new epoch. Full progress/history/export remains TASK-006, and finished-photo attachment/range downloads and product ZIP UI remain TASK-007/008.

`ManageSelection` locks user, account-access and project rows in the same order as configuration/catalogue publication. It rechecks persisted capability/password/publication/archive/deadline/account gates after waiting. Each accepted operation atomically commits its choice or immutable confirmation, selection version and receipt. Reused payload IDs conflict; identical request retries are no-ops with the original result plus fresh canonical state. Stale epoch/version/revision returns 409. A current locked selection rejects new edits; account expiry freezes new writes while preserving eligible reads. Duplicate reviewed confirmations share one immutable snapshot, including when request IDs differ. Confirmation choice rows use `INSERT … SELECT`, with no whole-choice-set PHP array. Reopening checks the authenticated photographer, current entitlement, expected epoch/version and deadline; earlier snapshots are never modified.

Grid and review pages contain at most 36 photos, folder pages 20 records. These are navigation page sizes, not gallery quotas. Folder traversal follows persisted parent file identities, so identical folder names do not merge. Existing catalogue revisions have no parent identities: all-photo browsing remains available with an explicit refresh instruction to the photographer, rather than guessing historical parents or silently refreshing a public snapshot.

The separate `DrivePreviewSource` contract obtains a fresh `thumbnailLink` using official Drive metadata and serves only allowlisted HTTPS Google image hosts through the private SnapPik proxy. Thumbnail links, credentials and resource keys never enter page state. No redirects are followed and no API/resource credentials are forwarded to thumbnail hosts. Enlargement uses the verified original stream. Both paths authorize each new request (including one carrying Range), use 64-KiB stream reads, close streams, deny indexing/referrers and disable caching. Range requests currently receive complete inline previews; TASK-007 owns download range behavior. Provider metadata/host/content-type/redirect failures remain visible as preview errors with explicit retry. [Google's file resource documentation](https://developers.google.com/workspace/drive/api/reference/rest/v3/files) confirms thumbnail links are short-lived and intended to be proxied; [resource-key documentation](https://developers.google.com/workspace/drive/api/guides/resource-keys) defines the metadata request header.

The React UI announces saving, acknowledged, conflict and failed/uncertain results. It preserves an uncertain operation ID only in mounted memory for explicit retry or offers a fresh server read. It never silently queues offline writes. Appearance switching during a pending save preserves the request/state. Lightbox closing/cancellation never selects or confirms a photo and returns keyboard focus to its invoker.

## Commands and inspected results

Windows commands used the ignored portable PHP path, adding `.local-tools/php` to PATH for Playwright's PHP child processes and `.local-tools/browsers` as `PLAYWRIGHT_BROWSERS_PATH`. Backend tests and all fixture writes use the guarded PostgreSQL `snappik_test` database. Browser servers run sequentially, separately from database-refreshing PHPUnit.

| Actual command | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/pint --dirty` | Formatted changed PHP; subsequent formatting check passed |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final backend suite: **139 passed / 930 assertions**, one opt-in large-ZIP test skipped (140 total); 94 MiB reported suite memory |
| `npm run build` | Strict TypeScript and production Vite build passed |
| `npm test` | 2 files / 5 tests passed |
| `npm run test:browser:selection` and `npm run test:browser:selection -- --grep '1440px light'` | All 4 viewport/theme scenarios passed, including the expanded 2,001-photo/expiry scenario on its corrected targeted rerun |
| `npm run test:browser:selection -- --grep '1440px dark'` | Final wide/dark scenario passed; generated screenshot visually inspected |
| `npm run test:browser:projects` | 4 passed at 360px/1440px in light/dark |
| `npm run test:browser:auth` | 6 passed on the isolated account server |
| `npm run test:browser` | 16 passed / 9 intentional skips; isolated auth/project/selection writes are excluded from the ordinary suite |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Additive migrations applied successfully in the isolated test database after PHPUnit |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive selection-history/receipt/parent-identity migration applied to local development PostgreSQL |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Manifest/lockfile valid |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Passed |
| `node .local-tools/check-links.mjs` | Local Markdown file links passed in 14 documents |
| `git diff --check` | Passed |

Initial sandboxed PHP could not read `vendor/autoload.php`; sandboxed Vitest could not rename temporary files. Approved execution outside that Windows sandbox passed. Early targeted tests corrected their expected generic 404 tenant denial and used explicit persistence for the guarded suspension field; no product authorization policy was relaxed. A review caught full-original grid fetching and replaced it with the documented thumbnail proxy before final backend verification. The expanded browser expiry test initially changed entitlement before the preceding page read finished, correctly leaving controls already frozen; waiting for that read before simulating expiry made the intended open-session/write-rejection scenario deterministic. Its corrected targeted rerun passed.

## Inspected scenarios

Feature/adapter tests cover reload/shared persistence, receipt/payload replay, stale edits/reviews, zero-versus-one confirmation, allowance enforcement, foreign file/folder/media IDs, password generation/session scope, exact deadline/account expiry boundaries, suspension/archive/rotation/unpublication, refresh revision mismatch, missing-choice retention/removal, immutable history after reopening and stale reopen rejection. A synthetic receipt failure rolls back the choice and version together. A 77-photo catalogue returns bounded pages; duplicate-named folders remain separate by identity. Thumbnail tests reject unsafe/missing destinations, credentials in URLs, redirects, revoked access, changed files and HTML, and verify metadata resource-key propagation without forwarding keys to thumbnail hosts. Preview HTTP tests compare returned synthetic original/thumbnail bytes and confirm private/no-referrer image responses.

Six separate-process PostgreSQL tests contend for the real owner/project aggregate: two clients taking the last slot, identical operation replay, edit versus confirmation, two identical reviewed confirmations, expiry during a lock wait and deadline closure during a lock wait. Exactly one final-slot edit commits; one receipt/snapshot is created under duplicate contention. An edit either wins before confirmation and invalidates review, or loses to the committed immutable snapshot. Prior acknowledged choices survive both expiry cases.

Browser checks use actual application/session/CSRF routes with CLI-only synthetic accounts/imports and intercepted image bytes. They exercise password entry, folder navigation, reload/shared choices, empty confirmation denial, lightbox previous/next/Escape/focus return, an acknowledgement lost after the server commits, explicit retry of the same operation ID, theme switching while a real save waits, stale review rejection, cancelled confirmation, one-of-two confirmation, a second client's rejected post-lock edit and photographer reopening. The expanded wide/light case browses **2,001 photos across 56 pages with 36 mounted tiles**, preserves choices across page replacement, rejects a new write when account access expires, reloads read-only state with both choices intact, then denies a read after the module deadline. No JavaScript page errors or page-level horizontal overflow occurred. Narrow sessions use reduced motion; wide sessions use normal motion. Screenshots contain synthetic names/photos only and no capabilities/source links.

The generated 360px light and 1440px dark selection screenshots were visually inspected for readable state text, separate preview/choice controls, unclipped panels and responsive geometry. Image fixtures are deliberately synthetic, so these screenshots do not prove real-provider image quality or availability. Screenshots are ignored under `test-results/`.

## Remaining gates

TASK-004 acceptance remains open pending TASK-002's real resource-key fixture. TASK-005 provider verification is synthetic: no new live Drive/R2 transfer, real message, credential change, production publication or >4-GiB rerun was authorized or performed here. Real thumbnail host/permission behavior must be smoke-tested with the authorized deployment source; an unsupported provider host fails visibly rather than broadening the fetch allowlist. Private media/access-log redaction, actual provider availability and operational supervision remain release checks. No hosted uploads, per-person choices, photo notes, content quotas or source modifications were added.
