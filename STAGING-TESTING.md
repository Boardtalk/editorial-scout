# 0.6.0-rc1 staging upgrade, testing and rollback

Use isolated single-site WordPress 6.5+, PHP 8.0+ with cURL/DOM/mbstring and InnoDB.
Prefer supported releases. No paid account or secret is needed for local features or
the automated mock suite. Never test destructive uninstall against valuable data.

## Upgrade from 0.5.0-rc1

1. Back up database and plugin directory together. Deactivate Editorial Scout. Leave
   your correctly functioning WordPress cron runner unchanged, regardless of host.
2. Build `python3 tools/build-staging.py`. In `artifacts/`, run
   `sha256sum -c editorial-scout-0.6.0-rc1.zip.sha256`.
3. Upload/replace the plugin ZIP, or extract its `editorial-scout/` directory into
   plugins. Use directories 0755/files 0644. The ZIP contains 19 allowlisted runtime
   files, no fixtures, vendor dependencies or credentials. Activate. If replacing files
   manually, use your host's supported PHP opcode-cache reset/restart procedure;
   deterministic ZIP timestamps are fixed. The native WordPress upgrader handles
   normal upgrade cache invalidation.
4. Schema 3 is unchanged; no table migration or post/taxonomy rewrite occurs. AI
   settings and one monthly aggregate usage option are non-autoloaded and initialize
   on use. One per-administrator consent preview retains only IDs/timestamps/digest,
   never excerpts. It expires after an hour and is removed on access/disconnection.
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
   Review the included native category structure too: category exclusions remove
   sampled posts/descendants, but all native category IDs/names/parents are shown
   for context and will be sent. Do not confirm if that structure is sensitive.
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

For rollback, Disconnect first, deactivate, remove the server secret if no longer
needed, and restore matching 0.5.0 files/database backups for a complete rollback.
File-only downgrade to 0.5.0 understands schema 3 and retains all existing editorial
data; it ignores new AI options/preview metadata and cannot call this adapter. Preserve
usage data/backups if upgrading again in the same budget period. Restoring an older
usage database can reset reservations: check actual provider spend before re-enabling.
No destructive migration needs reversing. Default uninstall retains data; explicit
EDITORIAL_SCOUT_DELETE_DATA removes plugin-owned tables/options/user metadata while
retaining administrator-approved native categories and the independently configured
server secret. Remove that secret manually. Never restore a whole database over new
valuable content without the website owner's coordinated backup/rollback plan.
