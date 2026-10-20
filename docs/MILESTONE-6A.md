# Milestone 6A: optional semantic website understanding

Base: `feature/editorial-intelligence`, commit `83bba02`. Development branch:
`feature/semantic-website-understanding`. Candidate: **0.6.0-rc1**.
GPL-3.0-only. No production/staging website was accessed or modified, no paid request
was made by Codex, and no plugin post, image request, deployment or merge occurs.

## Implementation

A separate OpenAI website-learning adapter keeps the legacy local relevance boundary
closed to external discovery assessment. It is not wired to cron, feeds or publishing.
The existing administrator/POST/nonce gate and SQL ownership lease cover every new
configure/disconnect/test/prepare/analyze mutation. Fresh option/user caches are read
under that lease. Native source/category/decision tables and schema 3 are unchanged.
Existing profiles remain unchanged until explicit review/save of a new proposal.

Only a server constant supplies credentials; no database secret option or browser
key field exists. Requests use fixed https://api.openai.com/v1/responses, pinned
`gpt-4.1-mini-2025-04-14`, HTTPS verification, zero redirects, 25 HTTP seconds,
75,000 request bytes and 65,536 response bytes. WordPress-configured HTTP proxies
fail closed. There are no tools, retries, image calls, arbitrary endpoints or
organization headers. `store:false` is requested. Authentication/errors/responses
are never logged by plugin code; only fixed status/error codes are stored/displayed. The configured key is also
rejected if it appears in sample material or structured provider output.
Third-party HTTP instrumentation and privileged server code remain outside that
secrecy boundary; disable unsafe debug logging. DNS resolution and third-party HTTP
hooks may exceed the transport deadline, as with the existing feed DNS limitation.

Public sample construction independently rechecks standard post type, publish status,
empty password and modification time. It reads SQL LEFT(title,400) and SUBSTRING
content up to 4,000 characters; plain excerpts stop at 600 without rendering filters.
Each public post contributes ID/title/date/category IDs/excerpt; up to 200 native
category IDs/names/parents supply structure. No authors, URLs, private/draft/password
posts, pages, feed articles or full bodies are included. Entire native category
structure is previewed, including excluded sampling categories; administrators must
not consent if those names are sensitive. No automatic personal-data scrubber is
claimed. Individual posts can be unchecked and sample categories/descendants excluded.

Preview user metadata contains only evidence IDs/timestamps, SHA-256 material digest,
one-hour creation time and random confirmation token. Material is reconstructed for
display and submission; post/category changes invalidate the digest. Consent binds to
the current user's preview and selected subset, is consumed before the request, and
must be granted anew after failure. Material is revalidated after the network wait.
One inactive editable draft replaces the user's earlier unsaved draft, never a saved
profile. Human decisions, sources and schedules are untouched.

The strict Responses JSON schema returns seven profile claims plus editorial sections,
with bounded text, uncertainty and approved evidence IDs, plus existing category IDs.
Independent validation rejects missing/extra fields, malformed shapes, invented IDs,
overlong text, unsupported categories and nonempty claims without evidence. Unknown
readership/geography are prompted to remain blank. Sections are also copied into the
editable proposed instructions so explicit save retains them. Model-selected categories
are suggestions only. No taxonomy creation or assignments occur in this action.
Evidence titles/edit links replace opaque post-number labels. Profile editing, provider
configuration and sample controls use native expandable details to reduce scrolling.
Local keyword analysis is clearly distinct and remains available on failure.

## Usage and spending controls

A monthly aggregate option holds current UTC day/month reservations, provider-reported
successful tokens, request count, fixed last status and last-request time. No content,
key, raw error, account, response ID or infrastructure identifiers enter it. Each
serialized payload byte is conservatively counted as one input token, plus 8,000 for
instructions/schema/framing and 4,096 maximum output tokens. Full serialized requests
are also checked against that input allowance. A fixed no-content connection test
requests only 16 output tokens but retains the same conservative reservation policy.

A durable reservation is written under the shared SQL lease before HTTP. Daily and
monthly reserved-token ceilings (configurable within bounded limits) and a 60-second
cooldown fail closed. No refunds are issued for network/model/storage failures or
crashes; a lease lost during HTTP cannot write a new proposal. This intentionally
under-utilizes a budget rather than replaying uncertain paid work. Period rollover
bounds storage; disconnection cannot reset usage. Provider-reported tokens are
informational, not a reconciled invoice. Cost uses editable USD/million assumptions
with a visible date, input allowance and output maximum before confirmation.
Historical defaults are **not current verified prices**. No guaranteed zero overrun,
exact tokenization, control of other clients, or enforcement of a provider invoice is
claimed. Restoring an older database can restore an older ledger; check provider spend
before reconnecting. Provider-side project budgets and a dedicated key are recommended.

## Official API contract review

Reviewed on 2026-10-20 against OpenAI's current official generated SDK definitions:

- https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_create_params.py
- https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_text_config_param.py
- https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_format_text_json_schema_config_param.py
- https://github.com/openai/openai-python/blob/main/src/openai/types/shared/chat_model.py

These confirm Responses `input`, `instructions`, `max_output_tokens`, `store:false`,
`text.format` json_schema/name/schema/strict and the pinned GPT-4.1 mini model ID.
The documentation website itself returned a network-proxy 403 in this environment;
linked official docs/pricing remain in the staging guide. No real account availability,
model output quality, latency, price, billing, provider retention or successful live
connection is claimed. Only paid, explicitly administrator-initiated staging evaluation
can establish those in the intended account. Tests never require real credentials.

## Validation evidence

Executed against the extracted runtime ZIP, WordPress **7.1.3 / PHP 8.3.35**:

- **585 integration assertions passed**, including the complete existing suite.
  New checks include sparse-sample filling, external default-off, dummy server
  credentials, consent/subset/replay/privacy, malformed output, safe exceptions,
  durable reservation failure, uncached budget refresh, SQL ownership loss,
  cooldown, UTC rollover, disconnect and all permission/nonce/method gates.
- **67 Chromium checks passed**, including native admin POSTs through the mocked
  semantic workflow, inactive proposal/review/save/disconnect, no key in HTML,
  and scoped axe WCAG 2/2.1 A/AA at 375/768/1280. All four existing tabs and expanded
  provider/privacy screens had zero detected violations and no page overflow.
- WordPress Core/Extra standards passed for all **15 production PHP files**.
  PHP syntax, JavaScript syntax and shell syntax passed. Fresh integration debug
  output contained no plugin PHP errors. Core update-service TLS warnings occur
  during browser/checker use on the intentionally isolated Docker network.
- Official **Plugin Check 2.1.0**: **0 errors / 139 warnings**, broken down below.
- Two independent ZIP builds matched byte for byte. All 19 entries match source,
  use `editorial-scout/` as root and exclude dependencies, tests and dummy keys.
  SHA-256: `a89c01897c6097738ae298f7a4d12c88d0eee70a86a8e065718307ebde36bf26`.

GitHub Actions retains PHP 8.0/8.2/8.3/8.4 lint, WordPress standards, deterministic
packaging, minimum WordPress 6.5/PHP 8.1 and current PHP 8.2/8.3 integration, with
Chromium on PHP 8.3. The final run/commit status is linked in the prepared PR text.
These checks are mocked for OpenAI and do not verify a paid account or real semantics.

### Every remaining Plugin Check warning category

| Count | Code | Assessment |
| ---: | --- | --- |
| 71 | WordPress.DB.DirectDatabaseQuery.DirectQuery | Deliberate prepared SQL for dedicated tables, atomic leases/migrations, fresh bounded evidence reads and uninstall. No new unprepared input. |
| 63 | WordPress.DB.DirectDatabaseQuery.NoCaching | Deliberate fresh coordination, ownership and public-evidence validation; cache invalidation tested under the lease. Generic long-lived caches would weaken these checks. |
| 1 | WordPress.DB.DirectDatabaseQuery.SchemaChange | Existing explicit opt-in uninstall drops only plugin-owned tables. Default uninstall retains data. |
| 1 | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Existing scalar-filter plus absint pipeline in posted_ids; downstream native-ID/count checks apply after capability/nonce gate. Static analysis does not follow the pipeline. |
| 2 | WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in | Sampling excludes at most 32 selected IDs, with indexed coarse date bounds and exact UTC/date/type/status/password constraints. Added sparse fill remains bounded to two queries; large archives still need profiling. |
| 1 | PluginCheck.CodeAnalysis.AIProvider.DirectIntegration | Direct OpenAI integration is intentional for WordPress 6.5 compatibility and explicit endpoint/consent/transport/budget control. The checker recommends evaluating the WordPress AI Client introduced in 7.0; adopting it later must preserve these controls and a compatible fallback. |

Warnings are documented decisions, not waived Directory acceptance. No security
certification, full accessibility conformance or WordPress.org approval is claimed.

## Limits and follow-up

Prompt instructions and structured output do not solve prompt injection or prove the
semantic claims. ID citations establish only that allowed evidence was referenced.
Readership/geography may still be mistaken despite grounding instructions. Human
review is mandatory. Fake semantic fixtures test plumbing, not model intelligence;
the 13 realistic posts on the user's staging site were not accessed.

Sample selection remains heuristic, not statistically representative. Eight time
windows and four category strata plus at most two sparse-fill queries keep work
bounded at 42 ID queries/32 posts. Large archives need database query profiling;
SQL runtime itself depends on indexes/host. Catalogs above 200 fail closed for AI.
No model switcher, key vault, automatic PII redactor, asynchronous paid jobs, real
billing reconciliation, semantic discovery relevance, writing, SEO or image adapter
is implemented. The pinned model can retire or become unavailable; failures preserve
the local fallback. Direct HTTPS and existing single-site constraints remain.

Automated Chromium/axe checks do not establish screen-reader accessibility or cover
all browsers/themes. Persistent object-cache deployments require staging validation.
Do not interpret this release as security certification or WordPress.org approval.

## Changed files

New runtime: `includes/class-editorial-scout-openai.php` and `-openai-screen.php`.
Bootstrap/version/loader, existing admin routing/notices, profile screen/sampler,
uninstall and WordPress readme integrate them; feed/store/runner/scheduling code is
preserved. The deterministic build allowlist and CI candidate paths use the new ZIP.
New mock integration/response fixtures and extended browser setup/workflows cover
optional AI. README, staging guide, SECURITY, TESTING and this report document the
scope. The runtime ZIP contains only 19 allowlisted files and no test credential.
