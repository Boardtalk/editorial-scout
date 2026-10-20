# 0.4.0-rc1 staging upgrade and rollback

Use an isolated single-site WordPress installation with PHP cURL/DOM/mbstring and
InnoDB, including the WordPress options table. No hosting panel, external service,
API key or paid account is required. Do not test against a valuable live site.

## Install or upgrade from 0.3.0-rc1

1. Back up the database and plugin files together, then deactivate Editorial Scout.
   Leave the site's existing, correctly functioning WordPress cron runner unchanged.
2. Build `python3 tools/build-staging.py`. In `artifacts/`, verify
   `sha256sum -c editorial-scout-0.4.0-rc1.zip.sha256`.
3. Upload `editorial-scout-0.4.0-rc1.zip` through Plugins → Add New → Upload Plugin
   and replace the old version, or extract its `editorial-scout/` root into the
   WordPress plugins directory. Use directories 0755 / files 0644; upload no tests
   or development dependencies.
4. Activate. Schema 3 stays unchanged. A new non-autoloaded scheduling option defaults
   to Hourly and preserves existing due times. Local initialization/retries fetch
   nothing. Older schema migrations are retained. Confirm categories, discoveries,
   shortlist/dismiss decisions and historic coverage before changing settings.
5. Open Settings. Confirm Hourly, the Cron Health Monitor and diagnostic textarea.
   Event-registration failures preserve intent and retry on the next load; do not
   ignore an upgrade or scheduling error.

## Hosting-independent execution

Standard WordPress traffic-triggered cron works without plugin-specific server
configuration. Low-traffic sites may benefit from an external task servicing ordinary
WordPress cron every five minutes. Any hosting panel or scheduler can provide it;
Editorial Scout neither detects nor configures one.

Optional administrator example: run `wp cron event run --due-now` from the WordPress
installation using the account/PHP environment recommended by the hosting provider.
An existing provider-supported request to the site's `wp-cron.php` entry point can
also service due work. Ask the hosting provider or website administrator for the
appropriate command and permissions. Never expose credentials in a URL or diagnostic.
Only disable page-triggered cron after validating the replacement. The plugin's
unchanged `editorial_scout_check_feeds` hook remains compatible with existing runners.
The five-minute worker services less frequent per-source schedules; it does not
fetch weekly sources every five minutes. No unrelated cron events are changed.

## Manual and automatic checks

- Use a direct public feed such as `https://wordpress.org/news/feed/` or
  `https://www.nasa.gov/feed/`. Availability/redirects can change; automated tests
  use deterministic local RSS/Atom/TLS fixtures with no public feed dependency.
- Test all five modes. In Manual Only there is no automatic plugin event and even
  a directly invoked automatic callback must not fetch. Other WordPress cron jobs
  must continue working. Check Now must work independently in every mode.
- Check Now twice immediately: the second request must display the five-minute
  cooldown. Source edits and disable/re-enable must not reset it. Disabled sources
  offer no Check Now action.
- Check All with multiple enabled sources. Each POST handles one source; Continue
  performs the next request without relying on cron. Closing the page pauses it.
  Confirm successful/failed/skipped counts, cooldown skips, safe errors, expiration
  after one hour and rejection for editors/subscribers or missing/invalid nonces.
- For Daily/Twice Weekly/Weekly, verify next due times relative to actual checks.
  Twice Weekly means every 84 hours, not fixed weekdays. Repeated five-minute cron
  callbacks must not fetch sources before due. A newly added feed starts due.
- Confirm the visible warning: infrequent monitoring can miss items discarded from
  the feed. Complete coverage is never guaranteed. There is no publishing schedule.
- Health: allow up to one hour after enabling/changing a schedule. With enabled due
  sources, confirm an actual automatic completion. Manual checks do not provide
  cron evidence. In a disposable setup, stop the runner and wait beyond the traffic
  allowance to check overdue guidance; restore it afterwards. DISABLE_WP_CRON alone
  must not produce an error. An inactive/low-traffic site cannot guarantee timing.
- Copy Diagnostic Report: it should contain fixed status/count/version/timestamp
  fields, never source URLs, credentials, server paths, IPs or account names. The
  button selects text for manual copying if clipboard permission is unavailable.
- Confirm existing filters, categories, shortlist/dismiss choices and source coverage
  still work. Review narrow screens, keyboard controls and debug logs.
  **No posts, AI requests, image-service calls or public content should appear.**

## Rollback

Deactivate the candidate. Keep `EDITORIAL_SCOUT_DELETE_DATA` unset/false. Restore
both the previous plugin files and matching database backup, then reactivate and
verify the previous cron schedule. Schema 3 has not changed, but restoring only
0.3.0 files leaves changed due times and ignores the new scheduling option, so it
is not a complete scheduling rollback. Do not restore a pre-schema-3 plugin against
upgraded data: its pruner cannot honor editorial protection. Pause the affected
work during restoration. Uninstall is not rollback; explicit opt-in deletes plugin
data. No rollback changes a hosting panel or requires a panel-specific command.

See `docs/MILESTONE-4.md` for evidence, reviewed warnings and outstanding limitations.
