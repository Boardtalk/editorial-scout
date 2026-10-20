# 0.7.1-rc1 staging upgrade, testing and rollback

Use isolated single-site WordPress 6.5+, PHP 8.0+ with cURL/DOM/mbstring and InnoDB.
Prefer supported releases. No paid account or secret is needed for local features or
the automated mock suite. Never test destructive uninstall against valuable data.

## Upgrade from 0.7.0-rc1

1. Back up database and plugin directory together. Deactivate Editorial Scout. Leave
   your correctly functioning WordPress cron runner unchanged, regardless of host.
2. Build `python3 tools/build-staging.py`. In `artifacts/`, run
   `sha256sum -c editorial-scout-0.7.1-rc1.zip.sha256`.
3. Upload/replace the plugin ZIP, or extract its `editorial-scout/` directory into
   plugins. Use directories 0755/files 0644. The ZIP contains 22 allowlisted runtime
   files, no fixtures, vendor dependencies or credentials. Activate. If replacing files
   manually, use your host's supported PHP opcode-cache reset/restart procedure;
   deterministic ZIP timestamps are fixed. The native WordPress upgrader handles
   normal upgrade cache invalidation.
4. Migration from schema 4 to 5 adds profile_id/profile_name to commissions. Older installations also receive the existing commissions/revision migration.
   No existing choices, observations, profiles, consent/budget settings or cron state
   are rewritten. Migration verifies columns/indexes/engines and can restart safely;
   if it fails, check database permissions/InnoDB, reload an administrator page and
   retry. Both WordPress options and usermeta must be InnoDB for Newsroom batches.
   The plugin will not alter native table engines automatically. Do not bypass errors
   by editing the schema marker or dropping decision/commission tables.
5. Confirm prior sources, coverage, categories, profiles, shortlist/dismiss choices,
   schedule and cron health. Check Now/Check All must behave as before. Test feed:
   `https://wordpress.org/news/feed/` (a manual staging network request to WordPress.org).
   Weekly monitoring still cannot guarantee feed coverage.

## Local workflow (no external calls)

Open Editorial Profiles. Create/edit profiles with existing native categories.
Run **Local keyword analysis**; Advanced sample controls contains UTC dates, sample
ceiling 1–32 and category/descendant exclusions. Sparse windows now fill from the
approved range. Evidence must display permitted article titles and edit links.
Private, draft, password-protected posts, pages and excluded descendants must be absent.
Review/edit a proposal and Save reviewed profile; existing profiles remain intact.
Change evidence to private or edit it: the inactive proposal should invalidate.
Discovery relevance still uses local title phrases, not the OpenAI website adapter.

## Optional provider configuration (website administrator only)

No key-entry field exists. Provision a restricted project key in a protected server
secret/environment facility. In wp-config.php, before WordPress loads, reference it:

```php
$scout_secret = getenv( 'EDITORIAL_SCOUT_OPENAI_API_KEY' );
if ( is_string( $scout_secret ) && '' !== $scout_secret ) {
    define( 'EDITORIAL_SCOUT_OPENAI_API_KEY', $scout_secret );
}
unset( $scout_secret );
```

Use the host's supported secret facility; no panel API or filesystem path is assumed.
The web PHP process must receive the variable. A literal wp-config.php secret is also
supported but then file backups contain it: restrict file/access/backup permissions.
Environment variables and constants are not encrypted vaults; privileged server code,
other plugins, process diagnostics or misconfigured HTTP-debug tooling can read them.
Do not enable request-header/body logging. Database exports contain no plugin-stored
key. Never paste keys into profile fields, screenshots, tickets or Git. This release
accepts ASCII letters/digits/underscore/hyphen, 40–256 characters; future credential
formats may require an adapter update. No organization/project header configuration
or arbitrary API endpoint/model selection is supported.

Open **Provider connection and budget settings**. Select Enable, review daily/monthly
reserved-token limits, current provider pricing and dated assumptions, then Save.
Defaults USD 0.40/1.60 per million input/output tokens dated 2025-04-14 are historical
assumptions, not current quotes. Verify them with OpenAI before enabling. Fixed pinned
model: `gpt-4.1-mini-2025-04-14`; model/account availability is not tested live here.
API reference: https://developers.openai.com/api/reference/resources/responses/methods/create
Model: https://developers.openai.com/api/docs/models/gpt-4.1-mini
Pricing: https://openai.com/api/pricing/

**A connection test is billable** and requires a separate checkbox. It sends only
`Connection test; no website content.` and requests up to 16 output tokens. Its budget
reservation is deliberately conservative (same 4,096-token output allowance), not
an exact bill. No Codex-run live connection test has been performed.

Disconnect disables execution and clears all administrators' unconfirmed previews.
Remove/revoke the server secret at OpenAI for full credential revocation. Disconnecting
does not revoke the provider key. It preserves usage reservations to prevent resets
from bypassing budget ceilings.

## Explicit semantic analysis and privacy review

1. Run local learning to choose the eligible sample, using category/date controls.
   Click **Preview sample for AI analysis**. Previewing performs no network request.
2. Inspect each title and expand each excerpt. Uncheck individual unsuitable posts.
   Review the included native category structure too. Category exclusions remove
   sampled posts and category names/IDs/parents, including descendants, from the
   transmission. Adjust exclusions and rerun local analysis to change that context.
3. Transmitted fields are selected public post IDs, titles (400 characters), UTC
   dates, category IDs and plain excerpts (600 characters); plus up to 200 native
   categories (ID, name up to 80 characters, parent). No author identities, URLs,
   full bodies, drafts/private/password posts, pages or feed articles are included.
   Public text/category names can contain personal information. No automatic PII
   scrubber is claimed. Inspect and exclude sensitive samples before consenting.
4. Read the conservative input/output allowance, approximate USD cost and assumption
   date. Affirm consent for this one billable analysis, then submit. Unchecked posts
   lower the reservation. A changed/expired sample requires a fresh preview/consent.
   A failed attempt also consumes the one-use confirmation; there is no silent retry.
5. OpenAI receives the material via server HTTPS, plus your server IP and a generic
   plugin user agent (no site URL in that agent). `store:false` is requested but
   does not guarantee zero retention; account/abuse-monitoring policies apply.
   Privacy: https://openai.com/policies/privacy-policy/
   Terms: https://openai.com/policies/terms-of-use/
6. Review semantic subjects/sections and claim evidence links. Audience/geography
   should be empty if unsupported. Model citations prove only permitted sample IDs,
   not factual truth. Edit every inferred field before explicitly saving a **new**
   profile. Sections are also included in proposed Editorial instructions for storage.
   Native categories are suggestions only; the AI action creates/changes no taxonomy.

Daily/monthly ceilings use conservative reserved tokens, not exact spend. Each request
reserves serialized payload bytes + 8,000 input-overhead tokens + 4,096 output tokens
before HTTP. Request/output sizes are separately bounded. Reservations survive
failures, HTTP timeouts, interruptions and disconnection; no automatic refund/retry.
UTC day/month rollover resets the relevant aggregates. Provider-reported successful
response tokens are shown separately; they are not a reconciled billing ledger.
Set provider-side budgets/rate limits and separate this key from other software.
Billing may differ and no guaranteed zero overrun is claimed. There is a one-minute
server cooldown and the existing common concurrency lease. Network errors expose
fixed friendly messages, never raw provider text. WP HTTP proxies fail closed.

## Staging checks and rollback

Automated tests/CI use fake credentials/responses inside disposable WordPress, never
paid requests. If you choose a live staging test, treat it as your explicit billable
request; manually assess the realistic gardening/cooking/machinery sample and privacy
preview. That live evaluation has **not** been performed by Codex. Test keyboard
navigation and a screen reader on your actual admin theme; axe is partial coverage.
Verify no posts, decisions, categories or cron settings change as a result of AI.
Check safe failures for disabled connection, missing consent, cooldown and low ceilings.

For rollback, disconnect optional AI, deactivate, and restore matching pre-upgrade
0.7.0 plugin files **and database backup**. Schema 5 is additive but 0.7.0 correctly
refuses a future schema, including its UI/cron; file-only downgrade is not an
operational rollback. Never set the schema marker to 4 (or earlier) manually. Preserve/export
any new commissions/decisions and coordinate recovery of other WordPress content
created since the backup before restoring a whole database. There is no automatic
lossless downgrade. Check actual provider spend before re-enabling AI if restoring
an older usage ledger, and remove the server secret if it is no longer needed.
Default uninstall retains data; explicit
EDITORIAL_SCOUT_DELETE_DATA removes plugin-owned tables/options/user metadata while
retaining all native posts (including drafts), attachments/files, taxonomies and post metadata, plus the independently configured
server secret. Remove that secret manually. Never restore a whole database over new
valuable content without the website owner's coordinated backup/rollback plan.


## The Newsroom: staging workflow

- Confirm Newsroom is the default landing tab. All five previous tabs must still
  work. Counts are global story totals, not counts restricted by the current filter.
  No draft/article/author/category totals are invented.
- With a real public RSS feed, open cards inline. Check titles, supplied/unknown
  dates, real source links and explicit heuristic reasoning. Expect no summaries
  because this release does not store feed excerpts. Never infer verification from
  feed count. Repeats on one feed count as observations, not additional feeds.
- Use a saved profile containing your own relevant/excluded phrases, then compare
  Recency only and Only clear title matches. This is literal title matching, not
  the opt-in OpenAI website analysis. Future/old dates receive no recency priority.
- Choose Commission, Hold and Not Interested on different cards. Leave another
  choice blank, submit once, and check compact outcome counts at the action anchor; expand Detailed submission receipt to inspect individual outcomes.
  No WordPress post should appear. Source/cron/AI settings and budgets must not change.
- Filter by Held, Commissioned and Not Interested to recover choices. Hold/dismiss
  an existing commission and recommission it: its durable ID must remain the same.
  In Discoveries, New pauses a historical commission while retaining its evidence;
  New releases ordinary protection only for never-commissioned stories.
- Open a second tab to replace the first form, then submit the first: stale form
  guidance must appear. Have another administrator change one visible story, then
  submit an older view: that item must be skipped, while other valid items can save.
  Replaying a POST must not duplicate a commission. Forms expire after one
  hour; the latest detailed receipt persists until replaced or explicitly cleaned up; use a fresh view and review current state after interruption, never assume
  an unconfirmed batch succeeded or failed.
- Verify ten-offer pagination and source/category/state/profile controls, long titles,
  missing dates, keyboard-only expansion and choices, receipt focus and narrow screens.
  Preferred/excluded native categories should use expandable checkboxes with selected
  counts. Without JavaScript, forms, links, initial counts and decisions still work.
- Existing limits remain: 1,000 retained decisions/commissions, 10,000 protected
  observations, 30 days/5,000 ordinary observations. Historical commission rows do
  not expire or disappear on dismissal; releasing that capacity needs a future
  explicit archive/export workflow. Do not test quota/reset SQL on valuable data.

## Articles and permanent ownership checks

- Successful submission should show **Decisions submitted**, five accurate counts,
  a statement that no WordPress article/draft was created, and View Commissions /
  Continue Reviewing links. The detailed receipt starts collapsed. Keyboard focus
  lands at the confirmation. Refresh must not display a repeat-submit button.
  Continue Reviewing creates a new neutral-choice form. Latest receipt is per user,
  not a permanent multi-entry ledger; a later submission replaces it.
- Open Articles. Check titles, created UTC dates, evidence links, current statuses
  and profile-at-first-commissioning snapshots. Old commissions say Not recorded.
  Profile renames/deletion do not rewrite history. Filter Waiting, Held, Cancelled
  and Paused. Hold/dismiss/recommission should update status without replacing ID.
  There is no writing engine or writing-failure queue yet.
- WordPress articles is a separate, read-only native-content view. With no exact
  provenance-marked posts it must be empty, even if commissions or unrelated
  WordPress articles exist. Do not add provenance markers to real manual posts as
  a staging shortcut. Disposable automated fixtures cover all native statuses,
  exact key/value cases, pagination and read/edit permissions.
- In a **disposable** copy with fixture native posts/media, exercise deactivation,
  reactivation, default uninstall/reinstall and explicit operational cleanup.
  Bodies, excerpts, statuses, authors, taxonomies, thumbnails, files and post meta
  must survive. Production cleanup must never delete them. Reinstall can still
  find exact native provenance after its operational history was removed.
- Test keyboard navigation, narrow screens and screen readers in your admin theme.
  Large tables scroll within labelled regions. No action creates article content.

See docs/MILESTONE-6C.md for results and limitations and docs/ARTICLE-OWNERSHIP.md
for the required future native-writing/linking contract. Earlier implementation
and transaction details remain in docs/MILESTONE-6B.md.
The future resumable first-run wizard must support both established sites and new
sites without published posts; it is documented but not implemented in this release.
