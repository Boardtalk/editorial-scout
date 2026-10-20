=== Editorial Scout ===
Contributors: boardtalk
Tags: editorial, drafts, rss, research
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.0
Stable tag: 0.5.0-rc1
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A source-led editorial assistant focused on human-reviewed WordPress drafts.

== Description ==

Editorial Scout is in early development. Administrators can manage public RSS/Atom sources and review discovered article metadata in an editorial queue. Scheduled checks never create or publish posts. No AI or image service is called in this release.

Future development aims to provide source monitoring, evidence review, AI-assisted drafting, and optional image discovery with manual editorial approval.

== Installation ==

1. Upload the editorial-scout plugin folder to the plugins directory.
2. Activate Editorial Scout in the Plugins screen.
3. Open Editorial Scout in the administrator menu and add a direct public HTTPS feed URL.
4. Choose a feed checking interval in Settings, or Manual Only. Use Check Now or Check All Sources Now for immediate administrator-driven checks.

Single-site WordPress and PHP cURL, DOM and mbstring extensions are required. Redirects, authenticated feeds, non-HTTPS URLs and proxy-based fetching are unsupported. No API keys are required. Sources are limited to 50; requests are limited to 1 MiB, ten HTTP seconds and 50 items. Unreviewed observations are pruned to 30 days and 5,000 records when checks run. Shortlisted/dismissed stories and their source evidence are protected, with explicit limits of 1,000 stories and 10,000 retained observations. Source removal and URL changes retain protected evidence. Decisions are shared between administrators. Known tracking parameters are ignored for story grouping; meaningful URLs are not merged by title. Check Now requires an enabled source and has a server-side five-minute cooldown. No article pages, images or enclosures are fetched. Selected feed hosts receive your server IP and the WordPress user agent. Source/discovery metadata stays in your database. See https://github.com/Boardtalk/editorial-scout for security decisions and staging procedures.

Deactivation/default uninstall retain data. To remove plugin data on uninstall, explicitly define EDITORIAL_SCOUT_DELETE_DATA as true in wp-config.php before deleting the plugin.

== Frequently Asked Questions ==

= Does this release publish anything automatically? =

No. This release stores discovery metadata only; it never creates or publishes posts.

Feed checking supports Manual Only, Hourly (the upgrade default), Daily, Twice Weekly (every 84 hours) and Weekly. Weekly and twice-weekly checks may miss items removed from feeds; complete coverage is not guaranteed. Automatic work uses ordinary WordPress cron APIs, compatible with standard traffic-triggered WP-Cron and any external scheduler that correctly services WordPress cron. No hosting-panel detection or integration is required. Cron Health Monitor reports real execution, due work and a one-hour startup/traffic tolerance. DISABLE_WP_CRON alone is not an error. Diagnostic copying excludes source URLs, credentials and infrastructure identifiers. Manual batches process one enabled source per explicit request, with resumable progress and existing cooldowns. These settings control feed discovery only, never AI, draft generation or publishing.

== Editorial intelligence foundation ==

Editorial Profiles supports up to ten administrator-reviewed profiles with native WordPress category preferences. Learn My Website is opt-in local keyword analysis of at most 32 public, non-password-protected standard posts, across eight date windows and up to four category strata. It reads bounded excerpts, keeps no original bodies and creates an inactive one-hour review proposal. Excluded categories include descendants; an incomplete category catalog prevents exclusions rather than silently ignoring descendants.

Relevance recommendations are local title-phrase matching, explicitly separate from human shortlist/dismiss choices. They are not semantic AI, fact checking or calibrated confidence. Batches contain up to five stories; unchanged inputs deduplicate. Suggested native categories require explicit approval and manage_categories; no post is created. Source-monitoring categories remain separate.

No live AI adapter, API-key entry/storage, external consent workflow or billing controls ship in 0.5.0-rc1. External provider execution is blocked even if consent is supplied to the contract. OpenAI, semantic learning, author/tag assignments, SEO and image integrations are deferred. No paid requests were used for validation. This is a tested local foundation, not a complete AI editorial pipeline.

== Changelog ==

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
