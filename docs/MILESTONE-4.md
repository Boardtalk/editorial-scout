# Milestone 4: Scheduling and Cron Health

Base: `feature/editorial-dashboard`, `10a1f32`.
Branch: `feature/scheduling-cron-health`. Candidate: `0.4.0-rc1`.
No live site was accessed, and nothing was deployed, published or merged.

## Design and behavior

Feed discovery has its own scheduling component and non-autoloaded configuration
option, separate from future research, draft-generation and publishing components.
There is no licensing implementation, API key, AI/image call or post creation.
The code stays GPL-3.0-only and retains the earlier feed security/discovery boundary.

| Mode | Source interval | Automatic worker |
| --- | --- | --- |
| Manual Only | None | No feed event; forced callbacks refuse work |
| Hourly (upgrade default) | 1 hour | Five-minute due-work poll |
| Daily | 24 hours | Five-minute due-work poll |
| Twice Weekly | 84 hours | Five-minute due-work poll |
| Weekly | 168 hours | Five-minute due-work poll |

Twice Weekly is a rolling interval rather than named weekdays. Infrequent checks can
miss items removed from feeds. A permanently visible, translated warning explains
this; no complete-coverage promise is made. Each callback handles at most five due
sources, each feed retains its 10-second transport / 1 MiB / 50-item bounds, and no
source is checked early because an external cron task runs every five minutes.
Hourly failure backoff remains two to 24 hours; longer modes never retry faster
than their selected interval. System DNS retains a separate resolver timeout.

Sources' due times are rebased from actual last checks when the mode changes;
unchecked sources stay due. Source IDs, categories, decision hashes and historical
attribution do not change. Individual Check Now still works in every mode. Both
manual paths share the existing atomic lease, SSRF boundary and persisted five-minute
cooldown. The automatic selector also respects a pending manual reservation.

Check All snapshots up to 50 enabled, nonarchived IDs and processes **one source
per explicit administrator POST**. Continuation is a form button, never a cron job
or JavaScript background loop. It survives navigation for one hour, belongs to its
initiating administrator, and requires capability, dedicated nonce and job token.
Disabled/removed/cooldown sources are skipped. Cursor checkpointing before networking
means an interrupted request is recorded on resume rather than silently replayed;
it may have already saved data. Replay of a completed token performs no fetch.
Totals distinguish successes, failures/interruption and skips; failures show only
fixed friendly messages. Normal pruning still protects all editorial decisions.

## Hosting independence and health

Only WordPress event APIs and the unchanged `editorial_scout_check_feeds` hook are
used. There is no hosting-panel identification, API, file path or configuration
dependency. Standard WordPress page-triggered cron and hosting-provided tasks,
cPanel, Plesk, DirectAdmin, managed WordPress, server cron or systemd timers work
wherever they correctly service WordPress cron. Tests assume none of these panels.
Generic guidance asks a hosting provider/website administrator to verify the runner;
optional WP-CLI guidance operates from the administrator's WordPress directory.

Automatic runs record start/completion, bounded counts and fixed outcomes while
holding the lease. Manual work never creates automatic execution evidence. Health
uses enabled-source due times, not merely an event's existence or DISABLE_WP_CRON.
It distinguishes no enabled sources, Manual Only, one-hour first-run grace,
missing events, absent execution with overdue work, fresh-heartbeat backlog,
interrupted runs and feed/storage failures. A long future source interval does not
become overdue simply because the last automatic callback was long ago. One hour
is a traffic allowance, not a guarantee; manual checks remain available.

The diagnostic is an allowlist of software versions, schedule/status/counts and
UTC completion/event times. No source URLs, names, accounts, paths, IPs, hostnames,
raw HTTP/SQL output or credentials are included. Copying uses local clipboard APIs
when permitted and otherwise selects the readonly textarea. No support transmission
is performed. Screens keep native labels, translation, escaping and responsive CSS.

## Migration, scheduling recovery and rollback

No table migration is introduced: schema **3** and its existing restartable upgrade
remain intact. Scheduling option version 1 initializes locally to Hourly without
changing due times. Event creation can retry on the next normal init; initialization
itself performs no feed request. New configuration is serialized against cron,
manual checks and mutations. Due updates plus configuration commit transactionally
when wp_options and plugin tables use InnoDB. Caches publish after commit and are
invalidated on rollback; workers refresh configuration after acquiring the lease.

Activation restores the configured event, configuration changes reconcile it,
Manual Only/deactivation remove only this plugin's hook (including argument-bearing
instances), and deactivation invalidates worker ownership. Unrelated WordPress jobs
are preserved. Default uninstall retains data; explicit opt-in also removes the new
option and plugin-specific user progress. Expired user progress is ignored, retained
at at most one record per participating administrator, and replaced on the next job.

Back up files and database together. Restore matching backups for a complete
rollback: 0.3.0 understands schema-3 editorial decisions, but does not understand
the new schedule option and would retain modified due times after file-only rollback.
Pre-schema-3 pruners must never run against upgraded protected evidence.

## Verification

- Local integration/security/lifecycle: **408 assertions passed on WordPress 7.1.3 /
  PHP 8.3.35**; an earlier PHP 8.2.34 run passed 404 assertions before four final
  initialization/health regressions were added. The final hosted matrix runs the
  complete suite on minimum WordPress 6.5/PHP 8.1, plus current PHP 8.2 and 8.3.
- **42 Chromium checks passed**: scoped axe WCAG 2/2.1 A/AA across all three sections
  at 375/768/1280px; every schedule option; manual-only health; coverage warning;
  safe diagnostic/copy fallback; real TLS individual check/cooldown and two-source
  synchronous manual continuation. External browser requests are blocked.
- WordPress coding standards for all eight production PHP files, PHP 8.3 syntax,
  JavaScript/shell syntax and reproducibility passed. CI also runs syntax on PHP
  8.0/8.2/8.3/8.4 and tests the extracted runtime ZIP, with browser checks on 8.3.
- Official **Plugin Check 2.1.0** (upstream
  `e8186b80c43c32b5841cabfdee8ebc21594bf89e`): **0 errors / 125 warnings**,
  comprising 66 direct-query, 58 uncached-query and one schema-change warning.
  These concern custom-table/atomic-lease operations, prepared transactional option
  updates, bounded fresh state/quota reads and explicit opt-in uninstall. Caching
  cannot substitute for current ownership/state; custom-table warning acceptance
  still requires Directory review. No warning was hidden from the raw checker report.
- Debug-enabled integration/browser runs showed no plugin PHP notices. Checker
  bootstrap produced WordPress core update-service TLS warnings in the isolated
  network, documented separately from plugin failures. Host CI may annotate pinned
  Node 20 actions/runtime and ubuntu-latest migration; these are tooling maintenance
  notices rather than feed or workflow failures. CI status is reported separately
  in the prepared PR description and task result.
- Reproducible ZIP: twelve runtime files under `editorial-scout/`, no development
  dependencies or fixtures. SHA-256:
  `4bee390d6d9e630e91501e8fd7c3e599e48eed652625d10550a4dc0632368286`.

The earlier security/discovery assertions are retained. Tests cover all modes,
rescheduling, actual standard cron spawning plus the real wp-cron.php dispatcher,
longer-interval polling, failure backoff, guarded manual-only callbacks, bounded
manual continuation, interrupted/completed/expired jobs, per-source cooldown,
independent-process method/capability/nonce/token gates, SQL rollback/cache refresh,
missing/failed event registration/retry, worker DB errors, absent initialization,
health grace/overdue/backlog/interruption/idle behavior, preservation of editor
choices and deactivation/reactivation/uninstall without unrelated event deletion.

## Remaining limitations

- Health is evidence and a heuristic, not hosting certification or an exact diagnosis.
  WordPress's own cron option concurrency, request timing, trusted server clock,
  hosting restrictions and blocked loopback requests can affect execution. The
  worker cannot create traffic on an idle site or configure a server scheduler.
- A callback can spend up to five transport deadlines plus parsing, DB and DNS work.
  A manual POST performs one source; PHP/proxy time limits can still terminate it.
  Lease recovery is roughly fifteen minutes. Interrupted manual attempts may have
  saved observations; deduplication makes explicit later retries idempotent.
- Protected retention limits and conservative URL-only grouping remain unchanged.
  An expired/abandoned job does not resume automatically. The administrator may
  start another; individual source cooldowns still apply.
- External persistent caches, every hosting environment, screen readers,
  Firefox/Safari and multisite are not certified. Multisite/proxy-dependent feed
  transport remain unsupported. Directory acceptance requires separate review.
- No security certification or WordPress.org approval is claimed.

## Changed files

Runtime: bootstrap/version; new `includes/class-editorial-scout-scheduling.php`;
runner, administrator handlers and dashboard; new local `assets/admin.js`;
readme/version; opt-in uninstall cleanup. Store/feed security classes are preserved.

Tests/build: new scheduling, cron-spawn and cron-dispatch harnesses; extended
integration, independent endpoint and browser fixtures/tests; test-only cron toggle;
packaging allowlist; CI candidate filenames. Runtime ZIP has twelve allowlisted files
and excludes all tests, dependencies, fixtures, credentials and development tooling.

Documentation: README, STAGING-TESTING.md, SECURITY.md, TESTING.md and this report.
