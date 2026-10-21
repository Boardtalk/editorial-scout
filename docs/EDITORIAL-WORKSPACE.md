# Milestone 8A — editorial workspace (0.9.0-rc1)

Base: `fix/openai-writing-reliability` at `3109a3c`.
Branch: `feature/editorial-workspace`. No merge or deployment is authorized here.

## What changed

Newsroom retains the newspaper cards with serif headlines and native WordPress
controls. Commission Article, Hold for later and Not interested are visible
without opening evidence. A direct action submits only that story, ignoring other
pending bulk choices. It uses the existing capability/nonce, per-administrator
manifest, revision checks, lease, transaction and durable receipt. Filters remain
in the redirect, focus returns to an inline result above the cards, and reviewing
can continue. Removing the last item on a later page moves back one page.

Profile and Source are the main filters; More filters exposes the remaining
controls, disabled-source inclusion and dismissal recovery. Source details retains
coverage, verified-safe outbound links, prioritization explanations, bulk choices
and the full-evidence link. Bulk decisions and confirmed bulk dismissal stay
separate from single-story buttons. Single-action/earlier receipts are collapsed;
bulk confirmation remains a dedicated non-replayable receipt.

The visible mockup contains summaries, but this release does not store RSS excerpts.
No invented excerpts, new scraping, research or article-length controls were added.
The three visible buttons do not initiate writing, create posts or incur AI charges.

Articles has four sections with Waiting as the default:

| Section | Included work | Primary action |
| --- | --- | --- |
| Waiting | Unfinished commissions, writing results awaiting draft approval, uncertain jobs and missing/changed native links | Prepare, review written work, or review recovery |
| Drafts | Attributed native WordPress draft, pending and future posts | Open in WordPress |
| Published | Attributed native posts whose current status is publish | Open in WordPress; view article |
| History | Cancelled/held/paused commissions without active protected work; private, trashed and other non-active native states | Restore a safe cancelled commission, review decisions, or open WordPress |

Native titles/statuses are read live. An exact provenance marker identifies native
articles, and an exact stable work anchor is required to associate them with a
commission. A verified native article suppresses the corresponding commission row,
so completed work does not also appear in Waiting. Duplicate provenance metadata
does not duplicate a post. A broken anchor is not guessed or rewritten: its
commission stays available for recovery, while an independently attributable
native post remains WordPress-owned. No replacement post is inserted.

Section badges count all statuses; the result total and pages honor the selected
section/status. The interface states that distinction. Rows are bounded to 20,
with a stable ordering and bounded post-cache priming. The workspace uses three
SQL reads plus at most one post-cache priming query, with indexed provenance
predicates plus byte-exact guards. Very large native metadata/history tables may
still warrant production query profiling; no performance guarantee is made.
Legacy commissions/posts query APIs remain for compatibility. Native rows require
native read permissions; restricted administrators can still review commission
work without gaining access to native posts. Unavailable native sections are not
reported as zero native articles.

On narrow viewports, article rows stack with visible field labels so the next
action is available without horizontal scrolling. Expandable evidence/history,
filters, diagnostics and safe recovery remain accessible. No Sources or Editorial
Profiles redesign was implemented.

## Design grounding and review boundary

The two images actually visible in this conversation were Newsroom and Commission
Article. Additional replies said that the missing images were attached, but arrived
as text only: no Articles/Sources/Editorial Profiles image or accessible link reached
the workspace. This was reported and a link requested; the administrator explicitly
instructed continued development. Newsroom follows the visible reference. Articles
uses its existing native WordPress table conventions and the explicit four-section
requirements. **An exact visual comparison with the approved Articles mockup is
still unverified.** Review the supplied screenshots against that original before
considering visual sign-off. No unseen visual details are claimed to be reproduced.
No planned commissioning notes, research or length features were implemented.

## Safeguards and storage

Schema remains 6. There is no schema migration or data backfill. Discovery data,
source categories, editorial decisions, commission IDs, attribution and writing
journals remain stored. This change never writes native posts. WordPress owns
finished content. Cancellation/restoration still use the existing bounded preview
and explicit confirmation, and protected writing/recovery records cannot be
cancelled through either path.

OpenAI transport, model, budgets, reservations, writing rules and request/draft
approval services are unchanged. Preparing and giving per-request paid consent
remain separate from approving native draft creation. No paid API request, image
service, live content publication, retry of commission #4 or live-site change was
made. Integration fixtures and mocked publishing/status changes run only in an
isolated disposable WordPress database with external networking blocked.

## Staging installation and upgrade

1. Back up the staging database and installed plugin files; keep the previous ZIP.
2. Confirm this is staging. Upload `editorial-scout-0.9.0-rc1.zip` through Plugins →
   Add New → Upload Plugin and replace the installed version. The archive root is
   `editorial-scout/`; it contains only the runtime allowlist, not tests or dependencies.
3. Open Newsroom. Check the three story buttons without opening Source details.
   Filter a source/profile, hold a story, and confirm only that story changes and
   filters remain. Review Source details and full evidence for a multi-source story.
4. Try bulk decisions and confirmed bulk dismissal on disposable stories. Recover
   them through More filters → Not interested. Verify disabled-source filtering
   still retains mixed active/disabled coverage and preserves historical evidence.
5. Open Articles: Waiting is selected by default. Inspect an existing failed or
   uncertain job without writing/retrying it. Technical details and journals remain
   accessible through the existing writing screen.
6. On separately created staging fixtures, change a native title/status in WordPress.
   Verify Drafts/Published/History reflect the current state and the same article
   does not also appear in Waiting. Private/trash are retained in History. Review
   cancelled commissions and restore only safe work through explicit confirmation.
7. Check keyboard navigation and 375/768/1280px layouts. No AI request is needed for
   any of these tests. Do not use commission #4 as a retry test or publish live work.

Rollback: replace plugin files with the previous ZIP; **do not uninstall** or reset
journals. Schema is unchanged. Keep newer native content and editorial decisions;
do not restore an old database over later work. Old software uses its earlier
views and will ignore the new section/inline URL parameters. Abandon old open
forms after changing versions and reload/review current state before any action.

## Verification

The implementation preserves the established tests, updating UI selectors where
labels/navigation intentionally changed. New tests exercise the production section
queries and actual direct-action endpoint, not a separate classifier. Browser
coverage includes filtered direct/bulk decisions, all four sections, native title/
status changes, cancellation/restoration, no-JavaScript forms, responsive layouts,
keyboard focus and scoped axe A/AA checks. Automated checks are not certification
of all WordPress themes, admin extensions, factual correctness or Directory approval.

Final test evidence, Plugin Check findings, ZIP checksum and GitHub delivery status
are recorded in the accompanying implementation report under artifacts.

Local runtime ZIP: **1204 integration assertions** passed on WordPress 7.1.3 /
PHP 8.3.35. **209 Chromium checks** passed with no browser JavaScript errors,
including all four populated sections at 375/768/1280px and scoped axe A/AA checks.
WordPress coding standards and PHP/JavaScript syntax checks passed. Established
permission, consent, journal recovery, content retention and source-security
assertions still run.

ZIP: `editorial-scout-0.9.0-rc1.zip`, 25 runtime files, 375555 bytes. SHA-256:
`d8e7e00e1786db813bff5e525b92c144eaef645c92416d7a344b0bbb600eaa07`.
Two builds compared byte-for-byte, and all archive contents were compared with
the current runtime files. Screenshots are named `milestone8a-SECTION-WIDTH.png`
under artifacts; available sections include newsroom, waiting, drafts, published
and history. Only synthetic test content appears.

Plugin Check on the runtime package: **0 errors, 210 warnings**. The prior release
had 201 warnings. The nine additional findings are the three fresh workspace SQL
reads flagged for direct access, no caching and interpolated-parameter analysis.
The union is built only from fixed SQL and prepared identifiers/literals; section,
status and offsets remain bound parameters. The fragments were reviewed and
malicious/nested inputs are covered by tests. These findings remain visible for
Directory review rather than being hidden. Caching these reads without native
post invalidation would defeat live state/count behavior. Existing warnings cover
other direct SQL/caching, one flagged input wrapper, AI direct integration, schema
management, meta queries and post__not_in. No Directory approval is claimed.

Concurrent native edits can occur between the separate count/list reads; refresh
if another editor changes status while the page is loading. Large installations
should profile the read queries before broad rollout.

Final wording refinement: the Newsroom explains that commissioning only adds work
to Waiting. Inline outcomes identify holding, dismissal/recovery or commissioning;
the latter explicitly confirms that no AI request or native draft was created.
