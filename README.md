# Editorial Scout

GPL-3.0-only editorial discovery plugin for WordPress. Milestone 5 foundation builds on
`feature/scheduling-cron-health` (`16ac329`), preserving the tested
feed security model and cron hook. Sources can cover any subject.

**No posts are created or published. No AI, image, analytics or paid API is called.**
Discovered titles, links and dates are unverified third-party metadata, not researched
articles. OpenAI and Pixabay remain future integrations.

## Editorial intelligence: delivered and deferred

This candidate adds **Editorial Profiles**, three generic editable starters, an
opt-in **local** Learn My Website workflow, and explicit local relevance assessment
for selected discoveries. It is the well-tested foundation scope permitted by the
milestone request. **No live AI adapter or API-key storage ships here.** The provider
contract rejects external execution even if its caller supplies consent. There is
no spending, no transmitted excerpt, no masked-key field implying usable credentials,
and no claim that keyword matching is AI. OpenAI/other adapters, informed external
consent, secure credential lifecycle, token/spending accounting and semantic learning
remain deferred. API contract tests use doubles and zero paid calls.

Profiles have name, audience, topic/exclusion phrases, geography, tone, instructions,
native category preferences and a default category. Ten profiles and one shared
default are supported. Local matching uses title phrases only; audience/geography/
tone/instructions are stored for human configuration and future adapters. No topic
or publisher is built into the plugin. WordPress categories/tags are read natively;
source-monitoring categories remain separate. The category catalog is capped at
201 returned terms (first 200 selectable). Incomplete catalogs disable new-category
proposals and category-excluded learning rather than claiming complete validation.

Learning samples eight UTC date windows, interleaving early/late windows and category
strata; sparse archives may yield fewer than the selected ceiling of 1–32 posts.
Only public standard posts without passwords qualify. Excluded categories include
descendants within a complete catalog. Queries return IDs without totals or random
sorts, with a coarse indexed local-date range and exact UTC bounds. SQL reads at
most 4,000 content characters per selected post; stripped excerpts are capped at
600 characters. No content filters/shortcodes are rendered, no source post is edited,
and no original body/excerpt is persisted. Only proposed topic words and evidence
IDs/dates/modification times are kept in administrator-owned metadata for one hour.
Review revalidates source eligibility; changed/private/deleted evidence invalidates
the proposal. Saving the editable proposal is the explicit activation step.

Discoveries offers assessment of one to five selected stories against a profile.
Recommendations are Relevant/Uncertain/Irrelevant, with matched title phrases and a
plain explanation. Conflicting/no matches are uncertain; these are not calibrated
probabilities or verification. Profile/story/taxonomy/provider revisions deduplicate;
results expire after 30 days on use and are capped at 200. Human decisions are never
changed. Configured native categories route relevant stories, with the selected
default as fallback. A genuinely new matched topic may be proposed as a native
category, with name/slug/description/optional parent. Only explicit approval by an
administrator who can manage categories creates it. Exact/near names are screened;
no post is created or assigned. Approved native categories remain after uninstall.

See [Milestone 5 report](docs/MILESTONE-5.md) for limitations and deferred work.

## Install or upgrade

Use single-site WordPress 6.5+, PHP 8.0+ with cURL/DOM/mbstring, and InnoDB.
Prefer supported WordPress/PHP releases. Build the runtime-only package with
`python3 tools/build-staging.py` (Python 3.9+) and follow
[STAGING-TESTING.md](STAGING-TESTING.md). No Composer or npm install is needed on the site.
For source installation include `editorial-scout.php`, `includes/`, `assets/`,
`uninstall.php`, `readme.txt` and `LICENSE`; exclude development dependencies and tests.

Before upgrading, back up the database and plugin directory and deactivate the old
plugin. Upgrading 0.3.0-rc1 keeps schema 3 unchanged: source categories, discoveries,
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
