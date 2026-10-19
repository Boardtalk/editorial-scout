# Testing

## Automated checks

Run the README commands from the repository root. `tests/run.sh` owns only the
`editorial-scout-tests` Compose project and destroys its disposable database on
exit. Never modify it to point to an existing site's DB. Empty-root authentication
is restricted to the isolated test network, with no host DB port. Installation
passwords and test TLS keys are generated inside disposable containers and are
never printed or committed. Integration uses real WordPress, MariaDB, DOM,
SimplePie, nonces, capabilities, migrations and the HTTP transport. Fixtures supply
DNS and feed responses; no Internet feed or paid API is necessary.

The assertion runner exits nonzero on failed assertions, uncaught errors or premature
termination, including WordPress bootstrap failures. It exercises:

- Activation, repeatable schema installation, cron scheduling/deactivation.
- Unsafe URL forms, reserved/private IPs, unresolved and mixed DNS answers.
- RSS/Atom, malformed XML, wrong roots, entities, UTF-16, byte and item limits.
- Safe HTTP arguments, redirects, HTTP errors, MIME and compression restrictions.
- Real verified TLS, real redirect rejection and DNS rebinding after validation.
- Source create/edit/disable/delete through nonce-protected handlers; malformed inputs.
- Anonymous/editor denial, valid-nonce role denial, missing nonce rejection.
- Deduplication, failure backoff, disabled sources and five-source worker batches.
- Atomic lock ownership/stale recovery, 50-source and 5,000-item limits.
- 30-day pruning, queue pagination, HTML escaping and no WordPress post creation.
- Data retention on deactivation/default uninstall and explicit opt-in deletion.

PHPCS applies WordPress Core/Extra to production PHP. Narrow inline exceptions cover
native DOM property names, internal prepared table identifiers, the 300-second cron
interval and cURL pinning inside WordPress HTTP. Dedicated-table cache warnings are
excluded with a rationale in `phpcs.xml.dist`. Security/escaping/nonce/sanitization
rules are not globally suppressed. PHP lint includes the test helpers.

## Manual staging checks before release

1. Back up a disposable single-site installation and enable `WP_DEBUG_LOG`. Install
   without `vendor/`, tests or development tools. Activate and confirm no output/notices,
   one `editorial_scout_check_feeds` cron event and two prefixed plugin tables. Test
   multisite activation refusal separately.
2. As administrator, add public HTTPS RSS and Atom URLs. Edit, disable and re-enable
   sources. Try duplicate URLs, empty/long names, HTTP, loopback, credentials and
   invalid ports. Confirm invalid edits leave existing data intact. A public redirecting
   URL may be saved, but must report `http_status` when checked without following it.
3. Trigger due cron via WP-CLI or normal traffic. Confirm results and dates, repeat
   to check deduplication, and confirm Posts counts/statuses remain unchanged. A source
   not yet due is not fetched by invoking the hook manually.
4. On controlled hosts, test unreachable feeds, HTTP 500, HTML, oversized XML and DTDs.
   Confirm fixed status codes and backoff without raw responses in the UI. Restore a
   valid URL afterward. Do not disable TLS to make a fixture pass.
5. As editor/subscriber, confirm no menu or direct page/admin-post access. Remove or
   tamper with the administrator form nonce; confirm no mutation.
6. Review empty states, pagination, mobile layout, keyboard access, screen-reader labels,
   translations and external links in Firefox/Chromium. HTML in names/titles must render
   as text. PHP rendering tests do not establish browser accessibility.
7. Delete a source and confirm its history disappears. Disable another and confirm its
   retained discoveries remain but fetching stops.
8. Deactivate and check cron removal/data retention. Reactivate and check scheduling.
   Uninstall normally and verify retention. Reinstall, explicitly opt in with
   `EDITORIAL_SCOUT_DELETE_DATA`, uninstall and verify only plugin tables/options
   are removed. Never test destructive opt-in removal on valuable data.
9. Run official WordPress Plugin Check against the production ZIP. Review current
   Directory rules, license/contributor/slug metadata and minimum version claims.
   Do not upload or submit without separate authorization.

Automated checks do not establish live-site compatibility, real public feed
availability, browser accessibility, multisite support or WordPress.org approval.
Cron execution depends on hosting.
