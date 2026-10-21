=== Editorial Scout ===
Contributors: boardtalk
Tags: editorial, drafts, rss, research
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.0
Stable tag: 0.9.0-rc1
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A source-led editorial assistant focused on human-reviewed WordPress drafts.

== Description ==

Editorial Scout is in early development. Administrators can manage public RSS/Atom sources and review discovered article metadata in an editorial queue. Scheduled checks never create or publish posts. Optional article writing requires explicit evidence review, one-request consent and separate native-draft approval. Optional, explicitly consented OpenAI website understanding is available; no image services or automatic AI calls occur.

Future development aims to provide source monitoring, evidence review, AI-assisted drafting, and optional image discovery with manual editorial approval.

== Installation ==

1. Upload the editorial-scout plugin folder to the plugins directory.
2. Activate Editorial Scout in the Plugins screen.
3. Open Editorial Scout in the administrator menu and add a direct public HTTPS feed URL.
4. Choose a feed checking interval in Settings, or Manual Only. Use Check Now or Check All Sources Now for immediate administrator-driven checks.

Single-site WordPress and PHP cURL, DOM and mbstring extensions are required. Redirects, authenticated feeds, non-HTTPS URLs and proxy-based fetching are unsupported. No API keys are required. Sources are limited to 50; requests are limited to 1 MiB, ten HTTP seconds and 50 items. Unreviewed observations are pruned to 30 days and 5,000 records when checks run. Shortlisted/dismissed stories and their source evidence are protected, with explicit limits of 1,000 stories and 10,000 retained observations. Source removal and URL changes retain protected evidence. Decisions are shared between administrators. Known tracking parameters are ignored for story grouping; meaningful URLs are not merged by title. Check Now requires an enabled source and has a server-side five-minute cooldown. No article pages, images or enclosures are fetched. Selected feed hosts receive your server IP and the WordPress user agent. Source/discovery metadata stays in your database. See https://github.com/Boardtalk/editorial-scout for security decisions and staging procedures.

Deactivation/default uninstall retain data. To remove plugin-only operational data on uninstall, explicitly define EDITORIAL_SCOUT_DELETE_DATA as true in wp-config.php before deleting the plugin. No uninstall mode deletes native posts, drafts, attachments, media files, assigned taxonomies or post metadata.

== Frequently Asked Questions ==

= Does this release publish anything automatically? =

No. Scheduled work stores discovery metadata only. Explicit administrator-controlled writing can create a native WordPress draft after review; it never publishes, schedules or submits it for review.

Feed checking supports Manual Only, Hourly (the upgrade default), Daily, Twice Weekly (every 84 hours) and Weekly. Weekly and twice-weekly checks may miss items removed from feeds; complete coverage is not guaranteed. Automatic work uses ordinary WordPress cron APIs, compatible with standard traffic-triggered WP-Cron and any external scheduler that correctly services WordPress cron. No hosting-panel detection or integration is required. Cron Health Monitor reports real execution, due work and a one-hour startup/traffic tolerance. DISABLE_WP_CRON alone is not an error. Diagnostic copying excludes source URLs, credentials and infrastructure identifiers. Manual batches process one enabled source per explicit request, with resumable progress and existing cooldowns. These settings control feed discovery only, never AI, draft generation or publishing.

== Editorial intelligence foundation ==

Editorial Profiles supports up to ten administrator-reviewed profiles with native WordPress category preferences. Learn My Website is opt-in local keyword analysis of at most 32 public, non-password-protected standard posts, across eight date windows and up to four category strata. It reads bounded excerpts, keeps no original bodies and creates an inactive one-hour review proposal. Excluded categories include descendants; an incomplete category catalog prevents exclusions rather than silently ignoring descendants.

Relevance recommendations are local title-phrase matching, explicitly separate from human shortlist/dismiss choices. They are not semantic AI, fact checking or calibrated confidence. Batches contain up to five stories; unchanged inputs deduplicate. Suggested native categories require explicit approval and manage_categories; no post is created. Source-monitoring categories remain separate.

AI website understanding is optional and off by default. Configure EDITORIAL_SCOUT_OPENAI_API_KEY server-side, enable the connection, preview approved public samples and consent separately to each billable analysis. Model: gpt-4.1-mini-2025-04-14. Daily/monthly reserved-token limits and a one-minute request cooldown apply. Pricing assumptions are dated estimates, not guaranteed billing caps. Unknown audience/geography should remain blank; all proposals require editable review and explicit save. No keys are stored in plugin options. No paid requests were used for validation.

== External services ==

Optional OpenAI semantic learning sends only the administrator-previewed sample of public post IDs, titles, dates, categories and bounded excerpts, plus native category structure, to https://api.openai.com/v1/responses after per-analysis consent. A separate billable connection test sends no website content. Execution is off by default. No automatic AI calls. API credentials must be configured server-side; none are saved in the database. See https://openai.com/policies/terms-of-use/ and https://openai.com/policies/privacy-policy/ . store:false does not guarantee zero retention. Local keyword analysis needs no external service. Explicit article writing additionally sends the reviewed story, profile snapshot, selected source names/URLs/short permitted material and chosen category to the same Responses endpoint after separate one-request consent. Author identity, private posts and media are not included. No article-page crawler is enabled.

== Newsroom ==

The default Newsroom shows ten grouped story offers per page with inline evidence, honest date/profile signals and heuristic priority. Choose Commission Article, Hold for Later or Not Interested across offers, then Submit Decisions once. Blank choices remain unchanged. Commissioning stores only a durable work item; no WordPress draft or article is created. Existing shortlist/dismiss decisions map directly. Filters retain access to completed choices. Coverage is not verification or proof of source independence; no feed summaries are invented.

Schema 6 adds a verified writing journal alongside schema 5 first-commission profile snapshots and schema 4 commissions without rewriting existing choices or guessing historic profiles. Single-use per-administrator forms, stale checks and transactional receipts protect batch submissions. WordPress options/usermeta and plugin tables require InnoDB for batch writes; other native engines fail closed. Repeated submissions cannot create duplicate commissions. Historic commission evidence stays protected, even when paused via Discoveries. Follow the repository staging guide for matching database/file rollback. Category preferences/exclusions now use expandable checkboxes.

== Articles and ownership ==

Submit Decisions returns a compact confirmation with commissioned/held/dismissed/unchanged/failed counts, a collapsed detailed receipt and View Commissions / Continue Reviewing links. The completed receipt page never revives its batch. One latest per-administrator receipt persists until replaced or explicitly cleaned up; one-hour form expiry is separate.

Articles separates durable commissions from real WordPress articles. Commission states distinguish waiting, generated results awaiting approval, incomplete/failed writing, interrupted recovery and native article links, plus held/cancelled/paused decisions. Filters and twenty-record pages read current storage. New commissions retain the selected saved profile name; older work says Not recorded. Decisions remain editable through Newsroom/Discoveries.

Native article history is read-only and requires exact _editorial_scout_origin = editorial-scout/v1 provenance on standard WordPress posts, plus native post permissions. Only separately approved new drafts receive that marker; existing/manual posts are never retrospectively labelled. WordPress Draft/Pending Review/Scheduled/Published labels always mean real WordPress statuses. No native article is inferred from a commission, author or title. Native drafts store body/title/excerpt and status in WordPress, with native authors/categories and standard rendering. Suggested tags remain notes; no images are downloaded. Operational tables must never be the sole store of finished content. See docs/ARTICLE-OWNERSHIP.md in the repository for the durable linking/recovery contract and lifecycle safeguards.

== Explicit article drafting ==

Articles offers Prepare article draft for eligible commissions. Review sources, choose a saved profile, permitted author and existing category, and provide short original/permitted research notes (up to four sources, 40-600 characters each). Article pages are not crawled where usage/robots permissions are unknown. Headlines alone are research incomplete without a paid call. No paywalls, authentication or usage restrictions are bypassed.

Prepare writing request shows the exact material, profile and estimated allowance. Write draft requires separate per-request consent and uses the existing server-configured OpenAI connection, token reservation, cooldown and fixed Responses endpoint. Reservations remain on uncertain failures; there is no automatic paid retry. Review generated text and source/claim notes before separately approving Create WordPress draft. AI output is untrusted and not independently fact-checked. Structural grounding, copying and numeric screens cannot prove truth or originality.

Drafts are native WordPress posts, never automatic public content. A durable unique journal and native insertion markers reconcile interrupted creation; ambiguous cases stop for administrator review instead of inserting again. Replays never overwrite editor changes. Notes/source lists remain plugin workflow data, separate from article body. No images, Yoast metadata or background writer is implemented. Review provider privacy, source rights, costs and paired database/plugin rollback in the repository staging guide.

== Changelog ==

= 0.9.0-rc1 =
* Add direct story decisions to compact newspaper cards with expandable evidence and filters.
* Organize Articles into Waiting, Drafts, Published and History using live native post state.
* Keep separate writing/draft approvals, safe journals, WordPress ownership and non-destructive recovery.

= 0.8.3-rc1 =
* Accept bounded documented Responses message/text variations while rejecting incomplete, refused or unsafe output.
* Align writing guidance with local limits and verification rules; allow safe private punctuation and grounded public quotations.
* Add safe in-admin writing diagnostics and copy summary without provider text or private notes.
* Preserve journals, reservations and WordPress ownership. Old prepared requests need fresh review and consent.

= 0.8.2-rc1 =
* Fix article preparation form recovery and commission-scoped, article-specific notices.
* Accept valid reordered JSON object keys while retaining exact schemas and safe response-validation diagnostics.


= 0.8.1-rc1 =
* Recoverable Newsroom housekeeping, active-source visibility and safe commission cancellation/restoration.
* Bounded confirmed cleanup preserves all evidence and protects every writing journal and native post.


= 0.8.0-rc1 =
* Add explicit source/profile review, one-shot consent-scoped article generation and separate native draft approval.
* Add strict structured output, incomplete-research outcomes and separate source/verification notes.
* Add durable dispatch/creation journals, unique native links and conservative crash reconciliation without overwriting editor content.

= 0.7.1-rc1 =
* Add compact, accessible decision confirmations and durable expandable receipts without repeat submission of completed batches.
* Add paginated Articles commission history, first-commission profile snapshots and read-only exact native-post attribution.
* Establish permanent native content/media ownership and test deactivation, reinstall and both uninstall modes.

= 0.7.0-rc1 =
* Add the default Newsroom editorial desk with ten-offer pages, grounded heuristic priority and inline source evidence.
* Add durable, idempotent commissions and transactional multi-story decisions with per-item receipts and stale-form protection.
* Preserve earlier decisions, source attribution, schedules, AI privacy and budgets; add expandable native-category checkboxes.


= 0.6.0-rc1 =
* Optional, consent-scoped OpenAI website understanding with sample previews, editable evidence-backed proposals, server-configured credentials and conservative token budgets.
* Clearer local/AI distinction and article titles in evidence.


= 0.5.0-rc1 =
* Add editorial profiles, generic starters and reviewed local website-learning proposals.
* Add bounded local relevance recommendations and native category approval.
* Add a fail-closed provider contract and mocked structured-output checks; defer live AI.
* Preserve schema 3, schedules, source attribution and human editorial decisions.

= 0.4.0-rc1 =
* Add feed-only schedule settings and hosting-agnostic execution health.
* Add synchronous, resumable Check All with per-source cooldowns.
* Add sanitized diagnostics and feed-coverage guidance.
* Preserve schema 3 and all editorial decisions, categories and attribution.

= 0.3.0-rc1 =
* Add native Sources, Discoveries and Settings sections with categories and filters.
* Add locked Check Now actions with persisted cooldown.
* Add durable shared shortlists/dismissals and cross-source URL grouping with coverage.
* Upgrade storage additively to schema 3; protect editorial evidence during pruning.

= 0.2.1-rc1 =
* Harden lock concurrency, migrations, atomic data changes and feed transport.
* Improve responsive tables and administrator form accessibility.
* Add regression coverage and reproducible staging packaging.

= 0.2.0-dev =
* Add administrator-only RSS/Atom sources and read-only discoveries.
* Add safe bounded feed ingestion, public-address pinning and WP-Cron checking.
* Add deduplication, bounded storage, safe uninstall, tests and CI.

= 0.1.0-dev =
* Initial foundation and administrator landing page.
