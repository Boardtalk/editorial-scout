# Security design

## Authorization and storage

`manage_options` is required for every source mutation and queue view. Customized
WordPress roles follow capability assignment, not a hard-coded role name. Mutations
require POST, a capability check before processing, and a verified WordPress nonce.
Nonces protect against CSRF, not unauthorized roles. Scalar inputs are validated,
names sanitized, and UI output escaped by context. External links use HTTPS and
`noopener noreferrer`. Read-only pagination/edit navigation has no side effects.

An atomic option-row lock serializes scheduled workers and source mutations. Ownership is read directly from SQL and claimed with INSERT IGNORE / compare-and-swap; add_option and cached get_option reads do not decide ownership.
Expired locks are reclaimed with an exact-value compare-and-swap; only the owning token can
release a lock. A crash may delay edits/checks for up to 15 minutes. Workers renew before and after fetching and stop without writes if ownership is lost. Deactivation invalidates the lease so an in-flight fetch stops at its next ownership check. Plugin-specific, negative-option and autoload caches are invalidated after lock mutations. Cache backends do not decide SQL ownership. Custom tables
use prepared values, internal identifiers and unique indexes. They are deliberately
uncached so scheduling and lock reads are current. No post-writing API is used.

## SSRF and TLS

Feeds use `wp_safe_remote_get`, preserving WordPress unsafe-URL checks and TLS
verification. URLs must use HTTPS on 443, a public DNS hostname and no userinfo,
fragments, whitespace, control characters or backslashes. IP literals, ambiguous
numeric hosts, local names, private/reserved IPv4, mapped IPv6 and transition ranges
are rejected. Every returned A/AAAA address must pass validation; unresolved or
mixed public/private hostnames fail closed.

The WordPress Requests cURL transport is selected for scoped feed requests. A
validated public address is pinned with `CURLOPT_RESOLVE`, retaining the hostname
for SNI and certificate checks. This prevents a second DNS lookup from rebinding
the connection to an internal address. Request-specific hooks are removed in
`finally`. This configures WordPress HTTP; there is no separate cURL HTTP client.

All redirects, including same-host ones, are rejected. Enter the final feed URL.
No HTTP cookies/credentials are sent. No article page, image, enclosure, stylesheet,
linked document or HTML feed-autodiscovery URL is fetched.

Direct cURL connectivity is required. Configured WordPress proxies fail closed;
cURL's implicit proxy settings are disabled for these requests because remote
proxy DNS resolution would defeat local address pinning. Networks requiring a
proxy are unsupported. Never disable TLS checks. Trusted WordPress plugins/filters
and server administrators can alter HTTP behavior; malicious code already running
inside WordPress is outside this threat model. Host egress filtering remains useful.

## XML and untrusted metadata

Only HTTP 200 and allowlisted XML MIME types are accepted. Compression is neither
requested nor decoded; compressed responses are rejected. Response reading is capped
at 1 MiB plus one byte so oversized XML can be detected without trusting Content-Length.
The HTTP timeout is ten seconds; system DNS resolution has its own timeout.

DOM preflight uses `LIBXML_NONET`, default depth limits and no DTD/entity expansion
flags. DTDs, entities, NUL bytes, malformed XML and non-feed roots are rejected.
UTF-16/UTF-32 feeds are deliberately unsupported. WordPress's bundled SimplePie
parses only the validated in-memory body, with cache and autodiscovery disabled.
Only the first 50 items are inspected, without date sorting. Titles become plain
text (500 characters maximum); descriptions, article bodies and images are discarded.
HTTPS article links receive syntax validation but are never resolved or fetched.
Clicking an external link is the reviewer's own navigation. Publication dates are
untrusted, normalized to UTC, and omitted if invalid or more than one day in the future.

## Data lifecycle and restrictions

Stored data: source name/URL, enabled state, next/last check, bounded status/failure
count, and article source ID, URL hash, title, URL and publication/discovery times.
No profiles, secrets, prompts or article bodies are needed. Never enter authenticated
feeds, credentials or confidential URLs. All retained metadata stays in the site DB.

Workers prune history; retention is not a continuously running service. Deactivation
and default uninstall retain data. Explicit `EDITORIAL_SCOUT_DELETE_DATA === true`
on single-site uninstall removes only plugin tables/options. Multisite activation
and network cleanup are unsupported; activation on multisite is refused.

DNS aliases without usable A records, IPv6-only hosts rejected by WordPress's own
validator, can reject otherwise
public feeds. Public ordinary 2001 IPv6 addresses are allowed; only the special 2001::/23 block and documentation/transition ranges are denied. IPv4 is preferred deterministically on dual-stack feeds, consistent with WordPress validation. There is no IP failover, redirect following, conditional GET,
authenticated access, HTTP article support or cross-source deduplication. Locking
and unique indexes do not provide a distributed job system or durable archive.

Report potential vulnerabilities through the repository's private reporting feature
if enabled, or coordinate privately with maintainers. Do not post exploit details
or confidential site data publicly. This release claims no reporting email or SLA.

Schema 2 verifies required column types, primary/unique/scheduling indexes and InnoDB after dbDelta, and does not advance the version on incomplete migration. URL changes and source/history deletion use transactions; failed writes roll back. Retention uses discovery timestamp plus ID ordering, matching queue ordering even for backfilled timestamps. Multisite cleanup is still unsupported.

## Milestone 3 workflow boundaries

Schema 3 adds source category/archive/manual-reservation fields, canonical observation
hashes and indexed categories/decisions tables. Migration holds the SQL lease and
backfills in 200-row CASE updates, with renewals and readiness checks; marker advancement
follows successful completion. A future schema version is not automatically downgraded.
Source archive/deletion/versioning is transactional. A failed decision lookup cannot
cause protected evidence to be treated as ordinary history. Rollback requires restoring
the prior database backup as well as files; prior plugin versions cannot honor decisions.

Check Now is an authenticated administrator POST with a verified source nonce. It
uses the same lease, validated/pinned HTTP and ingestion path as cron. A UTC cooldown
reservation is persisted before fetching, and recent cron attempts also enforce the
five-minute manual interval. It applies to that configured source identity and is not
intended to restrain an administrator who deliberately creates new source identities.
Disabled/archived/missing sources are rejected. Name/category edits preserve the
reservation, prior result and scheduling time. No client-side timer is trusted.

Story decisions and category edits require separate verified nonce scopes plus
`manage_options`. Shared choices use last-authorized-write semantics, not per-user
private bookmarks. Flash notices are short-lived per-user transients containing only
fixed codes/counts; URL query strings cannot forge the displayed outcome. Filters
are read-only, scalar-validated and prepared; dates must be exact valid ISO days.
Statuses/operators/ordering are fixed allowlists. No raw server/DB exception is shown.

Decisions protect original observations from age/count pruning and source removal.
Explicit limits (1,000 choices; 10,000 protected observations) refuse further growth,
never silently evict evidence. Returning a story to New opts back into pruning.
Canonical URL grouping drops only documented tracking keys and retains every observed
source URL; it neither verifies factual claims nor infers semantic equivalence from
headlines. Archived records preserve attribution and are never scheduled. Default
uninstall retains both new tables; opt-in uninstall removes all plugin tables only.

PHP 8.3 regression testing showed that built-in reserved-IP filtering differed for
`2001:db8::/32`. The feed boundary now rejects this IPv6 documentation block explicitly,
independent of runtime defaults, while retaining all prior public-address restrictions.

## Milestone 4: feed scheduling and execution evidence

Scheduling is implemented exclusively through WordPress cron APIs and the existing
feed hook. There are no hosting-panel APIs, detection, infrastructure credentials or
runtime installation paths. A five-minute bounded worker applies longer source due
intervals; Manual Only removes the feed hook and guards even forced callbacks.
Only plugin-owned events are removed. WordPress's global cron option remains subject
to core's concurrency behavior; failed registration preserves intent and retries.

Schedule configuration uses the existing SQL lease. Due-time rebasing and the
non-autoloaded option write share an InnoDB transaction (including wp_options).
Cache publication occurs after commit, rollback invalidates option caches, and
workers refresh configuration after acquiring ownership. Schedule setup runs at init
so translations are not requested during early plugin loading. Schema 3 is unchanged.

Check All uses the same feed validation, TLS pinning, cooldown and SQL lease as
individual Check Now. One source is checked per administrator POST; up to 50 enabled
IDs are snapshotted in administrator-owned user metadata with a one-hour expiration.
The cursor is persisted before HTTP so a terminated request cannot silently replay.
Continuation requires manage_options, its dedicated nonce and the matching job token.
An interrupted cursor is reported as failed on resume. No manual continuation uses
WP-Cron, an image service, AI or post APIs. Pruning preserves existing editorial choices.

The health monitor reads only bounded source aggregates and plugin execution state.
A manual request cannot manufacture an automatic heartbeat. First-run and overdue
traffic tolerance is one hour; callbacks can report interruption after the lease
window. DISABLE_WP_CRON is informational because any correctly configured WordPress
cron runner is supported. The monitor does not identify hosting panels or diagnose
infrastructure internals. The copyable report allowlists fixed status/count/version/
UTC timestamp fields. It excludes source URLs, account names, filesystem paths,
hostnames, IP addresses, raw errors and credentials. Clipboard copying is local,
with selectable text as a fallback; no report is transmitted by the plugin.

## Milestone 5 local foundation

Profiles, local learning, relevance and explicit native-category approval share the
existing administrator capability/POST/nonce gate and SQL lease. Native category
creation also requires manage_categories. Profile/result option caches and the
current user's metadata cache refresh under ownership. No new action is attached
to feed fetching, cron or post publication. Source transport and store schema 3
are unchanged. New options are non-autoloaded and bounded (ten profiles, 200 results).

Website learning reads only publish-status standard posts with empty passwords,
using bounded ID queries and exact UTC dates plus an indexed coarse local-date
range. Up to 32 SQL snippets (4,000 characters each) become at most 600-character
plain excerpts in memory; no full post objects, content rendering or original bodies
are persisted. Excluded category descendants are expanded only in a complete bounded
catalog. No source posts are modified. Administrator-owned one-hour proposal metadata
contains derived words, evidence IDs/dates/modification timestamps and an opaque review
token. Review checks source eligibility again and never auto-activates the proposal.
Expired drafts are ignored/removed on access; there is at most one per participant.

Recommendations use a fixed local phrase matcher, not an LLM. Article text cannot
invoke tools, change editorial status or create posts. External execution is blocked
at the provider boundary regardless of supplied consent. There is no live adapter,
credential field/storage or paid request path. Structured-output tests reject unknown
recommendations, invented evidence, oversized data and raw provider failures. This
is not a claim of adversarial prompt-injection safety for future external models;
those need independent consent, credential, transport and billing implementation.

Native category proposals are tied to current relevant evidence, exclude existing/
near-matching names in the bounded catalog and require a separate explicit approval.
Category names/slugs/descriptions/parents are validated through WordPress APIs. Human
shortlist/dismiss decisions are never modified. Exact duplicates are checked again
on approval; near-name similarity is heuristic and external WordPress term edits can
race with plugin review. No parallel WordPress post taxonomy is created. Explicit
opt-in uninstall removes plugin options/drafts/results but keeps administrator-
approved native categories, which may have acquired independent uses.
