# TASK-002 verification record

Date: October 22, 2026 (Asia/Makassar). Scope: public Drive and one streaming ZIP64 technical prototype. The owner's explicit TASK-002 request authorizes this implementation and its dependencies/checks, while final document-package approval remains pending in the [index](../index.md).

**Status: PROVIDER SMOKE PARTIALLY VERIFIED / COMPLETE SOURCE ZIP BLOCKED BY DRIVE HTTP 403. TASK-002 is not complete.** Initially the owner confirmed missing provider configuration and requested implementing the prototype with that blocker documented. The owner subsequently configured Drive/R2 and explicitly approved the complete 384-photo transfer, verification and deletion. The [authorized provider follow-up](#authorized-provider-smoke-follow-up) records the real results and current blocker. No production publication, service purchase, source mutation or external message occurred.

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

## Remaining provider gate

The [README smoke procedure](../../README.md#task-002-drivezip-proof) was exercised but **has not produced a complete ZIP of the authorized photo catalogue**. Missing credentials are no longer the blocker. Before declaring TASK-002 complete:

1. Resolve/investigate Google's actual media-request block through the supported provider access path. If a different credential/access model is necessary, obtain architectural review before changing it. Do not repeatedly retry the terminal block, omit blocked inputs, scrape Drive HTML or reroute traffic to evade the restriction.
2. Import/validate an appropriate current source snapshot, complete the full manifest through the database worker, independently verify every real-source entry's original bytes/CRC and verify cleanup. Existing samples and a generated-data R2 ZIP do not substitute for that end-to-end proof.
3. Exercise an authorized real resource-key fixture; the configured folder tree reported **zero folder resource keys**, so that case remains synthetic-only. Real permission-revocation/quota recovery was not exercised by mutating the owner's source. Keep native failures and their remedies explicit.
4. Check target ZIP extractors including ZIP64 offsets and retain the strict-libzip observation above until resolved. Verify bucket public-development/custom-domain settings in the intended environment. Production publication remains separately unauthorized.

Official protocol references used: [Drive resource keys](https://developers.google.com/workspace/drive/api/guides/resource-keys), [Drive originals/downloadability](https://developers.google.com/workspace/drive/api/guides/manage-downloads), [ZipStream options](https://maennchen.dev/ZipStream-PHP/guide/Options.html), [AWS PHP multipart API](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html) and [R2 multipart sizing](https://developers.cloudflare.com/r2/objects/upload-objects/). These references informed implementation; reading them is not provider-integration evidence.
