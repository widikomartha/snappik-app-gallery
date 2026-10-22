# TASK-006 implementation evidence

Date: October 22, 2026 (Asia/Makassar). Status: **IMPLEMENTED / PREREQUISITE ACCEPTANCE PENDING**. The owner explicitly requested TASK-006. TASK-005 acceptance still depends through TASK-004 on TASK-002's authorized real resource-key fixture, so this task is not declared complete. Final package approval and production publication remain separate decisions in the [index](../index.md).

## Implemented boundary

Photographer dashboard/project lists now show canonical selected/allowed counts, not-started/selecting/current-confirmed state, independent module publication and frozen/closed context, recorded selection update times, historical confirmation counts and result/configuration links. Results paginate current choices and immutable confirmation snapshots, retaining original name/path/identity and marking records absent from the current published catalogue. A reopened epoch is not currently confirmed merely because a previous confirmation exists. Confirmation history is independently paginated. All human-facing times specify WITA.

`ProjectProgress` authorizes fresh persisted photographer ownership and Read permission under shared user/account/project locks, using the existing writer lock order. Expiry retains view/export rights while existing mutation/reopen routes remain denied; suspension hides project data, including from the dashboard. A confirmation ID from another project cannot be substituted into a read/export. No client capability, provider link or credential is included in the progress response.

An additive nullable `selection_updated_at` timestamp is committed with accepted choice/confirmation/reopen operations, without configuration/import edits pretending to be client saves. Receipt replays leave the timestamp unchanged. Backfill uses the most recent known choice/confirmation time; historical reopen or deleted-choice times cannot be reconstructed from pre-existing records.

Visible pages poll every 15 seconds and support manual refresh. Transport failure retains the last acknowledged response with a persistent stale warning and retry action; a successful read clears that warning. Permission denial hides results/actions. An independent failed-import warning explains retained catalogue metadata. Availability is metadata membership, not a live original-byte/provider probe.

Filename preparation reads the complete draft or chosen immutable snapshot, across all UI pages. Unique names retain full original names; duplicates use relative paths, and duplicate paths append stable file IDs. Literal backslashes and CR/LF are escaped to keep one entry per line. Whole-set window counts precede 500-record chunk reads, so duplicate names on another chunk remain disambiguated. A temporary stream spills after 1 MiB and finishes under the read lock before response output; storage-write failure fails rather than returning a knowingly truncated list. The stream closes after output. Current UI exports include the displayed version/revision and reject changed results with 409. Full browser text is loaded only after explicit preparation. Clipboard rejection focuses/selects the persistent labeled textarea. Export does not confirm, reopen, mutate or contact a provider.

## Commands and inspected results

Windows commands used the ignored portable PHP runtime; browser commands added `.local-tools/php` to PATH and used `.local-tools/browsers` for Chromium. Backend tests and fixture writes use guarded PostgreSQL `snappik_test`; browser suites run after PHPUnit, separately from database-refreshing processes.

| Actual command | Inspected result |
| --- | --- |
| `.\.local-tools\php\php.exe vendor/bin/phpunit` | Final backend suite: **146 passed / 1,064 assertions**, one opt-in large-ZIP test skipped (147 total); 94 MiB reported memory |
| `npm run build` | Strict TypeScript and production Vite build passed |
| `npm test` | 2 files / 5 tests passed |
| `npm run test:browser:progress` | Final run: **4 passed**, at 360px/1440px in light/dark |
| `npm run test:browser:selection` | Existing selection/confirmation/reopen regressions: **4 passed** |
| `npm run test:browser:projects` | Existing configuration/publication/archive regressions: **4 passed** |
| `npm run test:browser` | **16 passed / 13 intentional skips**; isolated database-write suites run separately |
| `.\.local-tools\php\php.exe artisan migrate --env=testing --no-interaction` | Additive schema applied successfully to the isolated test database after PHPUnit |
| `.\.local-tools\php\php.exe artisan migrate --no-interaction` | Additive acknowledgement-time migration applied successfully to local development PostgreSQL |
| `.\.local-tools\php\php.exe vendor/bin/pint` with the TASK-006 PHP paths | Formatting passed |
| `.\.local-tools\php\php.exe vendor/bin/pint --test` | Repository formatting passed after correcting route import order |
| `node .local-tools/check-links.mjs` | Local Markdown file links passed in 15 documents |
| `git diff --check` | Passed |

The first targeted run caught PostgreSQL rejecting `FOR SHARE` on the paginator's aggregate count. Counting separately while holding the owner read lock, then locking the page query, fixed it; the full suite passed afterward. Initial Windows sandbox execution could not read PHP autoload files or rename Vitest temporary cache files; approved execution outside that sandbox passed. Strict TypeScript caught the browser test's textarea element type; its explicit cast was corrected before the final successful build. Review also moved account banners/create actions into the polled overview, so expiry does not leave an old active-account message on screen. No failed attempt is counted as a passing check.

## Inspected scenarios and remaining gates

Backend checks cover draft visibility before confirmation, configuration versus selection timestamps, current confirmation/reopen transitions, retained immutable history after later draft removal, historical/current result pagination, missing-original warnings, simultaneous expiry/archive/deadline/unpublication context, read/copy after expiry with reopen denied, tenant/project/confirmation substitution, admin and suspension denial, stale catalogue warnings and stale draft export rejection. A **522-choice export** spans 27 UI pages and two server chunks, preserving duplicate filenames across chunks, duplicate paths and unusual newline/backslash names. Copy leaves selection versions and confirmation counts unchanged. Twelve historical confirmations paginate without changing the selected snapshot.

Browser scenarios use real authentication/session/application reads with CLI-only synthetic selection transitions, without fixture HTTP routes. Both 360px and 1440px are exercised in light/dark; narrow runs use reduced motion. Checks cover live draft counts, export of both identical-path identities, denied clipboard selection, successful clipboard acknowledgement, a 503 refresh retaining prior data, confirmation then reopening, an unavailable historical original after draft removal, expired read-only history export, failed catalogue-refresh warnings and theme switching without losing prepared text. The final expanded scenario also expires an account while its dashboard is open and checks that refresh replaces the active-account banner. They assert no JavaScript page errors or page-level horizontal overflow. Screenshots contain synthetic data only and are ignored under `test-results/`.

The final 360px light historical-results and 1440px dark overview screenshots were visually inspected for readable labels, publication/expiry context, unavailable-result warnings, wrapping and accessible export controls. The final expanded four-scenario progress rerun passed after the account-banner correction. Screenshots remain under ignored `test-results/`; Playwright replaces that output on later runs. CI includes the new dedicated progress command; hosted CI was not run in this task.

TASK-005's prerequisite acceptance remains open through TASK-002's real resource-key fixture. No new live-provider transfer, real message, credential change, source modification, production publication or large-ZIP rerun was performed. No analytics suite, Telegram/WhatsApp/freelancer messaging, hosted upload or numerical content quota was added. Whole export text is intentionally mounted only on request; browser clipboard/text capacity and deployment temporary-storage capacity remain operational limits rather than plan quotas.
