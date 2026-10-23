# SnapPik release and operator handoff

**October 23, 2026: local preparation only; public release blocked.** The owner confirmed that production services, domain, legal/support contacts and costs are not selected. The [TASK-015 evidence](../planning/TASK-015_EVIDENCE.md) records checks actually executed. The [backlog](../planning/BACKLOG.md#integrated-release-gate) owns the twelve release scenarios; this runbook does not waive them. Final package approval remains pending in the [index](../index.md).

## Current boundaries

`snappik:release-check` reads configuration, database state, operational freshness and optional private operator attestations. It sends no mail, contacts no provider, creates no order, changes no access and cannot publish. Exit **1** means blocked; **0** would mean its checked conditions passed, not permission to deploy. The current sandbox-only payment adapter makes `production_payment_adapter` unconditionally false. Merchant credentials or a manually edited evidence file cannot enable live payments. A reviewed production adapter, merchant/channel acceptance and separate publication authorization are required before public commercial operation.

No host has been provisioned, domain registered, certificate issued, notification sent, live transaction made or bucket policy changed during this task. The Linux Nginx/systemd files in [ops](../../ops/nginx.conf.example) are uninstalled examples. They require selected-host validation; they were not executed on this Windows workstation.

## Service and cost register

Fill this register from the actual chosen contracts and measured usage before approving release. Do not interpret unlimited application entitlements as unlimited or free infrastructure.

| Service | Actual selection | Price / usage basis | Required evidence |
| --- | --- | --- | --- |
| Application host, PHP-FPM, worker CPU/RAM/scratch disk | Not selected | Pending | Provider/region/plan, resources, monthly amount/currency/tax, capacity test, disk/resource alerts |
| PostgreSQL host and recovery | Not selected | Pending | Version, encrypted connections/network isolation, disk/connection capacity, recurring/backup charges |
| Domain and HTTPS | Not selected | Pending | Owner-controlled domain/DNS, registration/renewal, certificate/renewal method and tested HTTPS |
| Midtrans merchant and QRIS channel | Not selected | Pending | Sandbox lifecycle, live merchant/channel approval, actual channel fees and settlement terms |
| Transactional SMTP sender | Not selected | Pending | Verified sender/domain, delivery/authentication checks, mail allowance/overage and actual price |
| Private archive R2 bucket | Production not selected | Pending | Private access/range tests, operation/storage charges, abuse/cost alerts |
| Independent private backup bucket | Not selected | Pending | Separate bucket-scoped credentials, retention/orphan lifecycle, restore/key custody, storage/operation costs |
| Redacted monitoring and alert recipient | Not selected | Pending | Alert routing test and actual monitoring/notification costs |
| Legal/support publishing | Owner content/contact pending | Pending if applicable | Final approved documents, reachable public URLs, accountable support recipient |

The previously authorized TASK-002 test storage/source is not evidence that production services are selected. Do not reuse reference credentials or silently repurpose that test infrastructure.

## Configure a private staging environment

Use the locked PHP/Node/PostgreSQL prerequisites in [README](../../README.md#prerequisites), plus Bash, GnuPG 2.2+ and PostgreSQL 18 client tools for backup. Linux workers need `pcntl` for PHP job timeouts/signals. Use a dedicated unprivileged `snappik` service account. Restrict the network, database, cache/session tables and staging access before loading data. Serve only Laravel's `public/` directory. Never expose `.env`, repository files or private storage.

Create the private environment from [.env.example](../../.env.example), supplying secrets through the selected host's secret mechanism. Production-like staging settings:

| Setting | Required value / action |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `production`, `false`, the exact selected HTTPS origin; staging remains private |
| `APP_KEY` | Unique initial key; retain it outside the host for encrypted database fields; preserve it across releases |
| `SNAPPIK_RELEASE_COMMIT` | Actual 40-character Git commit of the deployed build; rebuild after any source change |
| Database | PostgreSQL, dedicated application role, actual host/name/password, required SSL mode; no public database listener |
| Sessions/cache | `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, secure/HTTP-only cookies, `SESSION_SAME_SITE=lax`, `CACHE_STORE=database`; domain queue/cache/locks use the same intended database |
| Mail | Keep `AUTH_MAILER=array` until a real sender/test message is authorized. Then configure `AUTH_MAILER=smtp`, SMTP host/port/TLS scheme, credentials and verified From identity; never use log/failover for account links |
| Drive | Restricted server API key; authorized public source/resource-key acceptance; never commit a source capability |
| Archive R2 | Separate private archive bucket and bucket-scoped credential; no public domain/r2.dev; actual permissions and Range readback must be demonstrated |
| Payment | Existing `MIDTRANS_SANDBOX_*` only. No production key is accepted by the current adapter. Match the configured sandbox merchant to stored order contracts |
| Documents | `SNAPPIK_TERMS_URL`, `SNAPPIK_PRIVACY_URL`, `SNAPPIK_SUPPORT_URL`: final owner-approved public HTTPS pages without userinfo/query/fragment; never credentials or private capability URLs |
| Backup | Complete the following backup section before `SNAPPIK_BACKUP_ENABLED=true` |

The shared footer renders only syntactically valid configured links, in every shell. Empty values remain absent; there is no invented legal identity, contact or legal approval. Validate the actual pages before recording `legal_support_approved`.

The Nginx template assumes direct TLS termination. A CDN/reverse proxy needs separately reviewed trusted-proxy configuration, Host forwarding, secure cookies and IP-based throttling tests. Do not trust all proxies as a workaround. Stock access/error logs and PHP-FPM access/slow/error logs may expose secret URL paths; see monitoring below.

## Build, migrate and supervise

The following are **future operator commands on an authorized private staging host**, not commands already executed there. Prepare a versioned release directory, link private shared `.env`/`storage`, and run as the intended operator. Preserve the old build and shared data. Never run `migrate:fresh`, seed synthetic fixtures, generate a new application key on an upgrade, or run destructive tests against that database.

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
php artisan snappik:release-check --json
```

Check each exit status before continuing. Remove any development `public/hot` marker from the release artifact. Do not ship `tests/Support` fixtures, local tools or raw test output as public files. Build-time Node dependencies need not run on the application host. Set permissions so only required storage/cache paths are writable; use a dedicated PHP-FPM pool/socket matching the template. Validate selected-host Nginx/PHP-FPM configuration and TLS before serving it.

After configuring the actual paths/users/PHP binary, install the [worker](../../ops/snappik-worker@.service.example), [scheduler service](../../ops/snappik-scheduler.service.example), [monitor service](../../ops/snappik-monitor.service.example) and [monitor timer](../../ops/snappik-monitor.timer.example) examples without the `.example` suffix. Inspect with `systemd-analyze verify` and `nginx -t` on that host before enabling. Then, within the authorized staging environment:

```sh
sudo systemctl daemon-reload
sudo systemctl enable --now snappik-worker@catalogues snappik-worker@archives snappik-worker@payments
sudo systemctl enable --now snappik-scheduler.service snappik-monitor.timer
php artisan snappik:operations-check --json
```

Use one worker per queue initially; measure selected-host concurrency/DB connections/CPU/memory/scratch/provider cost before increasing. This is operational backpressure, not a paid project/photo/byte quota. Import/ZIP jobs have no content-size timeout; `--max-time=3600` rotates a worker only between jobs. Payments retain their job-specific 60-second timeout and three attempts. Long archives must not starve payment workers. Services discard raw worker stdout/stderr; application SafeLog and owner diagnostics supply safe identifiers.

The continuously supervised `schedule:work` process starts maintenance, reconciliation, probes and daily backups in background processes with independent overlap locks. A slow backup must not block minute maintenance. Run exactly one scheduler service against this deployment; shared locks remain required. Do not wrap `schedule:run` in a short-lived systemd oneshot with default child termination: it can kill its background tasks as soon as the parent exits. A worker probe is fresh only when its **dispatch time** is under five minutes old; a delayed probe cannot pretend a stopped scheduler/backlogged worker is healthy. A busy single archive worker can legitimately age its probe: investigate capacity/active progress rather than killing a healthy large build.

`/up` proves application boot only. It does not verify database, workers, backup, providers or launch readiness. Prove the supervisor restarts workers after a controlled interruption, the scheduler runs without an interactive session, and health degrades when a worker/service stops. On deployment use `php artisan queue:restart` to drain workers, verify new worker build/heartbeats, restart the scheduler after its active background tasks drain, and reload PHP-FPM to clear old OPcache according to the selected host. Do not blanket-retry failed financial jobs: use the owner UI and authoritative reconciliation.

## Encrypted daily backups and isolated restore

Generate and escrow a GPG encryption private key on an independent recovery host. Keep the private key/passphrase and separately retained Laravel `APP_KEY` recoverable outside the application server and backup bucket. Import **only the public key** into the service account's private GPG home; verify its full fingerprint through the operator's trusted channel. Do not put key material/passphrases in this repository, evidence JSON, logs or command arguments.

Configure `SNAPPIK_BACKUP_BASH`, `SNAPPIK_PG_DUMP`, `SNAPPIK_GPG`, `SNAPPIK_BACKUP_GPG_HOME`, and the full public `SNAPPIK_BACKUP_RECIPIENT` fingerprint. GPG's agent socket paths must fit the selected platform's limits. Configure `R2_BACKUP_*` with a **different private bucket and different credential** from archives, restricted to backup storage. The application rejects shared bucket names/access-key IDs, even across accounts. Disable every public bucket endpoint and inspect access from an unauthenticated client. Configure independent bucket lifecycle/retention to retain at least seven complete daily recovery points and abort orphan multipart uploads after a day; inspect the effective policy before enabling. Retry objects have unique keys and do not replace an earlier good backup. This application deliberately has no backup-delete command.

After independent-key custody, bucket policy and a real restore are arranged, enable `SNAPPIK_BACKUP_ENABLED=true`, rebuild cached configuration, and run:

```sh
php artisan snappik:backup
php artisan snappik:operations-check --json
```

The scheduled time is **02:00 UTC / 10:00 WITA daily**. Backup uses the resolved application PostgreSQL connection, a custom-format consistent `pg_dump`, Bash `pipefail` and GPG encryption. Plaintext passes directly between processes. Only encrypted output is spooled under private storage (0600), uploaded with bounded multipart concurrency and per-part MD5, then checked by HEAD length/SHA-256 metadata. That check is not independent full-object integrity or restore proof. Failed dumps cannot upload an encryption of partial input. Ordinary failures remove the local spool; an abruptly killed backup may leave encrypted spool/orphan uploads. After verifying no live backup holds the lock, an operator may remove only the identified stale spool; provider lifecycle handles orphan uploads.

A successful upload records private shared-cache freshness. Last backup age of **26 hours** or a failed invocation produces a failing operational check; loss of cache state also requires verification/re-run rather than inventing a success time. External monitoring must also inventory retained objects and test restores: cache freshness does not prove retention, key availability or bucket privacy. Provision scratch disk for the encrypted dump and alerts for disk/inode/CPU/memory pressure. The dump process has a one-hour operational timeout; tune implementation/host capacity after measurement if needed, without imposing product quotas.

On an isolated recovery host, using its own database cluster/credentials and no live workers, mail or provider access:

1. Download one identified encrypted backup with independent recovery credentials. Verify its stored SHA-256 against downloaded bytes. Never use the archive bucket/24-hour cleanup as a backup system.
2. Decrypt with the escrowed private key into an operator-only temporary file. Require GPG exit **0** before inspecting or restoring it; integrity errors may leave partial plaintext, which must never be restored. Remove failed plaintext immediately.
3. Create a **new empty isolated database**, explicitly checking its host/name differ from production. Restore using `pg_restore --exit-on-error --single-transaction --no-owner --no-acl --dbname=ISOLATED_DATABASE DECRYPTED_DUMP`. Provision necessary roles/extensions separately; this is a single-database logical dump, not a cluster/role/PITR backup.
4. Configure an isolated application with the retained application key, array mail and synthetic/disabled providers. Verify catalogue/choices/history, grants, held/consumed/released voucher states, durable jobs and duplicate/replay invariants before starting any workers. Ensure no copied callback/outbox can call production services. Record restored table counts/digests and agreed recovery time/data-loss target.
5. Review and authorize any production recovery separately. Never restore over production as a test. After recording evidence, remove only the explicitly identified isolated database/plaintext files and restrict or destroy transient recovery-key copies according to the approved custody policy.

The automated synthetic proof runs the actual encryption pipeline, rejects missing-private-key/corrupted/truncated files, and compares every restored table plus replay/immutable-ledger behavior. It substitutes local encrypted-file capture for R2; real off-host readback, policy, custody, retention and selected-host recovery remain open.

## Monitoring and incident handling

Run `snappik:operations-check --json` privately each minute and alarm on its nonzero status. It emits only fixed check names/booleans for DB access, payment review, failed queue rows, scheduler/worker age and backup status. No public detailed health endpoint or new outbound notification integration is installed. Connect the selected monitoring provider to the service's failure state and verify an **authorized** alert reaches the designated operator before launch. Add external HTTPS/certificate expiry, safe 5xx/latency metrics, DB connection/disk health, queue age, active import/ZIP progress, source/provider failures, storage spend and actual seven-day backup inventory/restore alarms.

Keep URL paths, queries, cookies, Authorization, request bodies, payment/source payloads, emails and selections out of telemetry. The Nginx example logs time/status/method/bytes/duration only and suppresses stock raw error logs. Review load-balancer/CDN/WAF, PHP-FPM, OS, provider and external error-collector settings too; a safe application log cannot scrub upstream logs. Do not enable an SDK that captures request URLs/locals by default. Route troubleshooting through retained safe IDs and `/admin/jobs`, `/admin/orders`, accounts and voucher history. A failed import/ZIP retry must use the scoped owner UI. Payment review uses `snappik:payments-reconcile --order=APPLICATION_ORDER_UUID`; never manually mark paid or release an uncertain hold.

For an incident, make affected actions unavailable in the authorized environment, preserve the immutable financial/selection history, inspect redacted records, and resolve scope/provider gates before retrying. Archive cleanup is exact-key only and never targets backups. Do not run blanket object deletion, reset migrations or remove financial history. A code rollback returns to a retained compatible build and reloads PHP/workers; do not reverse migrations or restore production data without a separately reviewed recovery plan. Confirm changed-schema compatibility before switching. Keep durable payment intake/reconciliation available where possible and recheck missed callbacks after recovery.

## Evidence, release decision and handoff

Copy [release-evidence.example.json](release-evidence.example.json) to a private operator file. Set the actual build commit, exact origin and `checked_at` Unix timestamp for the final review (within 24 hours, not future-dated). Each fixed check requires `passed: true` and a nonempty `record` pointing to a private redacted acceptance record. Do not put credentials, tokens, source/client links, private object keys or personal data in it. These are operator attestations, not inferred provider facts. Source/build/origin changes require re-review; a false/missing/stale/mismatched record blocks the check.

```sh
php artisan snappik:release-check --evidence=/private/operator/release-evidence.json --json
```

Execute all twelve authoritative integrated scenarios on the intended private release environment. Retain test versions, commands, dates, operator identity, results and required screenshots/extraction/payment/restore records. Specifically close the TASK-002 real resource-key fixture and TASK-010/TASK-011 real sandbox QRIS lifecycle/missed-notification gates. Reverify private R2 full ZIP readback/Range, expected JPEG/PNG bytes and independently extract one complete archive with every source identity. Existing local tests do not prove selected-host permission or capacity.

The owner must supply/approve final legal and support pages (business identity/contact, actual service/payment terms, privacy handling and public-Drive disclosure consistent with PRD) and approve the actual cost register. Do not invent a retention, refund or legal policy. Record final document approval separately from task implementation requests. Present the exact candidate commit/origin, unresolved issues, service costs, recovery/monitoring ownership and test evidence for explicit publication approval. An approved live transaction, real mail/alert and a service purchase each retain their own authorization boundary.

Hand off a private record naming the release operator, backup-key custodian, on-call/support recipient, incident contact channel, actual dashboards, certificate/credential renewal ownership, tested recovery objectives, known limitations and rollback build. All are pending owner/service selection today. Publication remains blocked while any prerequisite is missing, including the current absent production payment adapter. No command or checkbox overrides this requirement.

Implementation guidance was checked against the official [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment), [scheduler](https://laravel.com/framework/docs/13.x/scheduling), [PostgreSQL 18 pg_dump](https://www.postgresql.org/docs/18/app-pgdump.html), [AWS SDK multipart](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html) and [R2 S3 compatibility](https://developers.cloudflare.com/r2/api/s3/api/) documentation. Selected-host testing is still required.
