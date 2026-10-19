# Editorial Scout

GPL-3.0-only WordPress plugin for source-led editorial discovery. Built on
`feature/plugin-foundation` and draft PR #1. This is a development release, not a
WordPress.org Directory submission.

Administrators with `manage_options` can add, edit, enable, disable and delete
public RSS/Atom sources under **Editorial Scout**. Scheduled checks collect article
titles, links and dates in a read-only queue. Sources can cover any topic.
**This release never creates or publishes posts.** It makes no AI, image, analytics
or telemetry calls. OpenAI and Pixabay are future integrations. Discovery metadata
is unverified third-party information, not researched articles or original drafts.

## Installation and configuration

1. Use single-site WordPress 6.5+ and PHP 8.0+ with cURL, DOM and mbstring. Prefer a
   PHP version still receiving security updates. Multisite is unsupported.
2. Copy `editorial-scout.php`, `includes/`, `uninstall.php`, `readme.txt` and `LICENSE`
   into `wp-content/plugins/editorial-scout/`. Production requires no Composer vendor
   directory. Exclude development/test files from any production release ZIP.
3. Activate **Editorial Scout** and open its administrator menu.
4. Add a descriptive name and the final public **HTTPS** feed URL on port 443.
   Do not enter passwords, API tokens or confidential query parameters.
5. Leave **Enabled** checked. Disabling stops future requests but retains history.
   Deleting a source or changing its URL deletes that source's discovery history.
6. Review the **Discovery queue**. Linked pages are external and unverified.

No credentials, subscriptions, API keys or paid services are needed. Fetching sends
the WordPress user agent and server IP to the selected source. There is no built-in
source list; no feeds are requested until an administrator configures them.

## Scheduling and limits

A WP-Cron worker runs every five minutes and checks at most five due sources.
Each source is checked approximately hourly; failures back off from two hours to
24 hours. Low-traffic sites need a real scheduler to trigger WP-Cron regularly.
On a development site with WP-CLI, `wp cron event run editorial_scout_check_feeds`
runs one due batch; it does not force feeds that are not due.

Limits: 50 sources, 10-second HTTP timeout per feed (DNS lookup time is separate),
1 MiB XML, 50 extracted items per check. Each worker run prunes discoveries older
than 30 days and retains at most the newest 5,000 inserted items. Data may persist
longer if cron stops or the plugin is inactive. This is not archival storage.

A unique database index deduplicates normalized article URLs **within each source**.
Host case and explicit port 443 are normalized; path, query and trailing-slash
variants remain distinct. An article in two sources appears twice. Expired items
still present in a feed may be rediscovered. Feed GUIDs are not trusted identifiers.
Indexed custom tables avoid posts and autoloaded discovery data.

## Security and data removal

See [security design](docs/SECURITY.md) and [testing](docs/TESTING.md). Redirects,
authenticated feeds, non-HTTPS URLs and proxy-based feed fetching are unsupported.
Only metadata is retained; article pages, images and enclosures are never fetched.
The manager shows fixed status identifiers instead of raw remote errors:
`ok`, `invalid_url`, `unsafe_host`, `http_status`, `content_type`, `invalid_xml`,
`invalid_feed`, `fetch_failed`, `proxy_unsupported`, `missing_curl`, `missing_dom`,
`storage_failed`. `ok` indicates parsing completed, not factual verification.

Deactivation unschedules background work and retains data. Uninstall retains data
by default too. To intentionally remove plugin tables and options on uninstall,
set `define( 'EDITORIAL_SCOUT_DELETE_DATA', true );` in `wp-config.php` **before**
deleting the plugin. Back up first. This never deletes WordPress posts.

## Development

```sh
composer install --no-interaction --prefer-dist
composer lint
composer standards
./tests/run.sh
SCOUT_WP_IMAGE=wordpress:6.5-php8.1-apache ./tests/run.sh
```

Tests need Docker Engine and Compose v2. They create isolated disposable WordPress
and MariaDB instances, deterministic DNS and a trusted test TLS certificate, then
remove their containers/networks on exit. No live database, external feed or paid
request is used. The test-only `93.184.216.0/24` bridge is Docker-internal, without
Internet egress; do not run on a host already routing that subnet or run concurrent
copies with the same Compose project name. See the testing guide for details.

GitHub Actions runs syntax, WordPress Core/Extra standards and integration tests.
`composer.lock` pins development tools. No Node build or generated assets are needed.

Future work includes editorial review, evidence verification, human-approved drafting
and opt-in AI/image integrations. Official WordPress Plugin Check, staging browser
and accessibility review, final contributor/slug metadata and current Directory
policy review remain release gates. CI is not Directory approval.
