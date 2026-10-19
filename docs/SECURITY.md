# Security design

## Authorization and storage

`manage_options` is required for every source mutation and queue view. Customized
WordPress roles follow capability assignment, not a hard-coded role name. Mutations
require POST, a capability check before processing, and a verified WordPress nonce.
Nonces protect against CSRF, not unauthorized roles. Scalar inputs are validated,
names sanitized, and UI output escaped by context. External links use HTTPS and
`noopener noreferrer`. Read-only pagination/edit navigation has no side effects.

An atomic option-backed lock serializes scheduled workers and source mutations.
Expired locks are reclaimed with compare-and-delete; only the owning token can
release a lock. A crash may delay edits/checks for up to 15 minutes. Custom tables
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
validator, and the conservative IPv6 `2001::/16` exclusion can reject otherwise
public feeds. There is no IP failover, redirect following, conditional GET,
authenticated access, HTTP article support or cross-source deduplication. Locking
and unique indexes do not provide a distributed job system or durable archive.

Report potential vulnerabilities through the repository's private reporting feature
if enabled, or coordinate privately with maintainers. Do not post exploit details
or confidential site data publicly. This release claims no reporting email or SLA.
