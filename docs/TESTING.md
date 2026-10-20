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
- Atomic lock ownership/stale recovery, eight simultaneous independent stale-cache contenders, negative/positive cache poisoning, lease loss/renewal/deactivation, 50-source and 5,000-item limits.
- 30-day pruning, queue pagination, HTML escaping and no WordPress post creation.
- Data retention on deactivation/default uninstall and explicit opt-in deletion.

PHPCS applies WordPress Core/Extra to production PHP. Narrow inline exceptions cover
native DOM property names, the 300-second cron
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


The hardening suite additionally tests interrupted schema/index migrations, transaction rollback
when source/history writes fail, out-of-order discovery timestamps, unexpected transport exceptions,
real chunked/oversized/compressed responses, dual-stack selection and real timeout behavior.
`tests/proxy.php` runs a separate WordPress process with a configured HTTP proxy and expects
fail-closed behavior. Browser tooling has its own npm lockfile; it is not a runtime dependency.

The optional `SCOUT_KEEP_TESTS=1` leaves disposable services running for browser/Plugin Check
review; explicitly run Compose down afterward. The default still cleans up on exit.
The suite can mount `SCOUT_PLUGIN_ROOT` outside the checkout to validate an extracted ZIP;
test helper files remain separate from the runtime directory. Avoid concurrent copies with
the same Compose project or fixture subnet.

## Milestone 3 coverage

The original hardening/security assertions remain. Additional fixtures reconstruct
the exact prior two-table schema-2 shape, seed 406 observations, interrupt a canonical
backfill, then verify retry, three bounded CASE-update batches, preserved source IDs,
metadata and due times. Workflow tests cover categories, inclusive date/source/status
filters, tracking-aware cross-source grouping, original attribution, protected age/count
retention, source archival/versioning and explicit return to New.

Administrator POSTs are exercised in independent WordPress processes for Check Now,
categories and decisions, including anonymous/editor/invalid nonce/GET denial. Manual
checks use both response mocks and real pinned TLS browser requests. Persisted cooldown,
lock exclusion, disabled-source rejection, quota failure, 10,000-observation preservation,
failed decision probes and default/opt-in uninstall are exercised. Queue query count
is bounded; no row-by-row attribution lookup or pagination COUNT is required.

The browser suite scans each of three sections at 375/768/1280 pixels with scoped axe
WCAG A/AA checks, then performs category creation, source editing/assignment, real Check
Now/cooldown, disabling, shortlisting, filtering and coverage navigation. Tests enable
WordPress debugging on disposable services. PHP 8.3 is a full integration/browser
matrix target in CI alongside legacy WordPress/PHP and PHP 8.2 integration targets.

## Milestone 4

The existing harness additionally executes tests/scheduling.php and isolated
cron-spawn.php / cron-dispatch.php processes. The former intercepts the normal
traffic-triggered nonblocking cron loopback contract with DISABLE_WP_CRON=false;
the latter runs WordPress's real wp-cron.php dispatcher and a verified TLS feed
fixture with external cron enabled. Neither assumes any hosting panel exists.

Assertions cover every mode/interval, five-minute polling of less frequent sources,
manual-only forced-callback rejection, individual checks in every mode, persistent
cooldowns, shared locks, transaction rollback, cache refresh, configuration upgrade,
real heartbeat vs manual work, grace/overdue/backlog/missing/interrupted states,
bounded manual snapshots, explicit continuation, interrupted/expired/completed jobs,
independent-process permission/nonce/method/token checks, schedule API failure/retry,
weekly failure backoff and lifecycle retention of unrelated WordPress events.

Chromium tests add every schedule selector option, permanent coverage warnings,
manual-only health, sanitized diagnostic/copy fallback, synchronous Check All cooldown
skip and explicit continuation through a second real TLS source. All screens retain
axe WCAG 2/2.1 A/AA checks at three widths. These checks do not replace screen-reader
or hosting-specific cron deployment testing. See MILESTONE-4.md for executed results.

## Milestone 5 foundation

The integration harness adds intelligence.php and isolated intelligence-endpoint.php
requests. Fixtures cover native category/tag access, profile CRUD/default/count and
field bounds, public archive samples, excluded descendants, private/password/draft/
page canaries, immutable source posts, small persisted proposals, source eligibility
revalidation, review isolation, strict input shapes, profile/taxonomy staleness,
expiry/deduplication, justified native category approval and near-duplicate screening.
Every new mutation is checked against anonymous/editor/invalid-nonce/GET requests.
Default and opt-in uninstall are tested while retaining approved native categories.

Provider doubles test structured output, literal evidence, safe exception handling,
request/response bounds and externally blocked execution with/without supplied
consent. These are provider-contract mocks, not OpenAI transport tests: no external
adapter, key lifecycle, spending controls or semantic model is included. Zero external
calls are asserted for local learning/assessment. The existing security/cron/upgrade
suite remains enabled.

Chromium adds the fourth admin section to all three viewport/axe scans, editable
starters and native-category profile creation, local assessment, inactive learning
proposal/evidence, explicit review/save and AI availability disclosures. Public post
fixtures exist only in the internal disposable WordPress installation. Nothing is
published to staging/production. See MILESTONE-5.md for final execution results.

## Milestone 6A

`tests/openai.php` uses fake credentials and `pre_http_request` fixtures only. No real
key/account or paid request is required. It covers default-off/missing credential,
configuration validation, public-material bounds and privacy, exact preview digest,
subset selection, per-analysis consent/replay/expiry/user isolation, semantic claims
and malformed output, existing-category validation, immutable editorial/scheduling
state, durable usage-before-HTTP, SQL concurrency lease, cooldown, UTC period rollover,
budget rejection, sanitized exceptions/HTTP errors/oversized/non-JSON/incomplete
responses, no-content connection testing, disconnect and opt-in uninstall. All five
new mutation modes also run anonymous/editor/invalid-nonce/GET independent processes.
The full pre-existing feed/SSRF/DNS/cron/schema/editorial suite remains enabled.

Browser setup creates a fake provider MU plugin **inside the disposable test site**.
Its dummy key and response functions never enter the runtime ZIP. The browser checks
real admin POST/redirect/nonce flows for preview/configure/consent/review/save/disconnect,
article title evidence, no key in HTML, and expanded preview/provider settings at
375/768/1280 with scoped axe WCAG 2/2.1 A/AA. External browser requests are blocked;
the Docker test networks cannot reach the Internet. Fixture claims are not a live
semantic accuracy test. See MILESTONE-6A.md for final counts and checker warnings.

## Milestone 6B

newsroom.php and newsroom-endpoint.php add schema-3 interrupted/restarted migration,
historical choice/evidence preservation, future schema rejection, complete-queue
priority/pagination, inclusion/exclusion/quoted LIKE data, source echo counts, bounded
query counts, no-choice preservation, commissioned/held/dismissed transitions, stable
commission IDs, revision stale/ABA checks, valid+stale mixed outcomes, rollback on
commission and receipt failures, lock recovery after transactional renewal rollback,
nontransactional native-engine rejection, token expiry, independent-process permission/
nonce/method/held-lock tests and simultaneous repeated POSTs. Retention and opt-in
uninstall include commissions/manifests/receipts. All existing tests remain enabled.

Chromium exercises the default Newsroom, inline expansion, batch receipts/PRG anchors,
commission/hold/dismiss recovery and category checkbox counts, alongside all prior
source/cron/AI mock workflows. Scoped axe and overflow checks include all five tabs
at 375/768/1280 and expanded provider/privacy controls. Native navigation transitions
finish before viewport changes; page errors remain failures. All AI transport fixtures
use dummy credentials only; the disposable Docker networks have no Internet access.
Manual screen-reader, admin-theme and persistent-cache deployment checks remain needed.
See MILESTONE-6B.md for executed counts, CI and official Plugin Check findings.
