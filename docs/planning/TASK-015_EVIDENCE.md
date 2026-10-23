# TASK-015 — Local release preparation evidence

Implemented locally on **October 23, 2026** at the owner's explicit request. **Status: LOCAL PREPARATION IMPLEMENTED / RELEASE GATES PENDING.** The owner confirmed production host/domain, merchant, SMTP sender, backup destination, monitoring and legal/support inputs are **not selected**, and chose local preparation. No service costs are invented. TASK-014 prerequisite acceptance and final document approval remain open; see the [index](../index.md), [backlog](BACKLOG.md#task-015--prepare-and-authorize-public-release) and [release runbook](../operations/RELEASE_RUNBOOK.md).

## Delivered implementation

- `snappik:release-check [--evidence=PRIVATE_FILE] [--json]` reports effective production/HTTPS/session/cache/queue/provider/mail/backup/document configuration, migrations, owner/Pro state, private health and twenty-five fixed operator evidence gates, including the twelve integrated scenarios. Evidence must match build commit and origin and have a nonfuture review timestamp under 24 hours old. All-false/missing/stale evidence fails. Reports contain fixed booleans, never input values or private record contents. The command creates no order, grant, domain job or provider request and cannot authorize publication. The current sandbox-only gateway forces `production_payment_adapter=false`, even when every operator attestation says passed.
- `snappik:operations-heartbeat` sends probes through each real named PostgreSQL product queue. Handlers retain the newest **dispatch** time under a lock, so backlog, replay and out-of-order delivery cannot pretend the worker/scheduler is fresh. `snappik:operations-check [--json]` reports DB access, pending payment-review/failed-queue alarms, five-minute scheduler/worker freshness, 26-hour backup freshness and last backup failure. No detailed public endpoint or outbound notification service is installed.
- `snappik:backup` streams PostgreSQL custom-format output directly through GPG public-key encryption, using a static Bash program with `pipefail`. GPG pins a full recipient fingerprint, disables automatic key retrieval/config options and uses AES-256. Only an encrypted 0600 spool is created; PHP does not capture plaintext process output. The daily schedule is disabled by default and, when enabled, runs at 02:00 UTC / 10:00 WITA. Ordinary failures clean the spool and cannot record success. Killed processes can leave encrypted spools; the runbook describes exact-file investigation and independent orphan-upload lifecycle.
- A separate `BackupStorage` contract/R2 adapter requires different archive/backup buckets and access-key IDs. It uploads unique backup keys with adaptive multipart size, one concurrent part and per-part MD5, requests exact-upload abort on multipart failure, and checks completed length/SHA-256 metadata. It sends no unsupported R2 ACL headers. No application backup-delete/list/retention path exists. Actual bucket privacy, retention, off-host readback and private-key custody still require intended-environment evidence.
- All periodic commands run independently in the scheduler background with overlap locks. Uninstalled Linux Nginx/systemd examples supervise three independent workers, a persistent `schedule:work` process and private monitoring. The runbook avoids a oneshot service that would kill its background children on exit. Host/domain/TLS/PHP paths and alert routing remain deliberately unconfigured; these files were not installed or validated by Linux tools here.
- Owner-supplied HTTPS terms/privacy/support links are validated before sharing with every shell; empty or unsafe values are not rendered. Public/auth browser checks use synthetic pages without navigating to external services. No business identity, legal policy, support contact, retention/refund policy or approval is invented.
- The source/log scanner now also checks configured backup/SMTP/database secret values and armored PGP private-key signatures. Existing application log redaction and domain access rules remain in force. No Composer/npm dependency or schema migration is added; Bash/GPG/PostgreSQL client tools are operational prerequisites.

## Local commands and inspected results

Windows workspace: `D:\Website\snappik-app-gallery`, portable PHP **8.4.27**, PHPUnit **12.5.40**, PostgreSQL **18**, existing locked packages, Git Bash **5.3.15**, GnuPG **2.4.9**. All destructive database checks used the guarded dedicated `snappik_test` database and synthetic providers. No remote-provider transfer or real message/transaction was performed.

```powershell
$env:SNAPPIK_BACKUP_PROOF='1'
$env:SNAPPIK_PG_BIN=(Join-Path $PWD '.local-tools/postgresql/pgsql/bin')
.\.local-tools\php\php.exe vendor/bin/phpunit --log-junit storage/logs/task015-backend.xml
```

The initial complete regression passed **305 tests / 3,508 assertions / 1 skipped**, in **1:43.774**, with **104 MiB** peak PHP allocation. The final complete rerun after privacy/GPG option hardening also passed **305 tests / 3,508 assertions / 1 skipped / 0 failures / 0 errors**, verified from the completed `storage/logs/task015-backend.xml` report. The sole skip is the independent opt-in ZIP proof above 4 GiB, already recorded in TASK-014 and unchanged here. The complete suite includes existing tenant/financial/selection/queue recovery races and the new release, backup and SDK contracts. The new SDK test independently passed **35 assertions** after its final transport-fixture correction.

| Check actually executed | Inspected result |
| --- | --- |
| `npm run build` | TypeScript and production Vite build passed |
| `npm test` | **5 tests / 2 files passed** |
| `npm run typecheck` | Passed after browser/config changes |
| `.\.local-tools\php\php.exe vendor/bin/pint` | Full PHP formatting passed |
| `.\.local-tools\php\php.exe .local-tools/composer.phar validate --strict` | Manifest/lock validation passed |
| `npm run test:browser:release` | **4 passed**, 360px/1440px × light/dark, public/login/register footer links, keyboard focus and no page-level horizontal scrolling |
| Local production-mode `config:cache`, `route:cache`, `view:cache` | All passed using `.env.testing`, `APP_ENV=production`, a synthetic HTTPS origin and isolated `.local-tools/task015-cache` paths; those caches were then cleared |
| `artisan migrate --env=testing --no-interaction` | All 15 existing migrations passed; no new migration |
| `artisan schedule:list --env=testing` | Maintenance/reconciliation/probes every minute, backup daily 02:00 UTC; listing does not claim the disabled backup is enabled |
| `artisan snappik:release-check --env=testing --evidence=docs/operations/release-evidence.example.json --json` | Expected exit **1**, `ready=false`, real configuration/evidence gaps remain explicit; no raw values printed |
| `php tests/Support/privacy-scan.php storage/logs/task014-privacy.log` | **359 text files / 0 findings**, including new source and the representative redacted application log |
| `node .local-tools/check-links.mjs` | Local Markdown file links passed in **25 documents** |
| `git diff --check` | Passed |

The browser command prepended `.local-tools/php` to PATH and set `PLAYWRIGHT_BROWSERS_PATH=.local-tools/browsers`. All four generated `test-results/release-*.png` screenshots were inspected: narrow links wrap within the viewport, both themes remain readable, and focused support controls remain visible. No external legal/support page was opened. The fixtures' `.example.test` URLs are synthetic, not actual owner documents. Existing integrated browser suites were not rerun in full; the changed footer is directly covered by the new suite and shared-prop backend tests.

The sandbox initially denied PHP dependency readability/write checks, loopback PostgreSQL connections and Vitest temporary-file renames. The affected commands were rerun successfully outside that Windows sandbox. The first GPG setup failed because MSYS needed POSIX paths, then because its temporary agent socket path was too long; the disposable helper now normalizes those paths and uses shorter private fixture directories. The first SDK fixture used a numeric HTTP header and then a non-AWS error rejection shape; the corrected fixture exercises actual multipart failure/abort and metadata rejection. Initial isolated Laravel cache paths used an unsupported Windows absolute override; relative application-cache paths passed. These failed attempts are not counted as successful application verification.

## Streaming, failure and isolated-restore proof

`BackupRestoreTest` now uses the actual `DatabaseBackup` pipeline. The test imports only its synthetic public key into the encryption home, retains its private key in a separate temporary recovery home, and captures encrypted output locally through a fake destination. It restores the same synthetic selection/confirmation/history, paid grant/redemption, held/released vouchers and durable jobs used by TASK-014.

1. A real PostgreSQL custom dump is encrypted through the production pipeline with no plaintext dump spool. Decryption using only the public-key home fails. Modified ciphertext and a truncated encrypted file fail GPG integrity/decryption. Only the valid decrypt proceeds to restore.
2. A newly created, random, validated `snappik_restore_*_test` database is distinct from the source. Real `pg_restore --exit-on-error --single-transaction --no-owner --no-acl` restores there. All **35 application table digests** match, encrypted project metadata is readable with the retained application key, saved choices/history and all financial accounting survive.
3. Duplicate financial and old-epoch choice replay cannot create another grant/redemption or alter the restored snapshot. The restored immutable-ledger trigger still rejects mutation with SQLSTATE `P0001`.
4. Temporary recovery databases, decrypted/encrypted files, synthetic key homes and their scoped GPG agents are cleaned. The first complete run measured **116,366 dump bytes / 24,079 encrypted bytes**; compression/randomized fixtures mean exact sizes vary.

`OperationalBackupTest` encrypts **83,886,080 bytes** through an external streaming producer with **0 bytes** measured extra peak PHP allocator growth in the complete run. This measures PHP allocation, not total GPG/Bash/process RSS or production-size disk usage. A producer that emits partial input then exits unsuccessfully cannot call storage; a failing synthetic destination cannot update success freshness. Both remove the spool. A later successful attempt records freshness and clears the failure alarm. The SDK test independently checks bytes, per-part MD5, exact bucket/key routing, absence of ACL headers, abort on failed parts and rejection of mismatched final metadata without contacting Cloudflare.

An additional real local queue smoke used `.env.testing` with `CACHE_STORE=database`:

```powershell
php artisan snappik:operations-heartbeat --env=testing
php artisan queue:work catalogues --queue=catalogues --once --env=testing --timeout=0
php artisan queue:work archives --queue=archives --once --env=testing --timeout=0
php artisan queue:work payments --queue=payments --once --env=testing --timeout=60
php artisan snappik:operations-check --env=testing --json
```

All three actual database queues consumed their operational probe successfully. Database, review/failed-queue state, scheduler and every worker reported true. `backup_recent=false` correctly kept the aggregate exit at **1**: no real off-host backup was configured. This is a bounded loopback queue execution, not proof of unattended supervisor/timer operation on a selected host.

## Launch decision and remaining gates

**Do not publish.** The owner chose local preparation; the runbook's service/cost register accurately shows selections and prices pending. No Linux host/domain/certificate, production database, merchant/channel approval, SMTP sender, production archive/backup policy, off-host backup recovery, alert delivery or owner legal/support content has been verified. The implemented payment transport remains sandbox-only. Final package and publication approvals remain separate pending events.

All twelve release scenarios remain intended-environment gates. Prior local implementation evidence supports preparation, not blanket acceptance:

| Integrated scenarios | Existing local evidence | Outstanding release evidence |
| --- | --- | --- |
| 1–2 onboarding/trial/shared selection | TASK-003–TASK-006, TASK-013–TASK-014 | Actual authorized mail/source/resource-key acceptance and intended-environment journeys |
| 3–4 delivery/ZIP/recovery/expiry | TASK-002, TASK-007–TASK-008, TASK-014 | Resource-key fixture, private target R2/Range, complete independently extracted target archive, supervisor/restart behavior |
| 5–6 checkout/renewal/access expiry | TASK-010–TASK-011, TASK-013–TASK-014 | Configured real sandbox QRIS/lifecycle/missed notification, merchant approval, reviewed production transport before live use |
| 7–8 unlimited paid behavior/admin boundaries | TASK-004, TASK-012–TASK-014 | Intended-host resource/concurrency/provider/cost checks and actual owner handoff |
| 9 operational recovery/HTTPS/mail/monitoring | TASK-014 and this local backup/probe proof | Selected-host HTTPS, unattended workers/scheduler, authorized SMTP/alerts, independent retained off-host backup restore/key custody |
| 10–11 appearance/motion | TASK-013 and new footer browser checks | Intended release build inspection at both widths/themes and both motion preferences |
| 12 final release review | Runbook and all-false evidence template | Closed defects, actual service costs/support/legal content, final document approval and explicit candidate publication approval |

No purchase, public deployment, live payment, real email/alert, additional Drive/R2 transfer, credential change or destructive production recovery was performed. Hosted CI and selected-host Nginx/systemd validation were not run. The prior above-4-GiB archive proof was not repeated because archive construction did not change.
