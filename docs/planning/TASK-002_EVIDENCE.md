# TASK-002 verification record

Date: October 22, 2026 (Asia/Makassar). Scope: public Drive and one streaming ZIP64 technical prototype. The owner's explicit TASK-002 request authorizes this implementation and its dependencies/checks, while final document-package approval remains pending in the [index](../index.md).

**Status: PROTOTYPE IMPLEMENTED / PROVIDER VERIFICATION BLOCKED. TASK-002 is not complete.** The owner confirmed that the authorized public Drive source and private R2 environment configuration are unavailable and requested implementing the code with this blocker documented. No real Drive folder, API key, R2 token or bucket was substituted; no production publication, service purchase or external message occurred.

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

The ordinary suite retains the eight foundation feature tests. New tests cover synthetic Drive URL rejection/resource keys/pages, original byte equality, HTML/truncation/surplus/checksum/version/restriction failures, native-status sanitization/retries, incomplete search, nested import/resume, cycle rejection, empty/nonfolder sources, owner scoping, production denial, multipart integrity/finalization/cleanup, duplicate names, extraction/CRC, expiry and failure of the second input **after an 8 MiB part has already uploaded**. That attempt never calls Complete, never gets `ready_at`, and deletes its partial object. No real provider failure has been observed; native HTTP/SDK failures in these tests are synthetic.

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

## Real-provider gate still open

The [README smoke procedure](../../README.md#task-002-drivezip-proof) is implemented but **not executed against Drive/R2**. To close TASK-002, configure an authorized test folder with nested folders, multiple pages, resource-key cases and duplicate names, plus a private temporary R2 test bucket. Record only safe correlation IDs, counts, checksums, statuses and measurements. Then:

1. Run the official server-API-key import through all descendants. If Google requires a different credential/access model, stop for architectural review; do not scrape HTML or silently add photographer OAuth.
2. Compare individual originals with the authorized fixture bytes, build and independently verify the one completed R2 object, and check target ZIP extractors including ZIP64 offsets.
3. Exercise real permission/resource-key/revocation/quota behavior and record sanitized native failures and remedies. Confirm a failing second source cannot publish ready output, completed-object reads remain private, and cleanup succeeds.
4. Exercise the database queue with the actual provider-backed job, then verify and explicitly clean its private output using the run UUID. Production publication remains separately unauthorized.

Official protocol references used: [Drive resource keys](https://developers.google.com/workspace/drive/api/guides/resource-keys), [Drive originals/downloadability](https://developers.google.com/workspace/drive/api/guides/manage-downloads), [ZipStream options](https://maennchen.dev/ZipStream-PHP/guide/Options.html), [AWS PHP multipart API](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html) and [R2 multipart sizing](https://developers.cloudflare.com/r2/objects/upload-objects/). These references informed implementation; reading them is not provider-integration evidence.
