# Source Discovery hardening review

Audit date: 19 October 2026. Base: `c59f67f`, `feature/source-discovery`, draft PR #2.
Work branch: `feature/discovery-staging-hardening`. The implementation and foundation
history are preserved. This is a staging candidate, not security certification or
WordPress.org approval. No live site was accessed or modified.

## Changes and findings

| Area | Finding and resulting change |
| --- | --- |
| Feed destinations | Retain HTTPS/443, public DNS validation, WordPress safe HTTP, address pinning and redirect rejection. Prefer a validated IPv4 address consistently on dual-stack hosts; allow ordinary public `2001` IPv6 while excluding special-use/documentation/translation ranges. |
| Transport | Force the scoped WordPress Requests cURL transport, TLS peer/hostname checks, HTTPS-only protocol and explicit ten-second connection/total transport deadlines. Disable implicit proxies; configured WordPress proxies which would handle a feed fail closed. Hooks are removed on success and exceptions. |
| Network regressions | Real trusted/untrusted TLS, rebinding after validation, unreachable public AAAA alongside usable IPv4, redirects to public/private targets, chunked/oversized bodies, compression, MIME rejection and a twelve-second fixture demonstrating the ten-second deadline. No redirect destination is contacted. |
| Cron ownership | Cached `get_option` and cache-dependent `add_option` no longer decide ownership. SQL insert-if-absent and exact binary compare-and-swap claim/renew the lease. Negative/per-option/autoload caches are invalidated for compatibility reads. Eight independent stale-cache contenders prove only one simultaneous owner. |
| Failure recovery | Workers renew before/after fetches and abandon writes if ownership is lost. A transport exception records a fixed status/backoff without starving later work. Deactivation invalidates an in-flight lease. An old owner cannot release or renew its successor's lease. |
| Administrator forms | Retain capability-first POST and nonce validation, strict original-URL validation, scalar handling and contextual output escaping. Remove duplicate nonce HTML IDs, name repeated row actions, and scope the stylesheet to this page. No new public or unauthenticated endpoints. |
| SQL/storage | Use WordPress `%i` identifier placeholders (supported by the WordPress 6.5 minimum). Keep dedicated storage uncached for correctness. Source URL changes/history deletion and source deletion are transactional; failed operations roll back and report failure. Avoid logging expected duplicate-source errors. Narrow article insertion to a no-op on duplicate-key conflict instead of suppressing unrelated errors with INSERT IGNORE. |
| Migrations | Schema 2 checks required column types, indexes, uniqueness and InnoDB after dbDelta. Missing columns/indexes are repaired; interrupted migration cannot mark readiness. Failed upgrades pause plugin hooks/work until an administrator retries successfully. Schema changes are not repeatedly attempted on public traffic. |
| Retention | Prune using discovery time plus ID, matching queue order and retaining newer discoveries even when backfilled rows have higher insertion IDs. Existing size/age/source/item limits remain. |
| Data removal | Default uninstall still retains data. Explicit single-site opt-in removes only plugin tables/options; deactivation clears scheduling and ownership. Multisite remains unsupported. |
| Accessibility | Named keyboard-focusable scroll regions, responsive overflow handling, distinct action names and unique nonce markup. Actual browser edit/save/disable requests work against the extracted runtime ZIP. |
| Metadata | Version/stable tag `0.2.1-rc1`, matching `GPL-3.0-only` identifiers, prefixed globals, and removal of the development Update URI which Plugin Check rejects for Directory plugins. |
| Packaging | An explicit nine-file allowlist, fixed timestamps/order/modes, stored ZIP entries and SHA-256 sidecar make the staging ZIP byte-reproducible without bundling development dependencies or credentials. |

## Executed validation

- **220 integration assertions passed** against the extracted runtime package on
  WordPress **6.5.5 / PHP 8.1.29** and WordPress **7.1.3 / PHP 8.2.34**, using MariaDB 11.
  This includes the legacy 169 assertions plus new network/concurrency/migration/
  transactional-storage/retention/deactivation cases. No assertions were disabled.
- PHP 8.2 syntax checks on all first-party PHP and test helpers; WordPress Core/Extra
  PHPCS; JavaScript and shell syntax checks; `git diff --check` passed.
- **18 Chromium browser checks passed** against the extracted ZIP. Widths 375,
  768 and 1280 pixels have no page-wide overflow. Both table regions are named and
  focusable. Scoped axe scans using WCAG 2.0/2.1 A/AA tags report zero violations
  inside the plugin panel. Untrusted title rendering and real edit/save/disable
  requests pass, with no browser JavaScript errors.
- **Official Plugin Check 2.1.0** from WordPress/plugin-check commit
  `e8186b80c43c32b5841cabfdee8ebc21594bf89e` ran using its locked production dependencies,
  WP-CLI and `--require=.../plugin-check/cli.php` against the exact extracted runtime
  directory. Its default stable checks returned **0 errors and 56 warnings**:
  28 direct-database, 27 no-caching and one uninstall schema-change warning.
  These are reviewed deliberate operations, not suppressed scan results: plugin-owned
  tables/atomic leases require direct SQL; correctness requires fresh reads; explicit
  opt-in uninstall intentionally drops only plugin tables. Identifier/value escaping
  findings were fixed with `%i`/prepared values. Initial updater/license/stable-tag
  errors and input/global-prefix warnings were corrected.
- Two independent ZIP builds compare byte-for-byte. All nine archive entries have
  the `editorial-scout/` prefix, mode 0644 and fixed 1980 timestamps. CRCs and every
  archived byte match the runtime source files. The packaged plugin, not a checkout
  containing test helpers, was used for final integration and browser validation.

Raw local reports are retained outside the ZIP in ignored `artifacts/`:
`plugin-check-report.txt`, `integration-php81.log`, `integration-php82.log`, and
`browser-review.log`. The build script generates the ZIP and checksum sidecar;
those reports are additional review artifacts, never runtime files.

At audit start GitHub's public Actions page reported both source-discovery baseline
runs successful: [push run](https://github.com/Boardtalk/editorial-scout/actions/runs/39326137581)
and PR #2's run. This branch extends CI to test the extracted package, verify
reproducibility, upload a staging artifact and run Chromium/axe. Branch CI status
must be checked after pushing; local checks do not establish hosted CI success.

## Reproduction

```sh
composer install --no-interaction --prefer-dist
composer lint
composer standards
python3 tools/build-staging.py
python3 tools/build-staging.py --output-dir /tmp/scout-repro
cmp artifacts/editorial-scout-0.2.1-rc1.zip /tmp/scout-repro/editorial-scout-0.2.1-rc1.zip
./tests/run.sh
SCOUT_WP_IMAGE=wordpress:6.5-php8.1-apache ./tests/run.sh
```

For final-package validation, extract the ZIP, set directory/file modes to 0755/0644
and set `SCOUT_PLUGIN_ROOT` to that absolute runtime directory. Tests mount their
helpers separately at `/opt/scout-tests`. Browser instructions are in README.
Plugin Check can be reproduced on disposable WordPress using its upstream locked
checkout and WP-CLI:

```sh
wp --require=/path/to/plugin-check/cli.php plugin check /path/to/editorial-scout \
  --slug=editorial-scout --format=json
```

No AI/paid-service option was used. The upstream plugin ZIP download was blocked by
network policy, so the official Git checkout was used without disabling TLS or
changing dependency checksums. In this cloud environment the Composer container
needed the host's trusted CA bundle for both Composer and Git HTTPS.

## Remaining limitations and release gates

- PHP 8.0 remains the declared compatibility minimum; its local full-runtime image
  could not be installed because Docker exhausted disk space. Local PHP 8.0/8.4
  runtime validation is unperformed. Hosted CI checks syntax on 8.0, 8.2 and 8.4;
  full integration covers the two configurations above. Prefer a supported PHP
  release for staging/production. No claim covers every PHP/WordPress combination.
- System DNS resolution time is separate from the ten-second cURL deadline. There
  is no IP failover, IPv6-only host guarantee, redirect following, conditional GET,
  proxy-dependent networking, authenticated feed support or multisite support.
  Trusted plugins/HTTP hooks and host administrators remain inside the trust boundary.
- Redis/Memcached backends were not installed; tests inject stale positive/negative
  option caches in independent processes while ownership is enforced in MariaDB.
  Malformed leases fail closed; deactivation/reactivation clears an unusable lease.
- Browser coverage is Chromium and the plugin panel only. Screen-reader review,
  Firefox/Safari, complete WordPress administration accessibility, hosting-specific
  proxy/DNS/cron behavior and real public feed availability need staging review.
- Tests used isolated disposable services and generated keys/cookies only. They do
  not establish live-site compatibility, security certification or Directory approval.
  Plugin Check warnings still require maintainer acceptance and current Directory
  policy/contributor/slug review before a separately authorized submission.
- No branch was merged, no plugin deployed, no paid AI/image request made and no
  external content published. The staging ZIP itself does not install anything.

## Changed files

Runtime: `editorial-scout.php`, `includes/class-editorial-scout-feed.php`,
`includes/class-editorial-scout-runner.php`, `includes/class-editorial-scout-store.php`,
`includes/class-editorial-scout-admin.php`, `uninstall.php`, `readme.txt`,
new `assets/admin.css`.

Development: `.github/workflows/ci.yml`, `.gitignore`, `tests/compose.yaml`,
`tests/run.sh`, `tests/dns.php`, `tests/tls.php`, `tests/integration.php`,
new `tests/hardening.php`, `tests/lock-contender.php`, `tests/proxy.php`,
`tests/browser-setup.php`, `tests/browser/browser.cjs`,
`tests/browser/package.json`, `tests/browser/package-lock.json`, and
`tools/build-staging.py`.

Documentation: `README.md`, `docs/SECURITY.md`, `docs/TESTING.md`,
`STAGING-TESTING.md`, and this review. `LICENSE`, Composer dependency declarations
and Composer lockfile are preserved. No test dependency is shipped in the ZIP.
