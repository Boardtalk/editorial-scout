# Editorial Scout 0.8.2-rc1 — article preparation patch

Upgrade from 0.8.1-rc1: back up files/database, deactivate, verify
`artifacts/editorial-scout-0.8.2-rc1.zip.sha256`, install the corresponding ZIP and
reactivate. Schema remains 6; no journal or commission conversion is needed.
Keep existing cron configuration. Do not clear journals to recover a form.

Preparation is local and never calls OpenAI. After a failed attempt, expect one
article-specific explanation and restored safe selections/notes. Review the form
and check source permission again; it is never retained as acknowledgement.
A new successfully prepared preview requires its own separate paid consent.
Correction entries are scoped to one administrator/commission and available for
one hour, subject to cache eviction and source changes. WordPress may remove
expired physical cache/option data later. Existing drafts/posts remain untouched.

See [reproduction, implementation, manual tests and rollback](docs/ARTICLE-PREPARATION-FIX.md).
Rollback to backed-up 0.8.1-rc1 files uses the same schema; do not overwrite newer
native content with an older database. The previous housekeeping guide follows.

# Editorial Scout 0.8.1-rc1 — housekeeping staging guide

Use a disposable, backed-up **single-site WordPress 6.5+** installation with PHP
8.0+ and cURL/DOM/mbstring. Prefer supported versions. Plugin tables and native
options/usermeta must use InnoDB for confirmed batches. Never run fixture scripts
against a valuable staging or production database.

## Upgrade from 0.8.0-rc1

1. Back up plugin files and database together. Deactivate Editorial Scout; keep
   your existing, hosting-independent WordPress cron runner unchanged.
2. In `artifacts/`, verify `sha256sum -c editorial-scout-0.8.1-rc1.zip.sha256`.
3. Upload the ZIP through Plugins → Add New → Upload Plugin, replace the existing
   plugin and activate. Its root is `editorial-scout/`; it contains 25 allowlisted
   runtime files and no vendor, tests, fixture credentials or dependencies.
   Manual file replacement may require your host's supported opcode-cache reset
   because ZIP entry timestamps are fixed for reproducibility.
4. **Schema remains 6. No data conversion or deletion is required.** Existing
   evidence, categories, editorial choices, commissions, writing journals, drafts,
   budgets, provider controls and schedules remain intact. Do not reset schema
   markers, remove journals or enable opt-in data deletion to tidy the Newsroom.

## Manual review checklist — no AI calls needed

- Add two test feeds, for example `https://wordpress.org/news/feed/` and
  `https://wordpress.org/news/category/releases/feed/`. Real overlap and current
  feed contents vary; deterministic multi-source coverage is tested in fixtures.
  Check Now uses existing safe network controls and cooldowns.
- Disable a source. New Stories hides its exclusively supported stories; a story
  also supported by an enabled, non-archived source remains visible. Enable
  **Include stories from disabled sources** to review hidden New Stories. This is
  a per-view filter retained in pagination, not a change to source activation.
  Re-enable the source: visibility returns unless you explicitly dismissed stories.
- Filter by source/category/profile and check counters and pagination. Totals
  honor these filters across editorial states. Existing held, commissioned and
  dismissed history stays accessible even when every source is disabled.
- Select two uncommissioned offers → **Preview selected dismissals**. Check titles,
  ready/blocked counts, then explicitly confirm. Use **Recover dismissed stories**
  and choose Hold for Later or Commission Article to recover a story.
- Open **Newsroom housekeeping** → **Preview disabled-source cleanup**. The total
  is the number eligible when previewed; each batch is at most 20. Only New
  Stories without an active supporting source qualify. Held/dismissed stories and
  every commission are excluded. Confirm, check changed/skipped counts and preview
  again for the next batch. Re-enabling a source or changing a decision between
  preview and confirmation must block the stale story.
- In Articles, cancel an unstarted commission, confirm its preview, then restore
  it through another preview. Identity, profile and source evidence remain intact.
  There is no cancellation for **any** existing writing journal, including a
  prepared request, failure, uncertain operation, saved result or linked post.
  Follow the existing writing/recovery workflow; housekeeping cannot clear it.
- Repeat a confirmation, submit an old preview, open two previews, and try a
  non-administrator account: no unauthorized or repeated changes should occur.
  Forms work without JavaScript. Check keyboard navigation, confirmation labels
  and 375/768/1280px layouts. Review existing native drafts/posts in WordPress;
  none should be changed, deleted, recreated or published by housekeeping.

## Recovery and rollback

Previews expire after one hour; opening a new housekeeping preview replaces your
previous one. No changes occur until confirmation. A processing/interrupted
request cannot replay: inspect current states and start a fresh preview. Completed
receipts show actual changed/skipped counts. There is no blanket undo that can
silently overwrite another editor's later decision; use the recovery filters and
commission restoration after reviewing current state.

To roll back, deactivate and replace plugin files with backed-up **0.8.0-rc1**,
then reactivate. Both use schema 6 and the same editorial/commission states, so
new housekeeping decisions remain readable. Older releases lack the new central
journal protection: avoid older editorial status controls on saved writing.
Never restore an older database over newer native content just to roll back this
code. If a database restore is unavoidable, coordinate a matching full-site backup
with your administrator. Default uninstall retains data; do not set
`EDITORIAL_SCOUT_DELETE_DATA` for rollback. Native posts/media remain WordPress-owned.

## Reproducing automated checks

Build with `python3 tools/build-staging.py`; build again into another directory and
compare ZIP bytes. Extract the ZIP to a readable temporary directory and set
`SCOUT_PLUGIN_ROOT` to its `editorial-scout` directory before `./tests/run.sh`.
Use `SCOUT_KEEP_TESTS=1` for `node tests/browser/browser.cjs`, then remove only the
`editorial-scout-tests` Docker services/volumes. Suites use disposable native posts,
local DNS/TLS feeds and mocked AI responses; no paid provider calls are needed.
See [implementation report](docs/MILESTONE-7A1.md) for results and limitations.
