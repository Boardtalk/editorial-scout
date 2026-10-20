# Editorial Scout

GPL-3.0-only editorial discovery plugin for WordPress. Milestone 6A builds on
`feature/editorial-intelligence` (`83bba02`), preserving existing profiles, human
decisions, sources, attribution, schedules and hosting-independent cron health.

**The plugin creates no WordPress posts and never publishes.** No image service,
automatic AI work or periodic AI requests are implemented. Discovery relevance
remains local title matching. Optional OpenAI website understanding is separate.

## Editorial Profiles and Learn My Website

Profiles describe a website's subjects, readership, tone, geography and editorial
preferences. Up to ten shared administrator-reviewed profiles can use existing native
WordPress categories. Source-monitoring categories remain separate. Local keyword
analysis remains available without a key, account or external transmission.

Local learning samples up to 32 published standard posts without passwords, across
eight date windows/category strata, then fills sparse windows with two bounded date
queries (42 ID queries maximum). Excluded categories include descendants. Only 4,000
content characters are read per post; plain excerpts stop at 600 characters. No
content filters or shortcodes run. Full bodies/excerpts are not persisted. Proposals
expire after one hour and revalidate public eligibility and modification timestamps.
Evidence shows article titles with edit links. Saving a reviewed proposal explicitly
creates a new profile; an existing profile is never silently overwritten.

Optional **AI website understanding** uses the OpenAI Responses API and pinned model
`gpt-4.1-mini-2025-04-14` for semantic subjects, sections and grounded identity claims.
It is off by default. A server-configured credential, explicit enablement, exact sample
preview and affirmative consent for each analysis are all required. Individual posts
can be excluded; dates and category exclusions are under Advanced sample controls.
The preview lists titles, dates, IDs, category structure and exact bounded excerpts.
Public posts can contain personal information: review them before confirming.

No API key is accepted in HTML or saved in the database. Configure the constant
`EDITORIAL_SCOUT_OPENAI_API_KEY` from a protected server environment in wp-config.php;
see [installation, privacy, budgets and rollback](STAGING-TESTING.md). Provider connection
tests are separately consented billable requests with no website content. Codex/tests
make **no paid API requests**; real model quality/account access remain unverified.

A dated, editable pricing assumption estimates cost before consent. Conservative
per-request token reservations are enforced against configurable UTC daily/monthly
ceilings under the existing SQL lease, with no refunds after uncertain failures.
These are not a guaranteed billing ceiling; set provider-side project limits too.
The adapter uses one fixed HTTPS endpoint, no redirects, 25-second HTTP timeout,
75,000-byte request and 65,536-byte response bounds, no tools, `store:false`, strict
JSON output and independent server validation. Untrusted sample text is data, not
instructions. Prompt injection and semantic errors remain possible: claims are
unverified proposals, never executed actions. Unknown audience/geography should remain
blank. Only existing category IDs can be suggested; no AI category creation occurs.

See [Milestone 6A report](docs/MILESTONE-6A.md) for architecture, validation and limits.
Earlier milestone reports document historical releases, including the former AI deferral.

## Install or upgrade

Use single-site WordPress 6.5+, PHP 8.0+ with cURL/DOM/mbstring, and InnoDB.
Prefer supported WordPress/PHP releases. Build the runtime-only package with
`python3 tools/build-staging.py` (Python 3.9+) and follow
[STAGING-TESTING.md](STAGING-TESTING.md). No Composer or npm install is needed on the site.
For source installation include `editorial-scout.php`, `includes/`, `assets/`,
`uninstall.php`, `readme.txt` and `LICENSE`; exclude development dependencies and tests.

Before upgrading, back up the database and plugin directory and deactivate the old
plugin. Upgrading 0.5.0-rc1 keeps schema 3 unchanged: source categories, discoveries,
shortlist/dismiss decisions and attribution are retained. A restartable local option
initialization defaults existing installations to Hourly without rebasing their due
times. Failed event registration retries on the next load. Earlier schema upgrades
remain available. Restore matching file/database backups for rollback.

## Editorial dashboard

All sections and mutations require `manage_options`. Decisions are shared between
administrators, not private per-user bookmarks. The last authorized change wins.

- **Sources:** add/edit/enable/disable/remove public RSS/Atom feeds, assign categories,
  inspect last/next UTC checks and readable outcomes, and use **Check Now**.
- **Discoveries:** filter by source (including archived coverage), source category,
  inclusive UTC discovery date or editorial status. Shortlist, dismiss or return
  stories to **New**. **View coverage** preserves original links, source attribution
  and timestamps independently of story grouping.
- **Settings:** create/rename/remove up to 20 source categories and inspect fixed
  checking/retention rules. Removing a category reassigns its sources to Uncategorized.

Source names are limited to 200 characters; category names to 80. Feed URLs must
be direct public HTTPS/443 endpoints without credentials/fragments. Redirects,
authenticated feeds, proxy-dependent fetching and multisite remain unsupported.
Never enter tokens or confidential URLs. Selected feed hosts see your server IP
and WordPress user agent. No article pages, images or enclosures are fetched.

**Check Now** checks one enabled source with the same lease and feed boundary as cron.
A persisted five-minute cooldown applies across requests and retries, including
failed attempts; category/name changes and disable/re-enable do not reset it.
Cron still uses `editorial_scout_check_feeds`: keep any existing correctly configured WordPress cron runner.
The automatic worker uses the existing `editorial_scout_check_feeds` hook every five
minutes, processing at most five due sources. **Settings → Feed checking schedule**
selects Manual Only, Hourly (default), Daily, Twice Weekly (84 hours) or Weekly.
The poll interval and source interval are distinct: an external task every five
minutes does not turn weekly checks into five-minute feed requests. Failures never
retry faster than the selected interval; hourly backoff ranges from two to 24 hours.
Changing frequency rebases active sources from their last actual check; unchecked
sources stay due. Per-source checks are staggered by bounded processing, not promised
to occur at exact wall-clock times. All displayed check times are UTC.

Weekly/twice-weekly checks may miss items that a feed discards before the next check.
No interval guarantees complete coverage. These settings control **feed discovery
only**, separate from future research, draft-generation or publishing schedules.

Manual Only removes this plugin's automatic event and refuses direct automatic
callbacks. Check Now still works. **Check All Sources Now** snapshots up to 50 enabled
source IDs and processes one synchronously per explicit POST. Use Continue until
finished; processing pauses when you leave and expires one hour after starting.
Progress belongs to the initiating administrator. Disabled/removed/recently checked
sources are skipped. Interrupted attempts are recorded on resume and not silently
replayed. Nothing is enqueued to cron. Successful or failed attempts retain the same
five-minute per-source cooldown. No batch creates or publishes posts.

### Hosting-agnostic cron health

Ordinary traffic-triggered WP-Cron and any correctly configured external WordPress
cron runner are supported. There is no hosting-panel API, detection, installation
path or scheduler dependency. Hosting tasks on cPanel, Plesk, DirectAdmin, managed
WordPress, server cron or systemd timers can all work by servicing WordPress cron.
For example, a website administrator may run `wp cron event run --due-now` from their
WordPress installation every five minutes, using the hosting provider's supported
PHP/user setup. Scheduling tasks is outside this plugin; ask the host for guidance.
Do not disable traffic-triggered cron until a replacement has been verified.

Cron Health Monitor reports actual automatic callback starts/completions, not
whether a hosting panel exists. It allows a one-hour first-run/traffic grace period,
checks overdue source work, distinguishes fresh-run backlog and interrupted runs,
and recognizes Manual Only/no enabled sources. `DISABLE_WP_CRON` alone is not an
error. Manual work never manufactures an automatic heartbeat. Infrequent sources
are not overdue merely because no recent callback was seen. The sanitized diagnostic
report includes fixed status, counts, UTC event/completion times and software
versions; excludes URLs, hostnames, paths, IP addresses, accounts and credentials.
Copying is local; nothing is sent to a support service.

## Retention and duplicate coverage

Original per-source observations remain separate. The dashboard groups identical
article URLs after normalizing host/default port and dropping only `utm_*`, `fbclid`,
`gclid`, `mc_cid` and `mc_eid` query keys. Meaningful query parameters, their order,
paths and trailing slashes remain distinct. Titles are never used to assert that two
stories are the same. Coverage links show the original URLs; this is not semantic
or factual duplicate detection. Tracking-parameter normalization can still group
variants which a particular site treats differently; review original coverage.

Routine workers prune **unreviewed** observations to 30 days and 5,000 records.
Shortlisted/dismissed stories and their evidence are protected until explicitly
returned to New. Limits are 1,000 decided stories and 10,000 protected observations.
At a limit the operation fails with an explanation; existing decisions/evidence are
not silently evicted. Return unneeded choices to New to release capacity; pruning
then applies. Data can outlive the age bound while cron/plugin is inactive.

Removing a source or changing its feed URL archives its old source identity when
protected evidence exists. The protected observations remain visible; unreviewed
history is removed. A URL change creates a new active source identity in that case.
Empty archives are removed only after protection is released and pruning removes
all their observations. There may be 50 active sources; archives are evidence, not
scheduled sources. The source dropdown is bounded; coverage links can select older
archived records not initially listed.

## Security, removal and development

See [security design](docs/SECURITY.md), [testing](docs/TESTING.md), and the
[milestone review](docs/MILESTONE-5.md). Historical hardening results remain in
`docs/HARDENING-REVIEW.md`. Feed security retains verified TLS, public-IP pinning,
no redirects, a 10-second transport deadline, 1 MiB XML and 50 items per check.
DNS resolution has a separate system timeout. Messages never include raw server
responses, proxy details, SQL errors or stack traces.

Deactivation clears scheduling and invalidates in-flight leases. Default uninstall
retains all source, category and editorial data. Only explicit
`define( 'EDITORIAL_SCOUT_DELETE_DATA', true );` in `wp-config.php` before uninstall
removes the four plugin tables/options and plugin-specific manual progress metadata. It never deletes WordPress posts.

```sh
composer install --no-interaction --prefer-dist
composer lint
composer standards
python3 tools/build-staging.py
SCOUT_WP_IMAGE=wordpress:php8.3-apache ./tests/run.sh
```

Tests use disposable internal Docker/Compose services, deterministic DNS and trusted
local TLS fixtures. They never use a live DB or paid service. The fixture subnet is
`93.184.216.0/24`; avoid hosts already routing it and concurrent copies of the same
Compose project. `SCOUT_PLUGIN_ROOT` can select the extracted runtime ZIP (directories
0755, files 0644); helper files mount separately at `/opt/scout-tests`.

For Chromium/axe review: `npm --prefix tests/browser ci`, install Chromium through
`cd tests/browser && npx playwright install --with-deps chromium` or set
`SCOUT_CHROMIUM`. Run `SCOUT_KEEP_TESTS=1 ./tests/run.sh`, then
`node tests/browser/browser.cjs`; finish with
`docker compose -p editorial-scout-tests -f tests/compose.yaml down -v --remove-orphans`.
Browser setup resets only disposable fixture data, keeps cookies in memory and
uses a test-only CA binding inside that container. Never point it at an existing site.

GitHub Actions checks syntax, WordPress standards, extracted-package integrations,
packaging reproducibility and browser accessibility. Plugin Check and manual
host/screen-reader/Directory policy review remain release gates. The staging package
is not WordPress.org approval or a security certification.
