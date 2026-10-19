# Milestone 3: editorial dashboard and discovery workflow

Base: `feature/discovery-staging-hardening`, `4abcaa0`, draft PR #3.
Implementation branch: `feature/editorial-dashboard`. Release candidate: `0.3.0-rc1`.
The prior implementation is extended; its cron hook, public-URL/TLS/pinning boundary,
lease ownership, bounded feed parsing and no-post behavior are retained.

The user reported successful 0.2.1-rc1 staging operation on WordPress 7.1.3 / PHP
8.3.35, including WordPress News and Plesk cron. That site was not accessed or changed
by this work. New validation uses isolated Docker services and generated fixtures.

## Product behavior and deliberate choices

- Native WordPress Sources, Discoveries and Settings links, form/table conventions,
  translated labels, contextual escaping, accessible field labels, named scrollable
  table regions and responsive layout. All access still requires `manage_options`.
- Categories have stable IDs, 80-character names and a 20-category limit. Rename
  preserves assignments; deletion explicitly reassigns all its sources, including
  archives, to Uncategorized. Categories describe sources rather than tagging stories.
- Discoveries filter by source, source category (including Uncategorized), inclusive
  UTC discovery dates and New/Shortlisted/Dismissed status. The queue is paginated
  using 51-row lookahead. Coverage is independently paginated and includes archived
  source identities; source links can filter old records beyond the initial dropdown.
- Story grouping is conservative canonical-URL matching, not semantic/headline
  deduplication. Only documented tracking query keys are discarded. Meaningful query
  parameters/order, paths and trailing slashes remain. Every original source URL and
  observation stays separate, with its original metadata. Changing a title on a
  later fetch does not overwrite that evidence.
- Decisions are shared across administrators, not personal bookmarks. The last
  serialized authorized action wins. Routine checks cannot reset choices. Both
  shortlisted and dismissed stories retain their observations beyond normal pruning.
- Unreviewed retention stays 30 days/5,000 observations. Explicit quotas allow
  1,000 decided stories and 10,000 associated observations; quota failures never
  evict earlier decisions. Returning a story to New deliberately releases protection.
- Source removal/URL changes discard unreviewed history but preserve curated evidence
  under an archived old source identity. A changed curated URL gets a new active ID.
  Archives never run checks. Empty archives are collected after protection is released.
- Check Now is an administrator POST with nonce verification, the same SQL lease
  as cron, and the same validated HTTP/ingestion path. It reserves a persisted
  five-minute cooldown before networking; recent cron checks count too. Failed
  attempts, edits, disable/re-enable and independent requests cannot reset it.
  Missing, disabled or archived sources are rejected. This is a per-configured-source
  interval, not a defense against an administrator deliberately creating new identities.
- Manual checks update last/next checks and failure backoff. Friendly fixed outcome
  messages and per-user expiring notices expose no raw responses, SQL, proxy details
  or stack traces. Existing Plesk/server cron can keep invoking the unchanged
  `editorial_scout_check_feeds` WordPress hook.

## Storage, migration and query load

Schema 3 is additive: sources gain category/archive/cooldown fields and an active
source index; observations gain canonical `story_hash` and a story/date index; new
indexed categories and decisions tables hold small bounded configuration/state.
The decision table contains hash/status/UTC update time, not secret keys or editor
profiles. No WordPress posts, post meta or article bodies are created.

Migration owns the existing SQL lease, verifies column/index/InnoDB readiness and
backfills local observations in 200-row CASE updates. It renews ownership between
batches and advances the marker only after successful completion. Retry preserves
IDs, feed URLs, source scheduling times, titles and timestamps; it never fetches feeds.
Failed migration pauses normal plugin work. A future schema marker is not downgraded.

Source/history changes and category removal use transactions. Failed decision probes
cannot turn protected evidence into an ordinary deletion. Retention joins against
indexed decision hashes and orders by discovery timestamp/ID. A queue page requires
one prepared query with fixed SQL and allowlisted values; no attribution N+1, total
pagination COUNT or unbounded client data export is used. Coverage reads at most 51
records. Ingestion preloads existing identities/decisions in bounded batches, then
performs at most 50 inserts. Pending storage is 5,000 plus at most one worker batch;
protected storage is at most 10,000. Active sources remain capped at 50.

**Rollback restores files and the matching database backup.** An older plugin cannot
honor editorial protection and must not run its pruner on schema-3 data. This build
performs no destructive automatic downgrade. Deactivation invalidates in-flight
leases; default uninstall retains all four tables. Explicit opt-in removes only
plugin tables/options. See STAGING-TESTING.md for the upgrade/rollback sequence.

## Verification

The extracted release ZIP passed 306 integration/security/lifecycle assertions on
WordPress 7.1.3 / PHP 8.3.35 and separately 306 assertions on WordPress 7.1.3 /
PHP 8.2.34, with WP_DEBUG enabled. Chromium passed 29 workflow,
responsive and scoped axe WCAG 2/2.1 A/AA checks across all three sections at three
widths. Production WordPress coding standards, PHP 8.3 syntax, JavaScript syntax,
shell syntax and two-build ZIP byte reproducibility passed.

Official Plugin Check 2.1.0 (upstream commit
`e8186b80c43c32b5841cabfdee8ebc21594bf89e`) reported **0 errors / 114 warnings**:
58 direct-query, 55 uncached-query and one schema-change warning. These are reviewed
custom-table/atomic-lease operations: values are prepared, identifiers come from fixed
plugin tables, reads are bounded, and caching cannot replace fresh lock/decision/quota
reads. This does not establish Directory acceptance; future listing review must resolve
or accept the warnings. Checker bootstrap triggered three WordPress core update-service
TLS warnings in this isolated environment; no plugin PHP warnings were observed.

CI includes syntax on PHP 8.0/8.2/8.3/8.4 and packaged integration on minimum
WordPress 6.5/PHP 8.1 plus current WordPress/PHP 8.2 and 8.3, with browser checks on
8.3. Hosted results must be assessed separately from these local runs.
The suite preserves the original security/hardening assertions, with UI expectations
updated for the new navigation rather than removing the capability/escaping checks.

- Upgrade tests reconstruct the prior two-table schema and 406 observations, interrupt
  backfill, retry it in three SQL batches, and verify preserved identity/metadata/due times.
- Workflow regressions cover source/category/date/status filters, meaningful-query
  distinction, cross-source grouping and original coverage, shared decisions, aged
  curated retention, source archive/versioning, category deletion and explicit release.
- Independent POST processes test all new actions against anonymous/editor/invalid
  nonce/GET requests. Manual fetch, failure messages, cross-process cooldown, lease
  exclusion, disabled/missing-source checks and edits preserving cooldown are covered.
- Quota tests seed 1,000 decisions and 10,000 protected observations, verify refusal
  without eviction, then release protection and restore the ordinary retention bound.
  Lifecycle tests verify default retention and explicit removal of both new tables.
- PHP 8.3 revealed that built-in IP filtering accepted the IPv6 documentation range
  `2001:db8::/32` on this runtime. The network boundary now rejects it explicitly in
  binary form; the existing IP regression remains enabled.
- Browser checks cover all three sections at 375/768/1280px, scoped axe WCAG A/AA,
  category creation, source editing/assignment, real pinned TLS Check Now/cooldown,
  disabling, shortlisting, status filtering and coverage. External browser requests
  are blocked; WordPress core avatars/widgets cannot contact image services.

## Remaining limitations and risks

Canonical URL grouping can miss the same event on different publishers or different
meaningful URLs, and may group tracking variants a particular site uses semantically.
It does not assert factual equivalence; reviewers can inspect original coverage.
Decisions are shared and require administrator capability; there is no per-user
history, moderation audit trail, undo log or article-generation workflow.

Protected evidence is retained until explicitly released, so admins must manage
quotas. A quota/DB failure may leave a partially ingested feed batch; later checks
are idempotent and never reset existing choices. A crashed lease can delay operations
for up to 15 minutes. System DNS has its own timeout outside the cURL deadline.
No proxy-dependent fetching, authenticated feeds, multisite or AI/image integration
is introduced. Native WordPress filters/other trusted plugins remain in the trust boundary.

Browser checks do not establish full WordPress admin accessibility, screen-reader
usability or Firefox/Safari coverage. Redis/Memcached and each hosting environment
still need deployment-specific checks. PHP versions beyond the executed matrix are
not implicitly certified. Directory policy, slug/contributor ownership and acceptance
of reviewed database warnings remain release gates. There is no security certification
or WordPress.org approval, deployment, merge, paid API use or external publication.

## Changed files

Runtime: bootstrap/version; existing admin, store, runner and feed classes; new
`includes/class-editorial-scout-dashboard.php`; `assets/admin.css`; `uninstall.php`;
`readme.txt`. The feed change is the explicit PHP-8.3-safe documentation-IP rejection.

Development: `tests/workflow.php`, `tests/workflow-upgrade.php`,
`tests/workflow-endpoint.php`; existing integration/browser setup/browser runner;
Compose debugging configuration; build allowlist; and CI's PHP 8.3/full-package matrix.

Documentation: README, STAGING-TESTING.md, SECURITY.md, TESTING.md and this review.
GPL LICENSE, Composer lock/dependencies and browser dependency lock are preserved.
The ten-file staging ZIP excludes all fixtures, tools, dependencies and credentials.
