# Milestone 7A — first administrator-controlled AI article draft

Base: `feature/editorial-workflow` at
`cb40cb949fc523320c691ef15de758187186df5e`.
Branch: `feature/ai-article-drafting`. Release: **0.8.0-rc1**, GPL-3.0-only.
No paid API requests, production credentials, live-site changes, deployment,
publication or merges. Native posts generated in validation are disposable local
fixtures created with mocked provider responses.

## Delivered workflow

Articles → **Prepare article draft** opens original discovery/source evidence,
profile selection (saved commission profile where still available), existing native
category and permitted author choices. Up to four source observations are eligible.
The administrator supplies 40–600 characters of original/permitted material per
source, attests usage permission, then prepares a frozen preview. No provider call
occurs during preparation. A matching headline alone/empty/short material is rejected
locally; unsafe source URLs and pasted configured credentials are rejected.

Human-readable sources and profile fields lead the preview. Exact JSON, system
writing rules and token assumptions are expandable. The page shows the estimated
USD allowance before the separate **Write draft** consent. It sends the displayed
story, complete selected profile snapshot, selected source names/URLs/material and
chosen category. It does not send author identity, existing article bodies, private
posts or media. Updating the lower source/choice form requires preparing a fresh
preview; the existing Write action uses only its saved snapshot.

The result remains a **proposal**, marked Needs fact-checking, with source IDs/links
and supported / independently verify / opinion / unavailable claim notes outside
article content. Research incomplete never has a create action. Successful generation
alone creates no WordPress post. A second explicit approval creates one native draft.
It uses standard title, escaped paragraphs/headings, excerpt, native author/category
and durable namespaced provenance. Suggested tags remain notes; there are no image,
Yoast, scheduling, pending-review or publication actions.

The native draft appears in Articles and ordinary WordPress Posts. Subsequent
creation requests find its link and never rewrite native content. WordPress status
is read live. Articles filters/counts distinguish waiting, ready, incomplete,
failed, sending, recovery-needed and linked work; linked drafts are no longer counted
as waiting. Held/cancelled/paused decisions and evidence remain intact. Creation is
paused unless the commission is waiting again. Old source/profile/cron/privacy
functionality and native read-only attribution remain.

## Research boundaries and factual reliability

This release deliberately **does not crawl article pages**. Arbitrary feed links do
not establish permission, robots policy, copyright or access rights. There is no
paywall/authentication bypass or new arbitrary-URL fetcher. Existing feed monitoring
retains its HTTPS/DNS/cURL pinning/no-proxy/no-redirect safeguards. Permitted material
is supplied by the administrator after their own lawful research, bounded to short
notes/excerpts. A source link is not factual proof; multiple feeds may repeat the
same story. No independent cross-source fact-checking is claimed.

The fixed prompt treats payload/source instructions as untrusted data, forbids tools,
commands, invented quotations/statistics/dates/references, copied wording and
unsupported certainty, and requires research incomplete when evidence is inadequate.
The response uses a strict JSON schema and local type/key/count/length validation.
Article output is plain text only; HTML, shortcodes, URLs and direct double-quoted
quotations are rejected. References must use supplied IDs; supported claims require
a reference and ready output also needs a verification/unavailable note. New numeric
strings absent from supplied notes and verbatim twelve-word source sequences are
rejected. Only server-created paragraph/heading markup enters native content.

These screens **cannot prove truth, semantic relevance, claim support, completeness,
source independence, permission or originality**. Numeric matches can still be
misleading; shorter/paraphrased copying and invented wording can evade the screens.
The human must inspect everything. Structured model quality and actual billable
behaviour have not been evaluated with live requests. A model can misjudge evidence;
this is a drafting aid, not autonomous research or security certification.

## Provider, budgets and privacy

The existing restricted Responses endpoint/model, server-configured credential,
25-second timeout, 64 KiB response cap, TLS, no redirects/proxy/tools, strict output
validation, global SQL lease, one-minute cooldown and conservative daily/monthly
reserved-token ledger are reused. Writing and website-learning limits share the same
ledger. No new secret storage, paid SDK or automatic caller is added.

Before HTTP, the journal records sending, then a second **durable dispatch claim**.
The provider bridge accepts only an owner-bound, consumed approved journal request,
not arbitrary caller-provided material. Tokens/forms expire after one hour for
writing; changing source coverage, decision revision or profile invalidates consent.
Repeated requests cannot dispatch the same journal token again. An interrupted
sending operation is never automatically retried. Failed/invalid/budget/cooldown
outcomes require a deliberately prepared fresh request if another paid attempt is
wanted. An uncertain result-save failure may remain sending and require manual help.

Reservations use the inherited one-token-per-JSON-byte estimate plus 8,000 overhead
and 4,096 output tokens. Existing dated prices are assumptions (default USD 0.40 input /
1.60 output per million, dated 2025-04-14), not verified current rates or a billing cap.
Failures/interruptions keep reservations; successful reported usage is separate from
actual provider billing. Use provider-side limits and review spend before restoring
older ledgers. No automatic refund/retry or provider-result polling is implemented.

`store:false` is requested, not a zero-retention promise. Public material can contain
personal data. Permission and per-request disclosure are distinct approvals; exclude
sensitive data before consent. Operational payloads/results/notes are plain database
workflow data retained by default, bounded per commission, not encrypted vaults.
The configured key is never included in payloads, journal data, notices or logs.
Exceptions/provider failures become fixed codes, never raw provider text.

## Native ownership and recovery

See [ARTICLE-OWNERSHIP.md](ARTICLE-OWNERSHIP.md). Schema **6** adds only the InnoDB
writing journal: commission primary key, unique work UUID, unique nullable native
post ID, bounded JSON context and state. Migration verifies columns, nullable IDs,
indexes/uniqueness/engines before advancing and can restart after failure. Existing
commissions, dates, choices/revisions, sources, profiles, schedules and settings are
not rewritten. The future-schema downgrade guard remains.

A saved validated result precedes creation. Persist creating **before** native APIs;
initial native row insertion includes the UUID slug and internal filtered-content
anchor in the same row. A scoped final data filter rechecks lease/current commission
and forces native post/draft/author identifiers. Recovery requires exactly one
original anchor, native edit permission and non-conflicting provenance; it adds only
missing plugin metadata and records the unique post link. It never reinserts after
intent, changes native content, deletes ambiguous candidates or relabels unrelated
manual posts. Journal failures before intent leave a ready proposal; failures after
row insertion/linking preserve the row for reconciliation. Concurrent attempts and
post-insert hook interruptions are regression-tested.

Zero/multiple anchors, edited recovery slug/marker, deleted posts, conflicting
metadata, unknown database outcomes and abandoned sending states stop for manual
review. This can require help even after a pre-insert failure that created no post;
fail-closed safety takes priority over automatic recovery. The saved proposal remains
reviewable. Other plugins/hooks/database topology are trusted and need actual staging
tests; there is no blanket exactly-once guarantee across arbitrary third-party code,
manual journal deletion or mismatched restores.

Default uninstall retains workflow. Explicit opt-in drops plugin operational tables
including writing, never native posts/drafts/revisions, attachment records/files,
assigned terms or ordinary/provenance post meta. Operational cleanup deliberately
loses prior journal/idempotency history; it is not a retry mechanism. Reinstall can
still list retained exact native provenance. Matching **database + plugin backups**
are required to roll back to 0.7.1, which refuses schema 6. Never lower markers by hand
or overwrite newer WordPress content with an old database without coordinated recovery.

## Validation evidence

- **754 integration assertions passed** against the extracted current ZIP on
  WordPress **7.1.3 / PHP 8.3.35 / MariaDB 11**. Earlier discovery/security, cron,
  intelligence, OpenAI, Newsroom and ownership suites are preserved.
- WordPress Core/Extra coding standards passed on **20 production PHP files**.
  PHP, JavaScript, shell syntax and whitespace checks passed.
- The ZIP contains **24 runtime-only files**, **322,915 bytes**, under
  `editorial-scout/`. All entries match source; fixture credentials and dependencies
  are absent. A separate build is byte-identical. SHA-256:
  `5c32aa26d084124522cea5e383abb33740743b1d9df8752b2422e9b435dabb4b`.
- The final-package browser run passed **133 checks**, including actual mocked
  writing/consent/create/replay and ordinary WordPress Posts, zero detected scoped
  axe WCAG 2/2.1 A/AA violations/overflow at 375/768/1280px and no page errors.
  Browser output is saved in ignored `artifacts/milestone7a-browser.log`.
- CI retains PHP **8.0 / 8.2 / 8.3 / 8.4** syntax, standards, reproducible packaging,
  minimum **WordPress 6.5 / PHP 8.1** and current WordPress **PHP 8.2 / 8.3**
  integrations, with the full browser suite on PHP 8.3. Final run/commit evidence
  will be linked in the prepared PR and ignored artifact validation receipt. Official **Plugin Check 2.1.0** completed against these same runtime bytes:
**0 errors / 186 warnings**, no stderr. All warning categories were reviewed:

| Count | Warning | Assessment |
| ---: | --- | --- |
| 94 | DirectDatabaseQuery.DirectQuery | Prepared dedicated-table reads/writes, migrations, coordination and native anchor lookup. New journal/recovery access adds five findings; no raw request SQL fragments. |
| 82 | DirectDatabaseQuery.NoCaching | Deliberately fresh operational decisions, dispatch/creation ownership and recovery. Native article reads still use WordPress APIs/caches. |
| 1 | PluginCheck.Security.DirectDB.UnescapedDBParameter | Existing composed title-ranking query uses fixed SQL and individually prepared literal LIKE fragments; injection/quote/percent regressions pass. Analyzer does not follow the composition. |
| 2 | SlowDBQuery.slow_db_query_meta_key | Existing fixed-key/current-user receipt reads use native indexes. |
| 2 | SlowDBQuery.slow_db_query_meta_value | Bounded serialized receipt/manifest writes, not value searches; raw transaction writes avoid leaking uncommitted metadata to caches. |
| 1 | DirectDatabaseQuery.SchemaChange | Existing explicit opt-in operational-table cleanup only; native content/media preservation passes. |
| 1 | ValidatedSanitizedInput.InputNotSanitized | Existing scalar/absint posted-ID pipeline followed by native bounds/capability/nonce checks; analyzer cannot follow the pipeline. |
| 2 | WPQueryParams.PostNotIn_post__not_in | Existing bounded Learn sample exclusions (at most 32 IDs) with date bounds. |
| 1 | AIProvider.DirectIntegration | Existing direct adapter keeps WordPress 6.5 compatibility and privacy/transport/budgets; checker recommends considering the WordPress 7 AI Client. |

The only ten added warnings are five prepared operational/recovery access sites
flagged for direct queries and no caching. Full output is retained in ignored
`artifacts/milestone7a-plugin-check.txt`. Passing checks are not certification or
Directory approval. Core update-service TLS warnings are expected on the isolated
fixture network. Pinned GitHub actions may emit non-failing Node 20/forced Node 24
and ubuntu-latest/Ubuntu 26 migration annotations; runner maintenance is separate.

An earlier long-lived browser run emitted WordPress core React dependency errors
while navigating to native Posts. Repeated direct native loads passed. The harness
now finishes native view transitions before programmatic reload and verifies Posts
in a fresh fully loaded tab, with **all core/plugin page errors still fatal**. The
final run passed; long-lived sessions with actual admin plugins/themes still need
staging review. Browser testing also caught dynamic attribute whitespace in the new
templates: fixed exact field IDs/names now have an integration regression. Repeated
browser runs register/delete only their disposable generated fixture posts.

The suites run against the **current extracted runtime ZIP**.
Provider responses are mocked; feed transport uses isolated DNS/TLS fixtures. All
prior regression suites remain, including network/cron lock/security tests.

## Changed files

- `editorial-scout.php`, `readme.txt`: version, component loading, current capability
  and external-service/privacy descriptions; prior release changelogs preserved.
- `includes/class-editorial-scout-drafting.php`: bounded evidence/profile snapshot,
  dispatch journal, structured writing rules/validation and native creation/recovery.
- `includes/class-editorial-scout-drafting-screen.php`: plain-English preparation,
  consent/allowance preview, generated review, source notes and separate create action.
- `includes/class-editorial-scout-openai.php`: same private request engine and
  safeguards, fixed writing task/schema and journal-bound dispatch bridge.
- `includes/class-editorial-scout-store.php`, `uninstall.php`: verified additive
  journal migration/uniqueness and operational-only cleanup.
- `includes/class-editorial-scout-admin.php`, `class-editorial-scout-articles.php`:
  capability/nonce POST routing, anchored PRG, eligible work actions/live states,
  bounded journal joins and native links. Counts/page remain two operational queries.
- `includes/class-editorial-scout-dashboard.php`, `class-editorial-scout-newsroom-screen.php`:
  truthful current drafting descriptions; commissioning still creates no post.
- `assets/admin.js`, `assets/admin.css`: progressive focus and bounded accessible
  exact-material display; forms/actions work through ordinary server POST/GET.
- `tests/drafting.php`, `drafting-endpoint.php`, `drafting-fixture.php`: mocked
  provider, evidence/output security, permissions, budgets, stale/one-shot work,
  crash/failure/concurrency and native ownership tests.
- `tests/browser-drafting-setup.php`, `browser-setup.php`, `browser/browser.cjs`:
  isolated mock setup, source/consent/create flow and responsive/axe/native Posts checks.
- `tests/editorial-work.php`, `integration.php`: current schema/state expectations
  and lifecycle snapshots extended to actual plugin-written drafts and journal cleanup.
- `tools/build-staging.py`, `.github/workflows/ci.yml`: runtime allowlist and current
  package installation/reproducibility across the retained PHP/WordPress matrix.
- `STAGING-TESTING.md`, `docs/ARTICLE-OWNERSHIP.md`, this report: plain staging,
  ownership/reconciliation, permissions, limitations, privacy, costs and paired rollback.

## Remaining staging checks

Real provider quality/cost is untested by design. Screen readers, other admin themes,
persistent object caches, third-party insert hooks, replication and very large
postmeta datasets require site-specific staging review. Native user selection is
bounded to 50 plus self; native category catalog to 200. Four source notes are not
comprehensive research. Existing 1,000 decision/commission and 10,000 protected
observation limits remain; archive/export, automatic research, job takeover, queued
paid retries, images, SEO and publication are outside this release. No Directory
approval, security certification or guaranteed factual coverage is claimed.
