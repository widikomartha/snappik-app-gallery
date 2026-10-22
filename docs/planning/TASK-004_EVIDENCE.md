# TASK-004 implementation evidence

Date: October 22, 2026 (Asia/Makassar). Authorized by the owner's explicit TASK-004 request. Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. TASK-003 is complete; TASK-002's required authorized real resource-key fixture remains unverified. This task is therefore not declared complete, and final package approval/publication authorization remain pending in the [index](../index.md).

## Delivered boundary

- Verified photographer project list/create/edit/archive/restore with optional contact, positive choice allowance and independent selection/delivery source, password, deadline and publication controls. Project/configuration/command/diagnostic routes use server-side account and tenant authorization; supplied owner IDs and admin status cannot confer another photographer's rights. Expired accounts retain read access but lose mutations; suspended accounts lose project metadata and public gallery access.
- A per-account user/access lock serializes creation and restoration against the trial's three unarchived projects, including drafts. Draft slots persist until explicit archive. Pro has no project/photo/file-size/project-size quota fields or validation. Project versions reject stale configuration forms/commands; polling import state preserves unsaved form values. Delivery configuration stays in the same project/slot and is allowed while selection source replacement is locked.
- Project capabilities use 256 random bits, an indexed SHA-256 digest and encrypted retrieval storage. Rotation revokes the old link and increments both module gate generations. Passwords are hashed with byte/NUL validation and never prefilled. Independent offset-qualified deadlines, password changes/removal, publication closure, archive/restoration and capability rotation invalidate old module sessions. Password attempts are throttled per IP and capability/module/IP. Client status responses re-evaluate the persisted gates, omit provider sources/keys and use private/no-store/no-referrer/noindex headers.
- Product imports queue only a persisted revision UUID. A dedicated database queue connection shares the same PostgreSQL domain connection, so request/revision/job insertion commits or rolls back together. Worker authorization derives from persisted module/project ownership. Imports use the existing official paced public Drive adapter, with encrypted source/resource-key references, snapshots and page checkpoints. No original bytes are downloaded by this feature and no Drive write/R2 operation is involved.
- Incremental descendant traversal includes subfolder-only roots and every provider page, preserves stable identities, names, folder paths, provider versions/checksums and 64-bit byte metadata, and detects repeated identities/page cycles. Shortcuts are reported without traversal; unsupported files are reported outside the photo catalogue. Manifest hashing uses bounded database chunks. A PostgreSQL advisory lock prevents concurrent work on one revision; redelivery resumes committed checkpoints. Superseded requests, archive, expiry or suspension prevent publication.
- A complete traversal and source-overlap check precede the atomic accepted-revision pointer change. Roots, nested folders and photo identities are checked across the two module sources under the project lock. Initial module publication is an explicit photographer action; successful refresh replaces an already published module's revision. Failed/empty/inaccessible/overlapping imports retain the previous accepted revision and mark stale status. Error codes provide retry/source/access guidance without retaining raw provider exceptions.
- Saved choice identity/name/path/revision records are independent of catalogue refresh. Missing choices remain stored and appear through scoped anti-joins against the latest accepted catalogue. Allowance cannot be reduced below retained choices, and a locked selection cannot replace its source. The current choice table and selection lock support these configuration invariants; client save/review/confirmation/reopen/history endpoints remain TASK-005.
- The project page shows import/publication state, counts and bounded diagnostic samples. Complete excluded-file/shortcut and unavailable-choice lists paginate at 20 rows, as do photographer projects. These windows do not limit stored content. Archive preserves records, original bytes and publication settings while closing client access.

The client capability page intentionally covers gated module status/counts and password entry only. Photo grid/navigation/previews, client choice actions, confirmation/reopening, originals and ZIP delivery belong to TASK-005/007/008 and are not advertised as implemented.

## Verification environment and actual commands

Used existing ignored portable PHP 8.4.27, Composer, PostgreSQL and Chromium, plus the existing locked frontend dependencies. No package/dependency version was added or changed. PHP tests use the guarded isolated `snappik_test` PostgreSQL database. Provider responses were synthetic; no external requests, real mail, live charges, credential changes or publication occurred.

PowerShell runtime settings for browser commands on this machine:

```powershell
$env:PATH = 'D:\Website\snappik-app-gallery\.local-tools\php;' + $env:PATH
$env:PLAYWRIGHT_BROWSERS_PATH = 'D:\Website\snappik-app-gallery\.local-tools\browsers'
```

| Actual command | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/pint --dirty` | Formatted changed PHP files; final run passed |
| `.\.local-tools\php\php.exe artisan test --filter 'ProjectConfigurationTest\|AccountConcurrencyTest'` | Intermediate targeted suite: 15 tests / 188 assertions passed, before the final recovery/diagnostic/rollback checks were added |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final backend suite: 107 passed / 708 assertions; 1 opt-in large-ZIP test skipped (108 total), 90 MiB reported suite memory |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | No pending migrations after the backend suite |
| `npm run build` | Final strict TypeScript and production Vite build passed |
| `npm test` | 2 files / 5 tests passed |
| `npm run test:browser:projects` | Final project flow: 4 passed at 360px/1440px in light/dark; actual database queue worker and synthetic Drive binding |
| `npm run test:browser` | 16 passed / 5 intentional skips; isolated auth/project writes are excluded from the ordinary development-server suite |
| `npm run test:browser:auth` | 6 passed on its separate test-database server |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive project/catalogue migration applied to local development PostgreSQL |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Manifest/lockfile valid |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Passed |
| `node .local-tools/check-links.mjs` | Local Markdown links passed in 13 documents |
| `git diff --check` | Passed |

The first sandboxed PHP attempt could not read `vendor/autoload.php` or write formatting changes; Vitest's temporary rename was also denied. Approved execution outside that Windows sandbox passed. The initial targeted run exposed nondeterministic module ordering, a newly created model missing its database-default version in memory, and an unrefreshed verified test actor; explicit module ordering/model refreshes corrected these, and all final checks passed. No product policy was weakened.

## Inspected scenarios

Backend tests cover encrypted resource keys/capabilities, hashed passwords, owner injection, invalid fetch destinations, explicit deadline offsets, password byte/NUL validation, optional contact, nested-only roots, multiple provider pages, unsupported/shortcut reporting, duplicate filenames, empty/cyclic sources, failed second-page retention, missing-choice preservation, exact source-root/nested/shared-photo overlap, locked selection source, allowance reduction, stale forms, superseded jobs and entitlement loss before/during import. A manually staged committed page/checkpoint resumes only the remaining page; duplicate completed-job delivery does no provider work. Diagnostics expose all 25 synthetic excluded records across two pages and reject another tenant.

The paid metadata check creates **51 active projects**, imports **2,001 photos** represented at **30 MiB each**, and verifies a project above **20 GiB** without quota validation failure. This is a metadata entitlement test, not a claim to have transferred those bytes or measured arbitrary-catalogue memory. The backend suite reports its own peak memory separately; TASK-002 retains the real streaming measurements.

Separate PHP processes contend for the same real PostgreSQL user lock while creating or restoring into the final trial slot. Exactly one succeeds and the other receives the documented validation rejection; three unarchived projects remain. An outer transaction failure removes the project, revision and already inserted database queue row together. Existing verification/reset races continue to pass.

Project browser checks use the actual forms/session/CSRF/HTTP routes, seed only verified synthetic accounts in the isolated test database, and run the actual database queue worker with a CLI-only synthetic `DriveSource` binding. They create without a contact, preserve draft input through theme switching, consume nested/paginated imports, inspect excluded-file diagnostics, save edits, publish both modules, unlock selection without unlocking delivery, rotate/reject an old capability and archive/restore public access. No JavaScript page errors or page-level horizontal overflow occurred. Mobile uses reduced motion and wide screens normal motion. Generated screenshots mask source/share inputs; 360px light and 1440px dark were visually inspected for layout/readability. Screenshots are ignored under `test-results/`.

## Remaining acceptance and release gates

TASK-002's authorized real resource-key fixture remains a hard completion prerequisite. This turn did not rerun real Drive/R2 smoke transfers or the unrelated >4-GiB opt-in proof; existing evidence remains unchanged. No claim is made that synthetic key propagation proves an actual resource-key-protected source.

Deployment must configure/redact reverse-proxy/access/analytics logs so capability paths cannot be captured, run/supervise the documented catalogue worker and verify recovery/privacy in the intended environment. Photo/choice/media integrations, selection confirmation history, operator retry screens, operational sweeps/backups and public release checks remain in their owning tasks. Failed provider imports offer an explicit new refresh; process redelivery reuses its encrypted checkpoints. No hosted uploads, content/storage tiers or source deletion were added.
