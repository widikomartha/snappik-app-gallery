# TASK-002 verification record

Date: October 22, 2026 (Asia/Makassar). Scope: public Drive and one streaming ZIP64 technical prototype. The owner's explicit TASK-002 request authorizes this implementation and its dependencies/checks, while final document-package approval remains pending in the [index](../index.md).

**Status: FULL-CATALOGUE ZIP VERIFIED / REAL RESOURCE-KEY FIXTURE PENDING. TASK-002 is not complete.** Initially the owner confirmed missing provider configuration and requested implementing the prototype with that blocker documented. The owner subsequently configured Drive/R2 and explicitly approved the complete 384-photo transfer, verification and deletion. After the canonical media-route correction, all 384 photos reached one R2 ZIP; complete independent size/CRC/MD5 verification and cleanup passed. The [provider follow-up](#authorized-provider-smoke-follow-up) preserves earlier failures, and the [complete result](#complete-canonical-route-build-and-r2-readback-follow-up) records the successful proof. A real resource-key fixture remains unavailable. No production publication, service purchase, source mutation or external message occurred.

## Implemented paths and boundaries

- Provider contracts: `app/Contracts/{DriveSource,ArchiveStorage,ArchiveUpload}.php`.
- Official Drive v3 adapter: `app/Integrations/GoogleDrive/`, fixed API host, resource-key headers, API key header, pagination, bounded jittered retries and sanitized native-status failures. Redirects are rejected. Original streams validate MIME, advertised/actual length, optional provider MD5 and source versions before/after transfer. Reading through `getContents()` cannot bypass verification.
- PostgreSQL staging: the `drive_proof_*` migration, `DriveProofRun`/`DriveProofNode`, and `ImportDriveProof`. Iterative folder traversal, page-level transactional checkpoints, encrypted metadata/resource keys/page tokens, per-run advisory import lock, duplicate-identity/page-cycle failures and separate unsupported/shortcut reports. An inaccessible descendant leaves the run incomplete; committed pages can resume.
- Worker: `BuildDriveProofArchive` atomically claims a local/test run by persisted identity. Its immutable staged metadata is read in ordered pages of 200. A new attempt has a unique private object key. Photo paths retain deterministic identity suffixes; long identities use SHA-256 suffixes. Source byte totals use 64-bit integers.
- Archive: locked ZipStream-PHP STORE/ZIP64 output through `BuildStreamingZip` and the official AWS SDK/R2 multipart adapter. One part spills to temporary disk above 1 MiB; adaptive equal nonfinal parts respect documented native R2 limits. Upload parts carry Content-MD5. Complete checks the final object's length/type; input/output failures abort/delete the whole attempt. Cleanup failure has a distinct persisted non-ready state.
- Operator command: `snappik:drive-proof`, with synchronous or queued build, resume, independent completed-object extraction verification and explicit cleanup. Only local/testing execution is allowed; runs are scoped to an existing synthetic local user. There are no public routes or supplied photographer/file IDs that grant media access. The job serializes only the run UUID.
- Standard integration suite and opt-in large proof; a separate secretless large-proof step was added to CI. Hosted CI was not executed here.

The prototype is separate from product catalogue revisions, client gate policy, publication, deduplication, crash leases, download routes and scheduled cleanup. Those remain TASK-004/TASK-007/TASK-008. Its 24-hour expiry is checked by the diagnostic verifier; this is not evidence of a finished client archive endpoint. No originals library, hosted upload, gallery quota, ZIP splitting or Drive-folder redirect was introduced.

## Runtimes and locked dependencies

Used the existing ignored portable PHP 8.4.27 and Composer 2.10.3 from TASK-001, plus the existing isolated PostgreSQL 18.6 instance. Added real locked dependencies `maennchen/zipstream-php` **3.2.2** and `aws/aws-sdk-php` **3.401.5**; Composer installed five new packages with no unrelated package upgrades. `composer.json` and `composer.lock` are consistent.

The initial sandbox Composer download failed DNS resolution and reverted its attempted changes. The authorized download outside that restriction succeeded. The Windows sandbox also prevented PHP filesystem checks and test bootstrap access, as in TASK-001; the actual successful PHP commands below ran with the required external execution permission. This was a tooling restriction, not a provider credential workaround.

## Commands and inspected results

Run from `D:\Website\snappik-app-gallery`. On this machine `.local-tools/php/php.exe` replaces `php`, and `php .local-tools/composer.phar` replaces `composer` in the [README](../../README.md).

| Actual command | Inspected result |
| --- | --- |
| `.local-tools/php/php.exe .local-tools/composer.phar require maennchen/zipstream-php:^3.2 aws/aws-sdk-php:^3 --no-interaction --prefer-dist` | Five packages installed, real lockfile written; package discovery passed |
| `.local-tools/php/php.exe vendor/bin/pint` | Passed on final source |
| `.local-tools/php/php.exe .local-tools/composer.phar lint` | Final non-mutating style check passed |
| `.local-tools/php/php.exe .local-tools/composer.phar validate --strict` | Manifest and lock valid |
| `.local-tools/php/php.exe .local-tools/composer.phar test -- --compact` | **50 passed, 176 assertions**, 2.74 seconds on final run; one intentional opt-in large-test skip |
| `$env:SNAPPIK_LARGE_PROOF = '1'; .local-tools/php/php.exe -d memory_limit=128M vendor/bin/phpunit --filter LargeZipProofTest` | **1 passed, 11 assertions**, 72.625 seconds on the final large run |
| `.local-tools/php/php.exe .local-tools/composer.phar audit --locked` | No security vulnerability advisories found |
| `.local-tools/php/php.exe artisan migrate --no-interaction` | Additive proof-table migration passed on the local development PostgreSQL database; test migrations also exercised against `snappik_test` |
| `.local-tools/php/php.exe artisan snappik:drive-proof --help` | Implemented command and all five options inspected |
| `node .local-tools/check-links.mjs` / `git diff --check` | Local Markdown links passed in 11 documents; no whitespace errors (Git emitted only its CRLF-normalization notice) |

A targeted common Google/AWS/private-key pattern scan of application/config/migration/test/documentation/CI source returned no matches. `git check-ignore` confirmed ignored environment files, generated streaming metrics and diagnostic output. This is a bounded source check, not a claim of comprehensive secret detection. The temporary 4.296 GB diagnostic ZIP was explicitly deleted after independent inspection.

The initial ordinary suite retained the eight foundation feature tests. New tests covered synthetic Drive URL rejection/resource keys/pages, original byte equality, HTML/truncation/surplus/checksum/version/restriction failures, native-status sanitization/retries, incomplete search, nested import/resume, cycle rejection, empty/nonfolder sources, owner scoping, production denial, multipart integrity/finalization/cleanup, duplicate names, extraction/CRC, expiry and failure of the second input **after an 8 MiB part has already uploaded**. That attempt never calls Complete, never gets `ready_at`, and deletes its partial object. These contract-suite failures are synthetic; actual native failures and the later expanded suite are recorded below.

No frontend source changed, so the TASK-001 browser/frontend suites were not rerun for this backend-only task. Existing browser evidence remains scoped to TASK-001 synthetic shells.

## Measured streaming profile

The final opt-in test generated a nonseekable synthetic JPEG-labelled source of **4,296,015,872 bytes** (4 GiB + 1 MiB), followed by another 31-byte entry. Source bytes were generated incrementally, without a source file or full source/archive buffer. The real AWS SDK sent multipart requests through a synthetic local HTTP transport fixture that wrote one temporary output file; it did not contact Cloudflare.

| Measurement | Final result |
| --- | --- |
| Files / aggregate source bytes | 2 / 4,296,015,903 |
| Single ZIP object length | 4,296,016,353 bytes |
| Multipart count / nonfinal part size | 513 / 8,388,608 bytes |
| Build / full extraction time | 59.988 s / 12.076 s |
| Peak PHP allocator memory | 65,011,712 bytes (**62 MiB**) |
| Enforced PHP memory limit / test budget | 128 MiB / peak below 128 MiB |
| Integrity | Both entry lengths, full extracted MD5s and CRCs matched; ZIP64 end records present; the second entry beyond the 32-bit offset boundary extracted successfully |

These numbers describe local CPU/disk/fixture behavior. They are not R2/Drive network throughput, OS resident memory, an arbitrary file-count memory guarantee or production capacity. Temporary part disk grows with the adaptive part size; at native R2 capacity it can be much larger than 8 MiB. The worker never requires the complete ZIP on local disk; the fixture and independent diagnostic extraction deliberately do. Final test output was deleted automatically; ignored metrics remain in `storage/logs/large-zip-proof.json`.

An earlier diagnostic output was additionally read using the bundled Python standard library `zipfile.ZipFile` and `testzip()`. Both entries were enumerated at the expected sizes and CRCs (`34ba8a1d`, `0fe56189`); `testzip()` returned `None`, meaning no entry failed CRC validation. That ignored diagnostic archive was removed after inspection.

### ZIP-reader compatibility observation

The initial large test used `ZipArchive::CHECKCONS`. The installed Windows libzip returned error **21 (inconsistent archive)** for this streamed zero-header ZIP64 layout. Ordinary `ZipArchive::open()` successfully extracted **every byte** with matching source MD5/CRC, and Python independently passed every entry's CRC. The final test uses ordinary opening plus explicit complete extraction/identity/length/MD5/CRC checks; it does not treat a directory listing alone as integrity proof. Small archives also pass the strict checker.

ZipStream's own [zero-header documentation](https://maennchen.dev/ZipStream-PHP/classes/ZipStream-ZipStream.html) describes ZIP-reader compatibility limitations. This is a material compatibility observation, not proof that every client extractor works. Actual target-extractor smoke tests remain required before dependent delivery tasks are declared complete. No vendor source was patched, ZIP64 disabled or output split to conceal it.

The library also retains central-directory records in memory in proportion to file count. This measured two-entry result proves bounded **byte streaming**; large-count memory profiling and any needed central-directory spooling remain explicit productization work, not a newly imposed gallery limit.

## Authorized provider smoke follow-up

Later on October 22, 2026, the owner reported that the ignored Drive/R2 configuration was ready. A safe preflight confirmed all six required settings were populated, `APP_ENV=local`, configuration was not cached, PostgreSQL was reachable and proof tables existed. Values, folder links, file identities/names and bucket identity were not printed. A synthetic local operator was created; its random password was neither printed nor used for login.

Automatic approval review initially required explicit consent for the sensitive 384-photo cross-service transfer. The owner explicitly approved transferring the entire configured source into one private R2 test ZIP, verifying it, and deleting it. A separate proposed extra one-photo negative upload was rejected as outside that specific consent and was **not executed**. Negative testing used generated bytes instead; it did not request Drive data or reuse source metadata. No access-control workaround was used.

The local diagnostic wrappers under ignored `.local-tools/` bootstrapped the existing Contracts, importer, command, database worker and verifier. They printed only safe run correlations, counts, byte totals, fixed error categories and measurements; they are machine-local instrumentation, not additional shipped application commands.

| Actual command / stage | Inspected result |
| --- | --- |
| `php .local-tools/task002-smoke.php preflight` | Required configuration, local environment, database and proof schema available; no secret values printed |
| `php .local-tools/task002-smoke.php import` | Run `01a16271-9bd5-73b3-bb72-8aef07e4afb7`: **384 photos, 3 folders including root, 1,130,185,579 source bytes**; all descendants staged; 3 default-size listing requests, 9.098 s, 28 MiB peak PHP memory |
| `php .local-tools/task002-smoke.php pagination` | Read-only Guzzle request middleware reduced `pageSize` to 100, leaving the shipped adapter default unchanged. **6 real API pages, 3 continuation tokens, 384 unique photo identities; exact set equality with the staged manifest** |
| `php .local-tools/task002-smoke.php originals` | Two original media samples: **4,555,467** and **6,604,815** bytes; both lengths and full MD5s matched provider snapshots |
| `php .local-tools/task002-smoke.php native-failure` | Synthetic nonexistent ID against the actual Drive API: HTTP **404**, sanitized `drive_not_found_or_resource_key_required`, nonretryable, no chained exception |
| `php .local-tools/task002-smoke.php r2-preflight` | Authenticated access to the configured R2 bucket passed |
| `php .local-tools/task002-smoke.php queue` | Existing scoped run dispatched through `snappik:drive-proof --owner=<local-id> --resume=<run-uuid> --queue` to the database-backed `drive-proof` queue |
| `php .local-tools/task002-smoke.php worker` | Actual `queue:work --queue=drive-proof --tries=1 --timeout=0 --stop-when-empty`; started **15:12:33**, failed **15:15:27 Asia/Makassar** after 2 min 53 s; queue drained |
| `php .local-tools/task002-smoke.php status` | **`zip_failed`, 16 completed photos / 89,911,622 bytes**, no `zip_bytes`, no ready state or verification time; persisted original failure category `drive_access_denied` |
| `php .local-tools/task002-smoke.php diagnose-input` | Input position 17: metadata still `canDownload=true`, unchanged version, expected 5,671,457 bytes; official `alt=media` returned **403 text/html**, with the fixed phrase **“automated queries”**. No JSON API reason was supplied. Raw response, source ID and URL were discarded |
| `php .local-tools/task002-smoke.php inspect-cleanup` | Failed real-source run: `ready_at=null`; authenticated HEAD returned **404**, object absent; **zero remaining multipart uploads** under that run's prefix |
| `php .local-tools/task002-synthetic-failure.php` | Actual R2 with **generated bytes only**: first 9 MiB input completed, second synthetic input failed after an 8 MiB part had uploaded. Run `01a16277-5856-7121-9dc9-e339412b718e` stayed `zip_failed`, `ready_at=null`; object absent and **zero remaining multipart uploads**, 6.029 s |
| `php .local-tools/task002-synthetic-complete.php` | Actual R2 with **generated bytes only**: run `01a1627a-785a-71cf-b2bd-c4f6a49c0f4b`, **2 entries / 9,437,215 source bytes / one 9,437,563-byte ZIP**; multipart complete and independent object readback/extraction passed CRC/full source MD5. 12.131 s through verification/privacy probe; 56 MiB peak PHP memory. Object then deleted and authenticated HEAD confirmed **404** |

The synthetic completed object returned HTTP **400** to an unsigned HEAD on the R2 S3 endpoint, with no successful anonymous object response. This does **not** independently verify bucket-wide privacy, R2 development URLs or custom-domain settings; those still require deployment configuration checks. No permanent object URL or provider identity was exposed. Temporary local extraction files and completed R2 test output were removed. Failed-run metadata remains encrypted in the local proof tables as diagnostic history.

### Failure classification and regression verification

The actual media failure was originally recorded as generic `drive_access_denied`; that historical record was retained. The adapter now inspects at most **16 KiB** from non-success responses, including streamed media errors. A 403 HTML error containing the fixed automated-query phrase maps to **`drive_automated_requests_blocked`**, remains nonretryable and has no HTML/media fallback. JSON `downloadQuotaExceeded` maps to its own terminal category; documented JSON rate-limit reasons on streamed requests use the existing bounded retry policy. Raw bodies are never stored, logged or returned. This is error classification only, not scraping or a changed credential model.

After this change, actual commands `.local-tools/php/php.exe vendor/bin/pint` and `.local-tools/php/php.exe .local-tools/composer.phar test -- --compact` passed: **53 tests / 188 assertions**, 49.74 s, with the existing opt-in large test intentionally skipped. Three regression cases prove sanitized terminal automated-query handling, terminal media download quota handling, and bounded retry of streamed JSON rate-limit errors. The previously measured ZIP64 writer was unchanged; its >4 GiB result above was not rerun or reattributed to a real provider.

The final `composer lint` equivalent passed. Local Markdown links passed in 11 documents and `git diff --check` reported no whitespace errors. A local exact-match scan for the configured API key, source folder URL and R2 credentials found no matches in the seven changed source/documentation files, representative application logs or the `drive-proof` failed-job exception records. Only safe booleans/file names were printed; `.env` and all diagnostic wrappers remain ignored. This was a bounded secret check, not a universal secret-detection claim.

Google's [Drive error guide](https://developers.google.com/workspace/drive/api/guides/handle-errors) distinguishes documented JSON reasons from an undifferentiated 403. This actual response supplied HTML and the automated-query phrase instead of a JSON reason. We therefore do not claim it proves an invalid key, a numeric API quota, permanent per-file denial or a requirement for OAuth. No keys, source permissions, networks or provider endpoints were changed to bypass the response; further media attempts stopped after diagnosis.

## Request pacing follow-up

The owner subsequently requested fixing the blocked transfer. Official [Google system-parameter documentation](https://docs.cloud.google.com/apis/docs/system-parameters) and [API-key guidance](https://docs.cloud.google.com/docs/authentication/api-keys-use) confirm the documented header and query forms. One controlled comparison used the **same previously failing input at manifest position 17**, the same existing key, API origin, source version and network. Both the existing header path and documented query form succeeded: **5,671,457 bytes**, full MD5 matched, 9.746 s and 5.268 s respectively. This does not establish a header-authentication bug. Query-form credentials were confined to the ignored diagnostic wrapper; no URL or key was printed or stored in source/logs. The shipped adapter retains header authentication.

Added `DriveRequestPacer` as an operational mitigation against request bursts, rather than claiming control over Google's transient blocking. The shared database cache/lock coordinates the same key across processes; only a SHA-256 fingerprint and next-start timestamp are cached. The default interval is 1000 ms, applies before every request and retry, and can be configured within 100–10000 ms. A cache/lock failure stops the request. Automated-query blocks remain nonretryable; no automatic evasion, network change, credential rotation, alternative endpoint or HTML fallback was added.

Inspected verification:

- `.local-tools/php/php.exe vendor/bin/pint` passed after formatting.
- `.local-tools/php/php.exe .local-tools/composer.phar test -- --compact`: **60 passed / 201 assertions**, 4.38 s; the unchanged opt-in large-stream test was intentionally skipped.
- Two separate `.local-tools/php/php.exe .local-tools/task002-pacer-proof.php` processes used only a synthetic key in the actual PostgreSQL-backed database cache, without Drive/R2 requests. Four recorded starts had adjacent intervals **1.002912 s, 1.006695 s and 1.012492 s**. This proves shared timing state across those processes, not an arbitrary-concurrency load test.
- New tests cover shared state across instances, credential separation, interval validation, sanitized cache failure and invocation before each media/metadata/retry request. Existing terminal-block and fail-complete archive tests remain passing.

The owner-approved full manifest was queued for a fresh attempt after the controlled read succeeded, reusing the existing credential and source identities with a fresh private object key. The actual database worker started at **16:21:26** and failed at **16:24:50 Asia/Makassar**. It completed **23 photos / 129,649,968 bytes**, then persisted **`drive_automated_requests_blocked`**, never became ready, and produced no completed ZIP length. Whole worker runtime including queue drain was **207.01 s**, with **56,623,104 bytes (54 MiB)** peak PHP memory. Authenticated R2 HEAD again returned 404 and the attempt prefix had **zero remaining multipart uploads**. Pacing is implemented and verified, but **did not resolve the provider block**.

A second bounded authentication comparison used the newly failing input at **manifest position 24** while the failure was reproducible. Both the header and documented query forms returned **HTTP 403 / `drive_automated_requests_blocked`**, after 7.509 s and 6.876 s respectively. No additional full attempt was started. A local read-only network preflight reported no populated HTTP/HTTPS/ALL/NO proxy environment variables and no Guzzle proxy configuration; routing was not changed. These observations do not establish the underlying cause of Google's anti-abuse response and do not support claiming a credential-transport fix.

### Credential review — owner retained the API-key boundary

The owner explicitly chose to **keep API keys and investigate Google's block**. The optional service-account proposal below was therefore not authorized or implemented. Investigation continued inside the existing public-source/API-key contract.

The public-source contract continues to use the existing restricted server API key. Changing that credential model requires the review defined by [Architecture](../engineering/ARCHITECTURE.md#public-source-contract) and [AGENTS.md](../../AGENTS.md). No OAuth library, service-account access, user impersonation, domain delegation, source-permission change or network rotation was installed or enabled.

## Canonical media-route correction

Further primary-source investigation found a concrete request-construction mismatch. The official [Drive v3 Discovery document](https://www.googleapis.com/discovery/v1/apis/drive/v3/rest) sets **`files.get.useMediaDownloadService=true`**. Google's [Discovery URL-construction guide](https://developers.google.com/discovery/v1/using#media_download) specifies inserting `/download` before `servicePath` for this case. The adapter had been sending media to the ordinary `/drive/v3/files/{id}?alt=media` path rather than the documented **`/download/drive/v3/files/{id}?alt=media`** path.

One bounded direct comparison on the same failing input at manifest position **24** retained the existing API-key header, resource key handling, network, API origin and source version. The old route still returned **403 / `drive_automated_requests_blocked`**; the documented media route returned **4,760,417 bytes**, matching both expected length and full original MD5 (5.101 s). An earlier single canonical-route probe also matched that input's bytes/MD5 (5.196 s). This isolates an actionable route-conformance correction without introducing credentials or provider-host changes; it does not claim the ability to override all possible Google refusals.

`PublicDriveSource` now selects the canonical `/download/drive/v3/` path only for `alt=media`; listing and metadata retain `/drive/v3/`. Redirects remain disabled, API keys stay in headers, and all download permission/version/length/checksum checks remain. No HTML fallback, service account, endpoint rotation, source mutation or permanent originals cache was added. The corrected route is a documented part of the **same official Drive API origin**.

Final route regression run: `.local-tools/php/php.exe vendor/bin/pint` passed; `.local-tools/php/php.exe .local-tools/composer.phar test -- --compact` passed **60 tests / 208 assertions**, 2.84 s, with the unchanged large proof intentionally skipped. Additional assertions check the media versus metadata paths, resource-key/API-key headers, absence of key values from URLs, and disabled redirects. The same complete owner-approved source was then queued for a new database-worker attempt using a fresh private R2 key. Its completed build, independent extraction and cleanup result follows.

### Complete canonical-route build and R2 readback follow-up

The database worker started the corrected attempt at **16:39:45** and completed at **17:21:14 Asia/Makassar**, draining the queue at 17:21:17. All **384 photos / 1,130,185,579 source bytes** produced **one 1,130,302,773-byte ZIP**, with no source failure. The worker profile recorded **2492.658 s** including queue drain and **56,623,104 bytes (54 MiB)** peak PHP allocator memory. It used the existing API-key header and the same authorized source, bucket and network; no Google source mutation or credential change occurred.

Initial completed-object verification failed during the long R2 response, before ZIP opening/extraction. A sanitized diagnostic retry reached **494,501,861 bytes** before a `RuntimeException` at **251.271 s**; its message did not match the diagnostic timeout phrase. The underlying cause of that transport interruption was not established, so it is not recorded as ZIP corruption or a proven five-minute timeout. Temporary diagnostic output was removed on failure; the completed private object was retained only for the authorized verification and cleanup.

`R2ArchiveStorage::read()` now reads contiguous ranges of at most **8 MiB**, pinned to the initial HEAD ETag using `If-Match`. It validates HTTP 206, exact Content-Range, declared/actual length and ETag before emitting that range. The total-object deadline is disabled while the connection timeout and 60-second idle timeout remain. Interrupted ranges have a three-attempt budget with bounded delay, in addition to the SDK's existing request budget; permanent 4xx and malformed/changed responses stop. Ranges read one ZIP object and do not create split outputs. An initial range attempt also failed after **25,165,824 bytes / 59.844 s**; no integrity success was claimed from it.

Final source checks after the bounded-retry addition: `.local-tools/php/php.exe vendor/bin/pint` and `.local-tools/php/php.exe .local-tools/composer.phar lint` passed; `.local-tools/php/php.exe .local-tools/composer.phar test -- --compact` passed **68 tests / 236 assertions**, 3.82 s, with the unchanged large proof intentionally skipped. New SDK transport cases cover multi-range identity/order, pinned ETag and request timeouts, wrong status/range/ETag, truncated/surplus bodies, interrupted-read recovery without duplicate bytes, and bounded sanitized repeated failure. The ZIP writer and its earlier >4 GiB proof were unchanged.

A diagnostic range process started before the retry addition reached **754,974,720 bytes (720 MiB)**, then stopped at **361.833 s** on an underlying `RuntimeException` read failure with an `ErrorException` predecessor. It had not opened the ZIP, so this was not evidence of a CRC/MD5 failure. Its temporary file was removed. Verification was then restarted using the tested three-attempt range recovery; no new Drive build or photo transfer was required.

The live-stream range retry recovered an actual interruption at offset **159,383,552**, then reached **905,969,664 bytes (864 MiB)**. At that offset another body-read failure was followed by two connection failures; the three-attempt budget stopped verification at **612.073 s**, still before ZIP opening. A subsequent authenticated R2 bucket preflight passed. PHP curl availability was confirmed. No network, credentials, source or bucket settings were changed.

The final readback implementation completes each bounded 8 MiB response through the SDK's normal transport (`stream=false`) before validating its bytes, instead of returning a live PHP socket response. Each request has a 300-second deadline, preserving the connection timeout, with no aggregate-object deadline. Same-object ETag/range checks and the bounded retry budget are unchanged. After this adjustment, the backend suite again passed **68 tests / 236 assertions**, **3.71 s**; the large writer test remained intentionally skipped because its source path was unchanged. Actual full readback was restarted on the existing private object.

| Final actual command / stage | Inspected result |
| --- | --- |
| `php .local-tools/task002-verify-diagnostic.php` | Same verifier body with ignored count/type-only diagnostic instrumentation and the final R2 adapter. **384 extracted entries / 1,130,185,579 original bytes / 1,130,302,773 downloaded ZIP bytes**; every expected identity, size, CRC and full original MD5 passed; persisted `verified_at` set |
| Readback/extraction profile | **524.395 s**, **69,206,016 bytes (66 MiB)** peak PHP allocator memory; no storage error diagnostics on the successful final attempt. This includes complete temporary diagnostic disk, not a whole-ZIP RAM buffer; its temporary file was removed |
| `php .local-tools/task002-smoke.php cleanup` | CLI `--cleanup` deleted the verified private object and set the run to `expired` |
| `php .local-tools/task002-smoke.php inspect-cleanup` | Authenticated object HEAD confirmed **404 / absent**, with **zero remaining multipart uploads** under the run prefix |
| `php .local-tools/task002-smoke.php status` | **`expired`, 384 completed photos, 1,130,185,579 source bytes, 1,130,302,773 ZIP bytes, failure null, verified true** |
| `.local-tools/php/php.exe .local-tools/composer.phar lint` | Final non-mutating style check passed after the normal-transport adjustment |
| `node .local-tools/check-links.mjs` / `git diff --check` | Local Markdown file links passed in 11 documents; no whitespace errors |
| `php .local-tools/task002-smoke.php secret-scan` | No exact configured API key, source URL or R2 credential matches in the **16 changed source/documentation files**, representative application logs or proof failed-job exceptions. Only safe file names/booleans were printed; this is a bounded check, not universal secret detection |

The complete configured catalogue gate is now verified. The original source was not changed, skipped or cached permanently; API-key authentication, Google API origin, network and configured destination stayed unchanged. Earlier failed attempts remain historical evidence, not successful integrity checks. A successful catalogue proof does not establish that Google can never return a future native refusal.

## Remaining provider and delivery gates

The [README smoke procedure](../../README.md#task-002-drivezip-proof) produced and independently verified one complete ZIP of the authorized photo catalogue, then removed it. Missing configuration and complete-catalogue media access are resolved for this proof. Remaining acceptance and productization boundaries:

1. Before declaring TASK-002 complete, exercise an authorized real resource-key fixture. The configured tree supplied **zero resource keys**, so header propagation remains covered by synthetic contracts only. The configured tree also had no duplicate original-name groups; deterministic duplicate-name behavior was tested synthetically.
2. Keep actual native failure categories and supported remedies visible. Real permission revocation/quota recovery was not exercised by mutating the owner's source. Automated-query blocks remain terminal; a different credential model requires review. Do not omit blocked inputs, scrape Drive HTML or reroute traffic to evade a refusal.
3. Check target ZIP extractors including ZIP64 offsets before dependent delivery productization, retaining the strict-libzip observation above. Assess large-count central-directory memory separately; the byte-stream proof is not an arbitrary-count memory guarantee.
4. Verify bucket public-development/custom-domain settings in the intended deployment environment. The unsigned S3 probe alone did not establish bucket-wide privacy. Product cleanup scheduling, crash recovery and orphan sweeping remain with their owning backlog tasks. Production publication remains separately unauthorized.

Official protocol references used: [Drive resource keys](https://developers.google.com/workspace/drive/api/guides/resource-keys), [Drive originals/downloadability](https://developers.google.com/workspace/drive/api/guides/manage-downloads), [ZipStream options](https://maennchen.dev/ZipStream-PHP/guide/Options.html), [AWS PHP multipart API](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html) and [R2 multipart sizing](https://developers.cloudflare.com/r2/objects/upload-objects/). These references informed implementation; reading them is not provider-integration evidence.
