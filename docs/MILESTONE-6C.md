# Milestone 6C — editorial workflow and WordPress ownership

Base: `feature/editorial-newsroom` at `f58d317`.
Branch: `feature/editorial-workflow`. Candidate: **0.7.1-rc1**, GPL-3.0-only.
No live site, paid AI/image service, article-writing engine, deployment or merge.

## Result and release notes

Newsroom submission now uses a compact anchored confirmation with accurate changed,
unchanged and failed counts, an explicit no-WordPress-post statement and links to
View Commissions / Continue Reviewing. Details are collapsed. A success GET carries
a per-user receipt token: refreshing it does not mint a new ticket or display a
submit button. Continuing/filtering deliberately starts a fresh neutral form.
Authorization, nonces, shared lease, transactional writes/receipts, revision checks,
rollback recovery and replay rejection are retained. Selecting an already-current
state counts as unchanged. The old duplicate error paragraph is removed.

The latest receipt is durable per administrator until replaced by their next
successful submission or explicit operational cleanup; it is **not an append-only
multi-entry audit log**. One-hour form expiry remains. A failed attempt labels an
earlier receipt as earlier, never confirmation of that failure. Repeated POSTs
cannot duplicate commissions. Counts include stale/quota-rejected choices as failed;
transaction-level failures do not fabricate successful per-item outcomes.

Articles adds a separate twenty-row commission queue with live status counts,
filters, stable pagination, original-source names, full evidence/discovery links,
created dates, profile snapshots and navigation to existing decision controls.
Waiting, Held, Cancelled (not interested) and Paused (returned to New) reflect actual
operational states. No writer or artificial writing-error state is introduced.
Older commissions correctly show Not recorded for profile; new first commissions
retain the selected saved profile ID/name. Subsequent profile edits/deletion or
hold/recommission do not rewrite that snapshot or commission identity. Attribution
is source material, never verification or independent corroboration.

WordPress articles is a different read-only view. Only exact namespaced provenance
on actual native `post` objects qualifies. Draft, Pending, Scheduled, Published,
Private and Trash reflect WordPress status live. Exact key/value checks exclude
wrong case, suffixes, pages and unrelated posts. Marker rows cannot multiply a
post. Native post permissions gate access and editor links. This version assigns
no native provenance and writes no article; normally the native view is empty.
A marker is not a signature: privileged plugins/administrators can forge it.

The permanent storage, rendering, removal and future linking/reconciliation contract
is [ARTICLE-OWNERSHIP.md](ARTICLE-OWNERSHIP.md). Native posts, media files, taxonomies
and normal/provenance metadata must survive **every** uninstall mode. Future writer
crash consistency cannot be solved by wrapping `wp_insert_post` in the operational
SQL transaction. A durable site-scoped idempotency key, journal and reconciliation
are required before that engine can ship. No such writer is claimed here.

## Storage, performance and rollback

Schema **5** adds `commissions.profile_id char(36)` and
`profile_name varchar(120)`, both empty by default. Migration is additive, under
the existing lease, validates the resulting columns/indexes/InnoDB engines before
advancing and can restart after DDL failure. Existing commission IDs/statuses/dates,
discoveries, decisions/revisions, categories, sources, schedules and AI controls
are retained. No speculative historical-profile backfill or native-post labelling.
The existing future-schema guard remains. Older milestones upgrade through the
existing restartable migration path as well.

Commission data needs two queries (page and global totals); evidence is joined to
one original observation per commission without pulling the queue into PHP. Existing
story/status indexes and primary keys support bounded rows and stable tie ordering.
Twenty rows and a sentinel detect next page; offsets are capped at 1,000 pages.
Current 1,000 retained decision/commission limit and 10,000 protected-observation
limit are unchanged. Historic commission capacity still needs a future explicit
archive/export/release workflow; no silent eviction is added.

Native history uses paginated `WP_Query` with native meta-key equality/indexing,
binary value matching and a scoped, prepared EXISTS predicate requiring exact
key/value bytes (ordinary meta-key comparisons are case-insensitive). The local
`posts_where` filter is removed in `finally`. Term/meta cache preloads are disabled;
post results can use native query caching. No plugin-owned native-content cache
or destructive backfill is needed. Other plugins can filter native queries or
permissions; per-post read capability is rechecked. No misleading global native
count is shown. Very large postmeta tables and persistent object caches need
site-specific profiling; bounded output does not guarantee constant DB scan cost.

Follow [STAGING-TESTING.md](../STAGING-TESTING.md). Back up **database and files
as a matching pair**. Version 0.7.0 refuses future schema 5, so a file-only downgrade
is not an operational rollback. Restore its matching pre-upgrade database too,
after preserving/exporting new decisions and coordinating other WordPress content
created since backup. Never force a schema marker backward. Default uninstall
retains operational history; explicit cleanup removes only plugin operational data,
not WordPress posts/media/terms/post meta. Deactivate before restoring files.

## Validation

- **687 integration assertions passed** against the extracted runtime ZIP on
  WordPress **7.1.3 / PHP 8.3.35**, MariaDB 11, including the full prior suites.
- **114 Chromium checks passed**: six sections, real form submissions, compact
  five-count receipts, refresh without repeat submission, focus, commission and
  native status filtering, native pagination and JavaScript-disabled native forms.
  Zero detected scoped axe WCAG 2/2.1 A/AA violations or page-wide overflow at
  **375 / 768 / 1280 px**, including empty and populated native history. No browser
  page errors. Desktop Newsroom/Articles screenshots are in ignored `artifacts/`.
- WordPress Core/Extra coding standards passed for **18 production PHP files**;
  PHP syntax, JavaScript syntax, shell syntax and whitespace checks passed.
- The independent reproducible ZIP rebuild matches byte-for-byte. All **22 runtime
  entries** match current source, under the `editorial-scout/` root, with fixed
  timestamps/modes and an explicit allowlist; no vendor, fixtures, credentials,
  test dependencies or documentation trees are packaged. Size: **275,888 bytes**.
  SHA-256: `7ad4d99cb39eab9f47a450bdc043fd7ca7f3b0f83295a67e7addd3f5877e7548`.
- Existing CI retains PHP **8.0 / 8.2 / 8.3 / 8.4** syntax, standards, reproducible
  packaging, integrations on **WordPress 6.5 / PHP 8.1** and current WordPress on
  **PHP 8.2 / 8.3**, with the browser suite on PHP 8.3. Final run evidence will be
  linked in the prepared PR and ignored validation receipt.
Official **WordPress Plugin Check 2.1.0** completed on the final package with
**0 errors / 176 warnings**, no stderr. All warning categories were reviewed:

| Count | Warning | Assessment |
| ---: | --- | --- |
| 89 | DirectDatabaseQuery.DirectQuery | Prepared plugin-table operations and coordination/schema checks; two added read-only commission page/count queries. No request-provided SQL fragments. |
| 77 | DirectDatabaseQuery.NoCaching | Fresh operational state is intentional. Native post history uses WordPress APIs/caches; raw transactional receipts avoid leaking uncommitted state into metadata caches. |
| 1 | PluginCheck.Security.DirectDB.UnescapedDBParameter | Existing title-ranking SQL: fixed clauses with individually prepared literal LIKE fragments. Static analysis cannot trace the composed prepared query; quote/percent/injection-string regressions pass. |
| 2 | SlowDBQuery.slow_db_query_meta_key | Existing current-user/two-fixed-key receipt lookups use native user_id/meta_key indexes. |
| 2 | SlowDBQuery.slow_db_query_meta_value | Bounded serialized manifest/receipt writes, not unbounded value searches. Raw writes are intentional for transaction/cache consistency. |
| 1 | DirectDatabaseQuery.SchemaChange | Existing explicit opt-in removes plugin operational tables only; lifecycle tests preserve native content and media in both uninstall modes. |
| 1 | ValidatedSanitizedInput.InputNotSanitized | Existing scalar-filter/absint posted-ID pipeline followed by native ID/count checks after capability/nonce gating; analyzer does not follow this pipeline. |
| 2 | WPQueryParams.PostNotIn_post__not_in | Existing Learn sample exclusion is bounded to 32 IDs with date ranges, not random or unbounded selection. |
| 1 | AIProvider.DirectIntegration | Existing opt-in OpenAI adapter preserves WordPress 6.5 support and its privacy/budget/transport controls. Checker recommends considering the WordPress 7 AI Client; no provider change here. |

Compared with 0.7.0, the only four added findings are the two commission queries'
direct-query/no-cache warnings. The full raw output is retained in ignored
`artifacts/milestone6c-plugin-check.txt`. These warnings and passing automation are
not security certification or Directory approval. Core update-service TLS warnings
are expected on the intentionally isolated fixture network. GitHub's pinned actions
may emit non-failing Node 20/forced Node 24 and ubuntu-latest/Ubuntu 26 migration
annotations; their runtime updates remain maintenance work.

Tests run solely in disposable local WordPress databases. Native posts and an image
in ownership tests are explicit **test fixtures**, not plugin writing functionality.
All provider requests are intercepted mocks, and real feed transport uses isolated
DNS/TLS fixtures without access to public services.

## Changed files

- `editorial-scout.php`, `readme.txt`: release and component registration, user-facing
  ownership/feedback behaviour, accurate prior changelog preserved.
- `includes/class-editorial-scout-articles.php`: commission service/screen, native
  provenance reader, permissions, status filters and bounded pagination.
- `includes/class-editorial-scout-store.php`: additive profile columns, validation
  and optional first-commission profile snapshot; existing mutation rules retained.
- `includes/class-editorial-scout-newsroom.php`: durable latest receipt, accurate
  outcome summary and unchanged-state handling; transactional context preserved.
- `includes/class-editorial-scout-newsroom-screen.php`: compact receipt, completed
  form suppression, next actions, duplicate-notice/query cleanup.
- `includes/class-editorial-scout-admin.php`, `class-editorial-scout-dashboard.php`:
  success receipt PRG token and Articles navigation alongside all existing sections.
- `uninstall.php`: explicit native-content protection comment; no destructive
  WordPress-owned content or media cleanup is introduced.
- `tests/editorial-work.php`, `editorial-work-endpoint.php`: migration, receipt,
  queue/native filters/pagination/permissions and ownership/lifecycle fixtures.
- `tests/newsroom.php`, `integration.php`: forward-schema regression and lifecycle
  integration; earlier batch/replay/security coverage remains.
- `tests/browser-setup.php`, `browser-native-work.php`, `browser/browser.cjs`:
  disposable fixture lifecycle, actual feedback/queue/native navigation, native
  pagination and expanded accessibility/responsive coverage.
- `tools/build-staging.py`, `.github/workflows/ci.yml`: allowlist the new runtime
  class and install/test the current 0.7.1 package across the existing matrix.
- `STAGING-TESTING.md`, this report and `docs/ARTICLE-OWNERSHIP.md`: release,
  installation/manual procedures, limitations, paired rollback and future contract.

## Remaining manual checks and limits

Screen-reader use, other admin themes/plugins, persistent object-cache servers,
huge postmeta tables and unusual capability customizations require staging review.
Scoped axe is partial accessibility evidence, not a certification. Standard
single-site WordPress remains supported; multisite is unsupported. Native history
requires manage_options plus edit_others_posts/read_private_posts, and each row's
read/edit permissions; deliberately restricted administrators may see no history.
Core/native query filters are respected. No marker is retrospectively assigned,
so absence of attributable content is expected until a future writer exists.

There is no commission-to-native-post linker yet: its uniqueness, cross-site identity,
retry and crash recovery are design requirements, not shipped writing capabilities.
No writing AI, automatic publication, Pixabay, Yoast generation or licensing exists.
No security certification or WordPress.org approval is claimed.
