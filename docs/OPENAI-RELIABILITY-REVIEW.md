# OpenAI article-writing reliability review — 0.8.3-rc1

Base: `fix/article-preparation` at `3bc330c`. New branch:
`fix/openai-writing-reliability`. Reference investigation: `a6061e0`.
Reviewed 21 October 2026. Commission #4's live failure remains unconfirmed: its
journal was not accessed, changed, cleared or retried. No real API call or
production/staging deployment was made.

## Official contract reviewed

The rendered documentation at developers.openai.com/platform.openai.com could not
be fetched from this environment (HTTP/network failures). Instead, this review
read the current official OpenAPI specification, generated official SDK types,
SDK extraction implementation and official cookbook. These are first-party sources,
not evidence from mocks alone. No claim of a successful live/provider-certified
integration is made. Model capability/strict-schema acceptance needs eventual
administrator-approved testing; none is billable automatically here.

* [Official OpenAPI specification](https://github.com/openai/openai-openapi/blob/master/openapi.yaml)
  — Responses endpoint, request fields, authentication, model identifiers and errors.
* [Official Response type and output_text implementation](https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response.py)
  — output length/order varies; do not assume output[0] is the answer. The helper
  concatenates output_text from message content across the output array.
* [Request parameters](https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_create_params.py)
  — store, background, stream, instructions, input, max_output_tokens, text.format.
* [Output message type](https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_output_message.py)
  — content is a list of output_text/refusal; assistant role and message status.
* [Output item union](https://github.com/openai/openai-python/blob/main/src/openai/types/responses/response_output_item.py)
  — reasoning and tool items are distinct from messages.
* [Strict-schema type documentation](https://github.com/openai/openai-python/blob/main/src/openai/types/shared_params/response_format_json_schema.py)
  — only a subset of JSON Schema is supported with strict mode.
* [Official Structured Outputs introduction](https://github.com/openai/openai-cookbook/blob/main/examples/Structured_Outputs_Intro.ipynb)
  and [GPT-4.1 prompting guide](https://github.com/openai/openai-cookbook/blob/main/examples/gpt4-1_prompting_guide.ipynb).

The model remains pinned to `gpt-4.1-mini-2025-04-14`, with the same 4096 output-token
cap, budgets and price assumptions. No silent model/cost change. The fetched
OpenAPI specification flags this model family for **14 January 2027 shutdown**;
plan a separately reviewed model replacement before that date and verify the
provider's current deprecation notice. Identifier availability does not guarantee
availability for a particular account. Do not infer capabilities of future models.

## Findings and implementation

The old parser required one output item with one text block. The documented API
and SDK allow multiple items/blocks. The parser now concatenates message text in
order, allowing documented reasoning metadata but never displaying/persisting it.
It bounds the full HTTP response to less than 65536 bytes, aggregate text to 30000
bytes, output items to 16, and blocks per message to 16. Refusals, unknown output,
tool calls, incomplete messages/status, empty content and multiple JSON documents
fail closed. Tools are never configured or executed. Role/status are checked when
present; extra provider metadata is ignored, never interpreted as article fields.
All article fields still pass exact local validation before storage/native creation.

Requests use Responses `text.format` with `type:json_schema`, `strict:true`, a fixed
name, exact required keys, `additionalProperties:false` at every object, and enums
for status/note kinds. They do not use Chat Completions `response_format`.
Schema descriptions and instructions now spell out every local field/count limit,
mandatory supported plus verify/unavailable notes, source IDs and incomplete-output
rules. Length/cross-field/source-grounding rules remain enforced locally; schema
annotations guide generation, not a guarantee that the provider enforces them.
Unsupported/uncertain JSON Schema keywords were deliberately not introduced.

Public and private text now have distinct quotation treatment. Private verification
notes and tags accept ordinary quotation marks. Public quotes must be paired and
occur verbatim in supplied source material; invented/unclosed quotations fail.
Markup, shortcode brackets, URLs, unsafe controls, unknown references and twelve-word
source copying are still rejected. Ordinary apostrophes and punctuation remain
valid. Only a leading 1–3 digit integer followed by `.` or `)` and whitespace in a
section heading is structural numbering; other heading/body numbers need source
support. Numerical comparison now uses raw source material so decimal punctuation
is not destroyed before matching. No generated body is silently rewritten.

A `research_incomplete` result must have empty public fields/sections/tags and
nonempty explanatory notes. It cannot create a native post. Supported references
and numeric/quotation matching are conservative heuristics, not independent factual
verification. Attribution, implied claims and semantic accuracy require human review.
The copying heuristic is not copyright clearance or proof of originality.

HTTP errors are classified by status only: authentication, account/rate limit,
service error or request rejection. Transport failures get a combined connection/
timeout label: WordPress may supply only a generic transport code, so precise
network causes are not invented. Raw provider errors/refusal text/exception strings
are never stored or shown. Incomplete/token-limit/refusal/envelope/usage and local
validation stages have safe fixed identifiers. The output cap is not increased
and there is no automatic retry, poll or continuation. A failed response can
already have incurred a charge.

Valid reported usage is counted even if output is incomplete or rejected locally.
Total tokens must be a nonnegative integer within the conservative reservation;
when input/output counts are supplied they must be nonnegative integers, sum to
total, and respect the requested output cap. Unverifiable usage fails closed.
The durable full reservation remains charged against plugin limits on every
uncertain/failed request; this is not a guaranteed provider billing cap/refund.
Bearer authentication, fixed HTTPS endpoint, TLS verification, proxy rejection,
no redirects, 25-second timeout and body bounds are retained. `store:false`,
`stream:false`, `background:false` are explicit. There are no tools, conversations,
previous-response IDs, author identities or cookies in the request. `store:false`
is not a guarantee of zero provider retention; organization policies still apply.

## WordPress workflow and diagnostics

Articles → open the commission's **Prepare article draft** screen. A failed writing
journal shows a plain-English explanation and collapsible **Technical details**.
**Copy diagnostic summary** copies/selects only plugin version, pinned model,
allowlisted writing state, allowlisted validation check and the no-retry policy.
There are no journal dumps, research/profile text, request/consent tokens, provider
IDs/error strings, generated article text, credentials, infrastructure paths or
usage ledgers in that summary. Clipboard failure leaves the text selected for
manual copying. No SSH or database access is required.

The summary requires manage_options + edit_posts and the original request owner,
and only appears for failed/sending work. It reads the existing journal; viewing
or copying never sends a request or edits that journal. Read-only diagnostics need
no action nonce; existing paid/native actions still require capability checks,
POST, nonce, original owner, lease and single-use consent. Transient and stored
failures remain one primary notice. A missing historical stage says not_recorded;
no retrospective diagnosis is invented.

New preparation stores a writing-rules version. A pre-upgrade prepared request
cannot dispatch under materially changed instructions: prepare a new preview,
review all material/rules/allowance and explicitly consent again. No old journal
is edited during upgrade. Failed/sending/ready/creating/linked journals, notes and
native posts remain stored. Sending/uncertain work stays protected, without a new
paid dispatch; ready results retain their separate native-post approval/recovery
path. Do not reset/delete a journal to get past a recovery warning.

## Upgrade and rollback

1. Back up the database and installed plugin files. Retain the previous ZIP.
2. Confirm the site is staging. Upload the supplied 0.8.3-rc1 ZIP via Plugins →
   Add New → Upload Plugin, replacing the installed version. No schema change
   or data-clearing migration is required (schema remains 6).
3. Open Articles and inspect existing failed work's Technical details. Do not
   retry commission #4 or any live writing operation as part of this upgrade.
4. For a later explicitly approved billable test, use separate permitted fixture
   research, prepare a fresh request and review the entire preview. Check the
   per-request consent manually; failure keeps the reservation. Do not test by
   clicking old writing tokens or bypassing cooldown. Native draft creation still
   needs its separate human approval; never publish for a test.
5. Verify source schedules, evidence, shortlist/dismiss decisions, commissions and
   native drafts retain their content. View/copy a failed diagnostic without a
   new HTTP request, journal mutation or post creation.
6. Roll back by replacing plugin files with the previous ZIP. **Do not uninstall**
   or restore a database snapshot over newer editorial/native work. Schema is
   unchanged and old software ignores the added context field. The older release
   cannot display all new stage labels. Do not dispatch a preview across a version
   change: review and prepare fresh material with new consent. Preserve uncertain
   journals and obtain recovery advice instead of resetting them.

## Verification and limitations

The final runtime package passed **1151 integration assertions** on WordPress
7.1.3 / PHP 8.3.35, including actual-token accounting and reservation/status checks
on all 39 response scenarios. **175 Chromium browser checks** passed, including
keyboard/copy behaviour, diagnostic retention and scoped axe A/AA checks at 375,
768 and 1280 pixels. Local PHP syntax checks passed on PHP 8.1 and 8.3;
WordPress Core/Extra coding standards passed without errors or warnings.

Plugin Check on the runtime package found **0 errors and 201 warnings**, matching
the previous release count. Categories: direct SQL (102), uncached SQL (87), schema
change (1), direct AI integration (1), unescaped-DB-parameter heuristic (1), slow
meta-value/meta-key queries (3 each), post__not_in queries (2), and an existing
input-sanitization warning (1). They remain review items, not blanket claims of
false positives. Dedicated fresh SQL supports leases and recovery; AI-client
migration, meta-query performance and the flagged input/SQL wrappers need separate
Directory review. A development-checkout scan includes tests and historical ZIPs
and is not the distributable result. Only the runtime package result is reported
above. Full local outputs are retained under artifacts.

The reproducible ZIP is `editorial-scout-0.8.3-rc1.zip`: 25 runtime files,
364679 bytes; SHA-256:
`640bb8c892c085a4c5e1c4b0d656537e3b82a3f2a9b44ff9a4469b65fcf961c8`.
Two independent builds compared byte-for-byte. Tests, dependencies, diagnostic
helpers and development artifacts are excluded from the archive.
Mocks use actual production request/parser/validator/journal methods. No provider
response text is recorded for diagnostics. Local tests do not establish successful
provider-side schema acceptance or diagnose commission #4. Browser accessibility
checks cover plugin regions and do not certify all of WordPress or third-party
admin extensions. No WordPress.org approval or security certification is claimed.
