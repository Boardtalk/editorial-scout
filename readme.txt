=== Editorial Scout ===
Contributors: boardtalk
Tags: editorial, drafts, rss, research
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: trunk
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A source-led editorial assistant focused on human-reviewed WordPress drafts.

== Description ==

Editorial Scout is in early development. Administrators can manage public RSS/Atom sources and review discovered article metadata in a read-only queue. Scheduled checks never create or publish posts. No AI or image service is called in this release.

Future development aims to provide source monitoring, evidence review, AI-assisted drafting, and optional image discovery with manual editorial approval.

== Installation ==

1. Upload the editorial-scout plugin folder to the plugins directory.
2. Activate Editorial Scout in the Plugins screen.
3. Open Editorial Scout in the administrator menu and add a direct public HTTPS feed URL.
4. Wait for WP-Cron or trigger a due check with WP-CLI.

Single-site WordPress and PHP cURL, DOM and mbstring extensions are required. Redirects, authenticated feeds, non-HTTPS URLs and proxy-based fetching are unsupported. No API keys are required. See README.md and docs for security decisions, limits and testing.

Deactivation/default uninstall retain data. To remove plugin data on uninstall, explicitly define EDITORIAL_SCOUT_DELETE_DATA as true in wp-config.php before deleting the plugin.

== Frequently Asked Questions ==

= Does this release publish anything automatically? =

No. This release stores discovery metadata only; it never creates or publishes posts.

== Changelog ==

= 0.2.0-dev =
* Add administrator-only RSS/Atom sources and read-only discoveries.
* Add safe bounded feed ingestion, public-address pinning and WP-Cron checking.
* Add deduplication, bounded storage, safe uninstall, tests and CI.

= 0.1.0-dev =
* Initial foundation and administrator landing page.
