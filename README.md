# Editorial Scout

GPL-3.0-only editorial discovery plugin for WordPress. Milestone 3 builds on
`feature/discovery-staging-hardening` (`4abcaa0`, draft PR #3), preserving the tested
feed security model and cron hook. Sources can cover any subject.

**No posts are created or published. No AI, image, analytics or paid API is called.**
Discovered titles, links and dates are unverified third-party metadata, not researched
articles. OpenAI and Pixabay remain future integrations.

## Install or upgrade

Use single-site WordPress 6.5+, PHP 8.0+ with cURL/DOM/mbstring, and InnoDB.
Prefer supported WordPress/PHP releases. Build the runtime-only package with
`python3 tools/build-staging.py` (Python 3.9+) and follow
[STAGING-TESTING.md](STAGING-TESTING.md). No Composer or npm install is needed on the site.
For source installation include `editorial-scout.php`, `includes/`, `assets/`,
`uninstall.php`, `readme.txt` and `LICENSE`; exclude development dependencies and tests.

Before upgrading 0.2.1-rc1, back up the database and plugin directory and deactivate
the old plugin. Schema 3 adds category/archival/cooldown fields, canonical story
identities, and decisions/categories tables. Migration backfills local metadata in
batches, fetches nothing and preserves existing observations and scheduling times.
If interrupted it can be retried by an administrator; failed migration pauses work.
Downgrading requires restoring the matching DB backup: older builds do not understand
protected decisions. Never run the old pruner against an upgraded database.

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
Cron still uses `editorial_scout_check_feeds`: keep the existing server/Plesk trigger.
The worker processes five due sources every five minutes, normally hourly per source,
with failure backoff from two to 24 hours. Manual checks reschedule the source.
Low-traffic sites need a server scheduler; WP-CLI can run a due batch with
`wp cron event run editorial_scout_check_feeds`.

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
[milestone review](docs/MILESTONE-3.md). Historical hardening results remain in
`docs/HARDENING-REVIEW.md`. Feed security retains verified TLS, public-IP pinning,
no redirects, a 10-second transport deadline, 1 MiB XML and 50 items per check.
DNS resolution has a separate system timeout. Messages never include raw server
responses, proxy details, SQL errors or stack traces.

Deactivation clears scheduling and invalidates in-flight leases. Default uninstall
retains all source, category and editorial data. Only explicit
`define( 'EDITORIAL_SCOUT_DELETE_DATA', true );` in `wp-config.php` before uninstall
removes the four plugin tables/options. It never deletes WordPress posts.

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
