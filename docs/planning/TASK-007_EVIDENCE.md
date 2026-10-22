# TASK-007 implementation evidence

Date: October 22, 2026 (Asia/Makassar). Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. The owner explicitly requested TASK-007. TASK-004/TASK-005 acceptance still depends on TASK-002's authorized real resource-key fixture, so this task is not declared complete. Final package approval and production publication remain separate decisions in the [index](../index.md).

## Implemented boundary

The existing photographer delivery configuration/import/publication flow now leads to **Buka hasil akhir** on the client project page. The finished gallery has folder navigation including descendants, bounded photo/folder pages, filenames, original enlargement, previous/next/Escape controls, focus restoration, retryable preview errors and native original downloads. The 36-photo and 20-folder pages are response windows, not catalogue quotas. A failed refresh retains the previous visible metadata with a stale warning while hiding download actions; recovery requires an explicit successful refresh.

`DeliveryAccess` resolves persisted project capability, the independent delivery password generation, publication/revision, deadline, archive and account policy. `DeliveryController` scopes every photo/folder to that module's currently published revision; selection entries, another project and retired revision entries are denied. HEAD, full GET and every new Range request recheck the same gates. Ordinary trial/subscription expiry does not close valid delivery. Deadline equality, suspension, archive, rotation, password/gate changes and unpublication deny new requests. An already authorized response may finish after an application gate changes.

`DeliveryMedia` uses provider Contracts and bounded reads, without a permanent original cache or JavaScript whole-file buffering. Native attachments preserve original bytes and safe JPEG/PNG filename extensions/case. Unsafe separators, controls, bidi-format characters and reserved device names are sanitized; headers include a Unicode filename and ASCII fallback. The browser owns completion reporting. Download failures discovered before headers produce a safe 502 text response without image/attachment headers, visible in the new tab, with explicit retry/contact guidance. No provider body, credential or source link is included. No low-resolution fallback is substituted for an original.

Single byte ranges accept closed, open-ended and suffix forms. Unsatisfiable supported ranges return 416 and `Content-Range: bytes */size`; malformed, unsupported and multipart ranges fall back to the complete representation. Strong matching If-Range preserves 206; stale/weak/date validators fall back to 200. HEAD performs fresh provider metadata/permission checks without transferring original bytes. There is no conditional 304 authorization shortcut.

The existing official API-key adapter implements `DriveRangeSource`, sends Range only to the canonical media endpoint, requests identity encoding, and checks exact 206 boundaries/total/length/type. Full and partial transfers validate fresh identity, download permission, version, size, checksum metadata, MIME and modification time. `VerifiedOriginalStream` validates received length and checks metadata/permission again at EOF; full originals additionally validate MD5 when supplied. A partial response does not independently prove the entire original's checksum. Before response headers, the service reads a bounded prefix and rejects HTML/XML-like error bodies. During streaming, one chunk stays pending until the next read/EOF succeeds. A late error closes the source and leaves Content-Length incomplete, without appending HTML or claiming success.

No database migration, production configuration, provider credential model, source mutation, hosted upload, content quota or product ZIP workflow was added. TASK-008 still owns Download All.

## Commands and inspected results

Windows checks used `.local-tools/php` on PATH and `.local-tools/browsers` as `PLAYWRIGHT_BROWSERS_PATH`. PostgreSQL checks use guarded `snappik_test`. Browser suites ran after PHPUnit, with the isolated schema reapplied after its teardown, and run sequentially to avoid test-database collisions.

| Actual command | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/phpunit --filter 'ClientDeliveryTest\|PublicDriveSourceTest'` | Intermediate targeted run: **59 tests / 258 assertions passed**; later added scenarios are included in the final full suite |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final backend suite: **168 passed / 1,249 assertions**, one opt-in large-ZIP test skipped (169 total); 96 MiB reported memory |
| `npm run build` | Strict TypeScript and production Vite build passed |
| `npm run typecheck` | Final TypeScript check passed, including browser tests |
| `npm test` | **2 files / 5 tests passed** |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Existing schema reapplied successfully to the isolated test database after PHPUnit teardown |
| `npm run test:browser:delivery` | **4 passed** at 360px/1440px in light/dark; final run 42.7 seconds |
| `npm run test:browser:selection` | Existing selection/autosave/confirmation/reopen regressions: **4 passed** |
| `npm run test:browser:projects` | Existing configuration/import/publication/archive regressions: **4 passed** |
| `.\.local-tools\php\php.exe vendor/bin/pint` with the TASK-007 PHP paths | Formatting applied successfully |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Final repository formatting passed |
| `node .local-tools/check-links.mjs` | Local Markdown file links passed in **16 documents** |
| `git diff --check` | Passed |

The initial targeted check exposed a metadata-only fixture binding that lacked the new media contracts; the isolated test binding was corrected without relaxing authorization. Windows sandboxed Pint could not write a new file; approved execution outside that sandbox passed. Initial browser fixture runs failed because PHPUnit's teardown had removed the isolated schema. After migration, a synthetic HTTP header used an integer instead of the PSR-7 string type, producing a visible safe 502. Correcting the test transport's header type made thumbnail and attachment requests pass. Temporary sanitized exception-type diagnostics were removed. No failed run is counted as successful evidence.

## Inspected scenarios and remaining gates

Backend HTTP/provider checks cover complete original-byte/MD5 comparison; private/no-store/no-referrer/nosniff/no-index headers; safe Unicode, device and extension filenames; inline enlargement and proxied thumbnails; independent passwords/deadlines; ordinary account expiry; archive, suspension, rotation, password-generation and unpublication denial; another project's file/folder, selection entries and retired revisions; pagination; closed/open/suffix/large numeric ranges; strong/stale/weak/date If-Range; unsatisfiable ranges; upstream ignored/malformed/wrong-total/short/surplus ranges; source restriction, changed metadata, inaccessible files, native HTML and HTML disguised as image media. A 360,000-byte synthetic transfer with a bad final checksum leaves fewer bytes than the advertised Content-Length. A separate authorized response finishes after its password gate changes while the next Range request is denied.

The dedicated browser suite uses real authentication, sessions, module unlocks, application media endpoints and browser attachment handling. A CLI-only guarded test router injects synthetic upstream HTTP responses behind `PublicDriveSource`; neither the app's production front controller nor its routes reference that fixture. No browser route interception replaces the download. Each scenario reads the saved PNG from the browser download path and compares every byte, verifies 206 partial bytes through the authenticated request context, confirms delivery after account expiry/selection deadline, checks separate passwords, navigates folder/zoom, tests Escape focus restoration, reads the safe permission-revocation error in the opened tab, retries original preview after revocation, and denies a fresh Range after delivery deadline. Both themes/widths have no JavaScript page errors or horizontal page overflow; narrow runs use reduced motion.

The 360px light and 1440px dark screenshots were visually inspected for wrapping, readable labels, folder/photo controls and available download actions. They use a synthetic one-pixel PNG, so this inspection proves layout and unchanged fixture rendering rather than real photograph quality. Screenshots are generated under ignored `test-results/`; later Playwright commands replace that output. The new dedicated browser command is included in CI; hosted CI was not run here.

TASK-002's authorized real resource-key fixture remains unverified and is a hard prerequisite through TASK-004/TASK-005. No new live-provider preview/download/Range transfer was authorized or performed. Before release, verify actual provider Range behavior, real filename/byte downloads, interrupted-transfer behavior and proxy buffering/compression/timeouts in the target deployment, with capability-path redaction in access logs. No real messages, credential changes, production publication or large-ZIP rerun were performed.
