# TASK-014 — Security, concurrency and recoverability evidence

Implemented and locally verified on October 23, 2026, at the owner's explicit request. **Status: IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING.** TASK-008/TASK-011/TASK-012/TASK-013 retain their existing real Drive resource-key and QRIS sandbox acceptance gates. Final documentation-package approval remains independently pending in the [index](../index.md).

## Delivered changes and defects corrected

- Added an adversarial HTTP matrix across photographer results/configuration, owner operations, client previews/originals/ZIPs, order reads/resume/checkout and signed quote ownership. Substituted identifiers cannot cross tenants or selection/delivery revisions; GET/HEAD/Range attempts fail before any source call. Added source/thumbnail attacks using metadata-service addresses, IPv6 loopback, deceptive host suffixes, trailing dots, backslashes, fragments and encoded control characters. Existing redirect, content/version/truncation and payment authenticity contracts remain exercised.
- Added independent-process lock-wait races for photographer reopening and capability rotation. The selection harness checks each worker PID in `pg_stat_activity` and requires a real lock wait before the parent releases or changes the gate, matching the existing checkout/payment race barrier. Old-epoch requests conflict without altering retained choices/history; rotated capabilities fail after the waiting request acquires the aggregate lock. Existing final-selection-slot, confirmation/edit, trial-project, last-voucher-slot/per-user, checkout-idempotency, duplicate receipt/order and simultaneous-renewal races remain part of the full suite. The strengthened focused selection run passed **8 tests / 80 assertions** before the final full rerun.
- Added actual process interruption at two new boundaries: after a persisted import page and after a grant INSERT before financial commit. The importer resumes committed checkpoints; killed financial transactions roll back grant/redemption/access changes, retain the hold and durable receipt, and recover through scheduler re-enqueue and duplicate-safe processing. The existing killed-ZIP test now independently opens the restarted output and checks all entry bytes/CRCs, in addition to partial cleanup, fresh-attempt identity and exclusion of overlapping live workers.
- Added application log projection through `SafeLogManager`/`SafeLog`, including configured, stacked, on-demand and emergency channels. Arbitrary messages, nested data, model/request objects, exception messages/chains/traces and processor extras cannot enter formatted output. Valid UUID operational IDs, loaded throwable classes and numeric HTTP provider status survive; fixed payment-review events retain their event name. A generated representative log is scanned without printing matched content.
- Added a source/log scanner that checks nonignored Git text files, configured application/provider secret values and selected credential/private-key/capability signatures. It reports file/line/category only. Positive synthetic fixtures verify detection and ensure findings never contain the matching values. This is a bounded scan, not an assertion that every possible credential format or historical log is covered.
- Added an authenticated encrypted PostgreSQL dump and isolated restore proof described below. The restore check exposed a shared PL/pgSQL trigger resolving `OLD.state` on `entitlement_grants`, where that field does not exist. It already rejected edits, but with SQLSTATE `42703` instead of its intended immutable-history error. The additive `2026_10_23_600000_harden_payment_history_trigger` migration checks the table before resolving event-only fields. Restored grant mutations now produce SQLSTATE `P0001`; earlier mutable payment events still process correctly. Its rollback retains the compatible hardened function until the original payment migration drops it.
- The first complete regression run also exposed CSP omission when the workspace's existing `public/hot` file was present. `SecurityHeaders` now exempts hot development only in `local`; a synthetic-marker test verifies CSP in `testing` and `production`, and the intended local exception. The existing development marker/server was left untouched.

No content quota, hosted upload, provider identity/credential policy, purchase term or live-service boundary was changed. No new dependency is required.

## Reproducible checks and inspected results

The local runtime was PHP **8.4.27**, PHPUnit **12.5.40**, PostgreSQL **18** and the repository's locked dependencies. All database-refreshing/process tests used the dedicated PostgreSQL `snappik_test` database and synthetic source/storage/payment transports. No real provider calls, messages, charges or deployment were part of these checks.

PowerShell commands were run from the repository root. The `.local-tools` paths identify the existing ignored portable tools, not shipped dependencies. Equivalent PATH-installed PHP/PostgreSQL tools work with the documented commands in [README](../../README.md#security-and-recovery-verification).

```powershell
$env:SNAPPIK_BACKUP_PROOF='1'
$env:SNAPPIK_PG_BIN=(Join-Path $PWD '.local-tools/postgresql/pgsql/bin')
.\.local-tools\php\php.exe vendor/bin/phpunit --log-junit storage/logs/task014-backend.xml
```

Final complete backend result: **297 tests / 3,396 assertions / 1 skipped**, no failures or errors, in **1:44.927**, with **102 MiB** peak PHP allocation. The sole skipped test is the opt-in above-4-GiB proof, executed separately below. This run includes the restore, new races/process checks with verified lock waits, all existing financial races, provider adapter contracts, cleanup/gate tests and the final logging/header changes. The initial full run failed its CSP assertion; the corrected complete rerun is the result reported here.

```powershell
$env:SNAPPIK_LARGE_PROOF='1'
.\.local-tools\php\php.exe -d memory_limit=128M vendor/bin/phpunit --filter LargeZipProofTest
.\.local-tools\php\php.exe -d memory_limit=128M vendor/bin/phpunit --filter ManyEntryZipTest
```

| Proof | Inspected result |
| --- | --- |
| Above-4-GiB ZIP64 | **1 test / 11 assertions**; 2 entries, **4,296,015,903 source bytes**, one **4,296,016,353-byte ZIP**, independent size/CRC/MD5 checks on every entry; **66 MiB peak PHP allocation** under a 128 MiB limit |
| Streaming profile | Build **70.244 s**, extraction **16.067 s**; 513 multipart requests, 8 MiB nonfinal parts; actual AWS SDK through local synthetic HTTP, not Cloudflare R2 |
| Large central directory | **1 test / 6 assertions** in **7.004 s**; **66,000 distinct ZIP entries** survive independent directory/first/last-byte reads; fresh-process peak **28 MiB**, sampled/retained allocation growth **0 bytes** at PHP allocator granularity |
| Import kill/resume | **3,000 photos**, 250-photo pages; worker killed before page two returns, with 250 committed photos and old publication intact; restarted revision contains exactly 3,000 distinct photo IDs; old saved choice retained; fresh worker peak **28 MiB** under 128 MiB |
| ZIP kill/restart | Tracked partial cannot be ready; slow live worker retains exclusivity despite expired lease; killed process loses lock, maintenance fails/cleans its attempt, retry creates a new key and one readable complete two-entry ZIP with matching bytes/CRCs |
| Payment kill/recovery | Killed after grant INSERT while transaction uncommitted; zero committed grants/redemptions, unchanged access and held reservation; stale durable receipt is re-enqueued once by recovery; two subsequent deliveries result in one paid order, grant and redemption, extending access by 720 hours |

These measurements concern the synthetic fixture and PHP allocator, not unrestricted physical provider capacity or operating-system RSS. Large archive and central-directory temporary files were removed by their proofs. Existing real 384-photo Drive/R2 evidence remains in TASK-002; it was not rerun or extended here.

Additional inspected checks:

| Command | Result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Full PHP formatting passed |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Manifest/lock validation passed |
| `.\.local-tools\php\php.exe .local-tools/composer.phar dump-autoload --no-interaction` | Optimized autoloader/package discovery passed |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Test schema restored after the backend suite; all 15 migrations, including the trigger fix, passed |
| `.\.local-tools\php\php.exe tests/Support/privacy-scan.php storage/logs/task014-privacy.log` | **331 text files / 0 findings**, including the representative application log |
| `node .local-tools/check-links.mjs` | Local Markdown links passed in **23 documents** |
| `git diff --check` | Patch whitespace passed |
| Read-only PostgreSQL catalogue query and local temporary-file check | **0 remaining `snappik_restore_*_test` databases**, **0 `snappik-dump-*` temporary files** |

The Windows sandbox initially reported PHP files/directories as unreadable/unwritable and denied loopback PostgreSQL connections. Affected Composer/PHPUnit/Pint/database checks were rerun successfully with the local authorization boundary. These environmental failures were not treated as application verification. An initial new HTTP fixture also lacked the preview/range interfaces; the corrected fixture explicitly forbids all provider methods and passes in the final complete run.

## Isolated encrypted backup restore

`BackupRestoreTest` creates synthetic published selection/delivery catalogues, an acknowledged choice, immutable confirmation history and a reopened epoch; a verified paid order/grant/consumed voucher; a pending held voucher; a provider-confirmed expired/released voucher; and durable payment/archive jobs. Its source database is guarded by the ordinary `_test` requirement.

1. `pg_dump --format=custom --no-owner --no-acl` captures the test database. Every public application table is independently hashed over sorted JSON row projections for comparison.
2. The bounded dump fixture is encrypted with a fresh random AES-256-GCM key/nonce and authenticated format context. The plaintext file is removed. Wrong-key and modified-ciphertext decryption fail; valid decryption reproduces the original SHA-256.
3. A random `snappik_restore_<16 hex digits>_test` database is created. The target is explicitly checked against the source and against its fixed naming pattern. `pg_restore --exit-on-error --single-transaction --no-owner --no-acl` restores there without replacing any existing database.
4. All **35 application table hashes** match, covering metadata, choices, confirmation/receipt history, financial records, audits, sessions and durable jobs. The final complete run's dump was **116,356 bytes**, encrypted file **116,384 bytes**; randomized fixture sizes can vary slightly.
5. The application explicitly switches to and checks the restored database. Encrypted project capability metadata decrypts with the retained application key. Current epoch is 2 and open, with the acknowledged choice and old immutable history preserved. One grant/redemption and the correct held/released reservation states remain; durable jobs remain present. A processed-payment replay and an original selection-operation replay neither duplicate financial effects nor reapply a pre-reopen edit. The restored grant-protection trigger rejects mutation with SQLSTATE `P0001`, and the complete row hashes remain unchanged.
6. The random restore database is dropped after closing its connection; plaintext/encrypted temporary files are removed in `finally`. Only names generated and validated by this test can be dropped. The original test database is not a restore target.

The proof is intentionally a bounded synthetic fixture. It does not supply production-size encryption streaming, retained-key distribution, an off-host bucket, backup scheduler, retention policy or a production restore procedure. The separately retained Laravel application key is necessary for encrypted database fields; the temporary dump encryption key is independent. Production backup key custody, off-host isolation, scheduled execution and operator restore verification remain TASK-015 inputs.

## Cleanup and privacy boundaries

The full suite re-exercises R2's SDK-backed exact-attempt cleanup: paginated orphan-upload enumeration aborts only the exact archive key, ignores a synthetic backup key returned in the same listing, deletes only that archive, retries cleanup failures and refuses a `backups/` cleanup request before any SDK operation. Archive authorization denies expiry at the exact 24-hour application deadline even when physical deletion fails. Backup data is outside the archive-key namespace; no broad bucket/prefix cleanup was introduced.

`LogPrivacyTest` writes `storage/logs/task014-privacy.log` with redacted on-demand/stacked/emergency failures and a payment-review record. Its assertions inject synthetic secrets into messages, URLs, exception chains, nested authorization data and processor extras; those values are absent from formatted output, while permitted identifiers/classes/statuses remain. The scanner checks that log plus nonignored Git text source using values read locally from ignored environment files without displaying them. Generated metrics/JUnit/log artifacts are ignored. No raw source credential or capability is added to the evidence.

CI now includes source/log scanning and the opt-in isolated restore with PostgreSQL 18 client tools, alongside its existing above-4-GiB proof. Its client-tool step uses the [official PostgreSQL Ubuntu repository setup](https://www.postgresql.org/download/linux/ubuntu/) rather than relying on Ubuntu's default major version. Hosted CI was not run here. No frontend asset or visual flow changed, so the TASK-013 browser evidence was not rerun; the backend suite exercises all changed HTTP/service boundaries. Production reverse-proxy/access logs and external telemetry require separate redaction checks before launch.

## Remaining gates

- TASK-008/TASK-011/TASK-012/TASK-013 prerequisite acceptance remains open through the authorized real Drive resource-key fixture and configured QRIS sandbox merchant/lifecycle/missed-notification evidence. Local adversarial tests do not close those gates.
- TASK-015 must verify actual infrastructure, private archive/backup buckets and permissions, off-host backup schedule/retention/key custody, production-like restore, worker/scheduler supervision, SMTP, HTTPS, safe access/monitoring logs and target/provider acceptance.
- Final document approval and production publication require their own owner approval. This task neither supplies nor asserts third-party security certification.

No additional live-provider transfer, real message, live payment, service purchase, credential change or production publication was performed.
