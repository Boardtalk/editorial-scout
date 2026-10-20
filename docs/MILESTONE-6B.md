# Milestone 6B: the Editorial Newsroom

Base: `feature/semantic-website-understanding`, `55ef6a6`.
Branch: `feature/editorial-newsroom`. Candidate: **0.7.0-rc1**, GPL-3.0-only.

## Delivered workflow

Newsroom is the default native WordPress tab. Discoveries, Sources, Settings and
Editorial Profiles remain available. A compact strip counts actual unique stories
by editorial state across filters; plugin-created article, author and WordPress
category totals are omitted because article creation is not enabled. Empty views
invite the administrator to add/check sources or change filters.

Ten offers per page use an editorial card grid, with a modest lead-story treatment,
plain titles, supplied publication dates or explicit unknowns, actual configured-feed
and observation counts, and inline expandable attribution and rationale. No summary
is fabricated: existing observations have no stored excerpt field, so summaries are
omitted. No remote images, copied article bodies, AI requests or scraping are added.

Ordering is a **local heuristic**, not semantic assessment: up to eight relevant and
eight excluded saved-profile phrases match literal case-insensitive title substrings.
Clear matches precede recency bands (seven/thirty days), then latest observation and
stable representative ID. If publication is missing, first discovery supplies recency;
future dates receive no bonus. Filters support editorial state, profile for ranking,
optional matches-only, source and source category. SQL ranks the complete filtered
queue before pagination, rather than ranking only the latest page.

True canonical article URLs remain grouped using the existing conservative identity
and tracking-parameter rules. All source observations remain stored. Distinct configured
feed URLs and observation counts are separate; repeated posts on one feed do not add
sources. Feeds may share ownership, copy content or syndicate it: independence is
unknown, coverage is explicitly not corroboration, and feed count never raises rank.
Up to six attribution records appear inline, with full paginated evidence in Discoveries.
Missing sources/links are labelled; untrusted titles/names/URLs are escaped and invalid
links are omitted without performing network/DNS lookups during rendering.

Exactly three editorial choices are offered: **Commission Article**, **Hold for Later**,
**Not Interested**. The neutral Leave unchanged option is not a decision; missing or
blank values preserve current state. Administrators choose across cards and use one
Submit Decisions button. PRG returns to `#newsroom-decisions`, focuses the receipt
progressively and preserves validated filters/page. Each receipt reports the actual
per-item outcome. No SPA or JavaScript dependency is required for decisions.

Existing shortlisted/dismissed rows map directly to Hold/Not Interested. Commission
creates a durable uniquely indexed work item, never a WordPress draft/post. Subsequent
Hold/Not Interested pause/dismiss that same work item, preserving its ID/creation time.
Recommission resumes the same row. It is a future writing-engine integration boundary,
not an implemented writer, research engine, scheduler or publisher.

## Transactions, races and recoverability

Every HTTP mutation requires manage_options, POST and its nonce, and shares the existing
SQL ownership lease. A per-administrator, one-hour, ten-story server-side manifest binds
an opaque single-use token to representative IDs and decision revisions. Opening another
Newsroom view replaces the form. Client keys/values must be bounded and match the
manifest; unknown IDs, nested values or unsupported choices reject the submission.

Decision rows gain a revision UUID changed on transitions. Discoveries forms also
carry checked decision tokens, so an older legacy tab cannot overwrite a newer
Newsroom choice. Tokens use shared decision state rather than filtered source
representatives, preserving source-filtered Discoveries updates. A stale or removed offer is
reported without overwriting its current choice; valid items in that batch can still
commit. Business quota errors are per-item. A database/receipt/ownership failure rolls
back all otherwise valid changes in the transaction. The unique story commission index
and consumed ticket prevent duplicate work on retries or simultaneous POSTs.

A durable processing marker is written before the transaction. Decision, commission,
final ticket and receipt commit together. Raw prepared writes to plugin-owned metadata
keys avoid placing uncommitted receipt values in persistent WordPress caches; the user
metadata cache is invalidated after commit/rollback. Both native options and usermeta
must be InnoDB, in addition to the plugin tables. Nontransactional installations fail
closed with actionable guidance; the plugin never changes native table engines.

Lease renewals inside the transaction also roll back. On rollback the caller's lease
is restored to its pre-transaction serialized value so conditional unlock succeeds,
without releasing another owner's lock. Lost ownership cannot commit new decisions.
Tests inject failures in commission and receipt writes and verify recovery.

If processing is interrupted before commit, the consumed form is not replayed. The
administrator refreshes offers, reviews durable current decisions and submits a fresh
form. If commit occurred before the redirect was lost, the receipt is committed too.
An indeterminate database connection failure is not described as a guaranteed successful
or failed decision; the interface asks for current-state review. Each administrator has
one active manifest and last receipt, not an unbounded audit log. Other privileged code
must use the shared mutation contract; arbitrary third-party SQL edits are outside it.

## Schema 4 and retention

Migration additively creates the InnoDB commissions table with unique story_hash and
status index, and adds a decision revision column. Existing choices, timestamps, source
IDs/attribution, profiles, API settings/budgets and cron state are not rewritten. All
columns/indexes/engines are checked before advancing the schema marker. Interrupted
migration can restart; future schemas are not downgraded. Earlier canonical backfill
remains bounded and unchanged. No network calls occur in migration.

Existing 1,000 retained decisions and 10,000 protected observations remain hard limits;
ordinary unreviewed retention remains 30 days/5,000 observations. No previous choice
is evicted for a new commission. Historical commissions and their evidence stay protected
when held/dismissed. Discoveries' legacy Return to New pauses an existing commission
and retains that story's decision row/evidence; it releases ordinary protection only
for stories never commissioned. This conservatively bounds durable history rather than
silently deleting it; a future explicit archive/export/release workflow is needed to
release historic commission capacity. Default uninstall retains data; explicit opt-in
also removes commissions and Newsroom metadata, retaining native WordPress categories.

Schema 4 cannot be used operationally by 0.6.0: its future-schema guard stops old UI and
cron. Do not change the marker manually. Restore matching pre-upgrade database **and**
files to roll back, coordinating preservation/export of newer editorial decisions and
other WordPress content. There is no automatic destructive downgrade.

## Usability and future onboarding

Preferred/excluded native WordPress category multi-selects are replaced with native
expandable checkbox fieldsets (200 displayed maximum), clear labels and selected counts.
The count updates locally; without JavaScript the initial count and all inputs still
work. Existing native IDs, permissions, limits and descendant privacy exclusions remain.
The single default-category selector is unchanged. Advanced sample/provider controls
remain expandable. Cards, headings, focus, receipts and controls use native semantics
and modest local CSS; no third-party UI package or browser request is introduced.

A **future resumable first-run wizard** is documented, not implemented here. It must
support both established sites (reviewed bounded public samples, profiles and consent)
and new sites (manual identity/topics and source setup without requiring posts or AI).
It should save checkpoints, support skip/resume/back, explain feed schedules/health,
keep external execution off until explicit setup, and separate optional paid consent
from basic local discovery. No wizard or paid licensing ships in this milestone.

## Bounds and limitations

Page size is 10 plus one sentinel, page input bounded to 1–1500; at most 12 data queries
cover offers, totals and six-record attribution previews. Filter catalogs, profiles,
phrases and form/report sizes are bounded. Ranking groups/sorts retained observations
using existing story/identity/status indexes and prepared filters; it never loads the
whole feed queue into PHP. Database runtime still depends on host/indexes and needs
profiling on large installations. No native post bodies or full articles are read.

URL identity is conservative, not semantic duplicate detection. Native-source ownership
and editorial importance cannot be inferred from feed counts. Literal title matching
misses synonyms, context and languages; substring matches are not calibrated relevance.
Missing publication dates and source metadata stay explicit. Per-user form replacement
can invalidate another tab intentionally. Browser/axe checks do not replace real screen
readers, different admin themes, persistent-cache deployment checks or human usability
review. The supplied live staging site and production credentials were never accessed.

## Executed validation

The complete existing/new integration suite passed **638 assertions** on WordPress
**7.1.3 / PHP 8.3.35**, including stale legacy-tab submissions, literal SQL-injection
phrases, concurrent requests and failure recovery. The final CI run is recorded in
the prepared PR and artifact validation receipt. **84 Chromium checks passed** with zero detected scoped axe
WCAG 2/2.1 A/AA violations and no page overflow across all five tabs at 375/768/1280,
expanded provider/privacy controls, ten-offer pagination, long titles and native
category checkboxes. No browser page errors remain. An intermediate filter input
named matches clobbered the native DOM method used by axe; renaming it to title_match
fixed the cause. Explicit navigation waits avoid stale-page pagination assertions.
A larger fixture exposed colour-only table links; visible content-link underlines
fixed that accessibility issue without suppressing any rule.

WordPress Core/Extra coding standards passed for **17 production PHP files**. PHP,
JavaScript and shell syntax passed. Independent ZIP builds matched byte-for-byte;
all 21 runtime files match source and exclude fixtures/dependencies/dummy credentials.
SHA-256: `6d1380b2977a68d478d4385160dca5247cc52842a32307ab215f0dcb7ed375d1`.

Official **Plugin Check 2.1.0**: **0 errors / 172 warnings**. Every warning category:

| Count | Warning | Assessment |
| ---: | --- | --- |
| 87 | WordPress.DB.DirectDatabaseQuery.DirectQuery | Prepared dedicated-table reads/writes, schema verification, native transaction receipts and existing atomic lease/migrations/uninstall. No arbitrary SQL fragments are accepted. |
| 75 | WordPress.DB.DirectDatabaseQuery.NoCaching | Deliberate fresh decisions, source evidence and coordination; receipt writes avoid uncommitted cache values and invalidate user metadata after completion. Persistent-cache deployment still needs staging validation. |
| 1 | PluginCheck.Security.DirectDB.UnescapedDBParameter | Static analyzer cannot follow offers' fully prepared SQL with individually prepared literal LIKE fragments. Fixed clauses, esc_like, prepared filters/identifiers and bounded integers were reviewed; quote/percent/classic SQL-injection profile strings are regression-tested as data. No raw request fragment is interpolated. |
| 2 | WordPress.DB.SlowDBQuery.slow_db_query_meta_key | Raw receipt lookup/writes are restricted to the current user and two fixed plugin keys, using WordPress's user_id/meta_key indexes. |
| 2 | WordPress.DB.SlowDBQuery.slow_db_query_meta_value | These are bounded serialized receipt/manifest writes, not searches over meta_value. Raw writes are intentional to prevent uncommitted metadata-cache reads. |
| 1 | WordPress.DB.DirectDatabaseQuery.SchemaChange | Existing explicit opt-in uninstall drops only plugin tables; default uninstall retains them. |
| 1 | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Existing scalar-filter plus absint posted-ID pipeline followed by native-ID/count checks after capability/nonce gating; static analysis does not trace that pipeline. |
| 2 | WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in | Existing website sampling excludes at most 32 selected post IDs with indexed date bounds and no random/totals queries. |
| 1 | PluginCheck.CodeAnalysis.AIProvider.DirectIntegration | Existing direct OpenAI adapter preserves WordPress 6.5 and explicit privacy/transport/budget controls. The checker recommends considering the WordPress 7.0 AI Client; no adapter change is made here. |

Core WordPress update-service TLS warnings are expected in the intentionally isolated
Docker network. GitHub's pinned actions emit non-failing Node.js 20/forced Node.js 24
and ubuntu-latest/Ubuntu 26 migration annotations; their runtime updates remain CI
maintenance. CI retains PHP 8.0/8.2/8.3/8.4 lint, standards, reproducible packaging,
WordPress 6.5/PHP 8.1 and current PHP 8.2/8.3 integration plus Chromium. Final run and
commit evidence are linked in the prepared PR description.

No paid requests, public posting, deployment, merge, security certification or
WordPress.org approval is implied.

## Changed files

New runtime services/screens: class-editorial-scout-newsroom.php and -newsroom-screen.php.
Store schema/transactional decisions, admin route, dashboard navigation, profile controls,
local CSS/JS, bootstrap/version, opt-in uninstall and WordPress readme integrate them.
Feed transport, runner, scheduling, OpenAI adapter and existing privacy/budget code remain
unchanged. New integration/endpoint tests, extended browser fixtures/flows, deterministic
build allowlist and CI candidate paths validate them. README, staging guide, security/
testing documents and this report describe installation, limits and rollback.
