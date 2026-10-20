# Milestone 5: local editorial intelligence foundation

Base: `feature/scheduling-cron-health`, `16ac329`.
Branch: `feature/editorial-intelligence`. Candidate: `0.5.0-rc1`.
The request explicitly permits a well-tested foundation if complete AI scope cannot
be safely delivered. This candidate uses that scope; it does not claim all live AI
requirements are implemented. No live site, deployment, merge or paid API was used.

## Delivered

- Administrator-configured profiles: name, audience, relevant/excluded topic phrases,
  geography, tone, instructions, native preferred categories/default category; up to
  ten profiles, stable IDs and a shared default. Briefing, Explainer and Community
  starters contain generic instructions, not a publisher's topics. Editing/deletion
  and all mutations use capability/nonce/POST checks and the existing SQL lease.
- Opt-in local Learn My Website: UTC date range, ceiling 1–32 and up to ten category
  exclusions including descendants. Only published standard posts without passwords
  qualify. Eight interleaved old/new date windows and up to four category strata
  avoid a recent-post-only sample. Sparse windows may return fewer posts. No random
  sort, total-count query, pagination OFFSET or whole-site post-object load is used.
- Queries fetch IDs first, with indexed coarse local-date bounds and exact UTC bounds.
  SQL retrieves at most 4,000 content characters per selected ID, stripped to 600
  characters in memory. Keywords count once per post. Source posts are never changed;
  neither bodies nor excerpts are stored. A proposed profile and evidence IDs/dates/
  modification times stay in administrator-owned metadata for one hour, inactive.
  Evidence is revalidated before review/save; private, removed or modified evidence
  invalidates the proposal. Only explicit edited review/save activates it.
- Human review includes limited-confidence/uncertainty wording and links to local
  evidence. Audience/geography/tone are not invented from word counts. A partial
  English stop-word list and Unicode tokenization are heuristics, not semantic learning.
- One-to-five selected discoveries can be assessed against a selected profile using
  the fixed local provider. Literal topic matches are Relevant, exclusion-only matches
  Irrelevant, conflicts/no matches Uncertain. Evidence must occur in the title. These
  are recommendations, not factual verification or calibrated probabilities. No human
  shortlist/dismiss decision changes. Audience/geography/tone/instructions are stored
  configuration; this local matcher uses only topic/exclusion phrases.
- Profile/story/taxonomy/provider hashes deduplicate recommendations. Inputs are shape/
  size checked, outputs bounded/validated/sanitized, raw errors hidden. Changed inputs
  invalidate old display; expired dedup entries regenerate on the first request.
  Results are capped at 200 and 30 days on subsequent assessment, not via hidden cron.
- Native WordPress categories and tags are read, never mirrored into a new taxonomy.
  Relevant stories route to configured preferred categories (first then optional
  secondary) or the chosen default fallback. This is configured routing, not AI
  category inference. A genuinely new matched phrase can propose name, slug,
  description and optional native parent; only an explicit administrator approval
  with manage_categories creates it. Existing/near names are screened, including
  repeated proposals on the results screen. No post is created or assigned.

## AI deliberately deferred

The provider contract and structured-output boundary are implemented/tested. The
shipped local provider performs no networking. **External provider execution is
blocked even when consent is supplied.** No OpenAI/live adapter, API-key input/storage,
masking/encryption/rotation implementation, external consent UX, token accounting,
price estimate or spending ledger is present. There is no billing exposure or claim
of an implemented secure key vault. Provider doubles are not official API compatibility
or OpenAI transport validation. A later milestone must implement these together with
current official APIs and adversarial/informed-consent tests before enabling them.

No AI is called by feeds or cron. There is no article generation, WordPress post
creation, author/tag assignment, Yoast/SEO integration, Pixabay call, license lock or
paid-feature advertisement. Stable profile IDs, separate local storage and a provider
interface prepare future components; no external adapter discovery/registration UI
is exposed. Installed PHP remains trusted code, not sandboxed by this interface.

## Storage, migration and rollback

Feed tables and **schema 3** remain unchanged. New non-autoloaded options initialize
only on use; no existing profile table/data is replaced. Limits bound configuration
and result serialization. The current user's metadata and option caches refresh
under the common lease; learning renews ownership before queries and persistence.
Source attribution, categories, retention, feed SSRF/TLS pinning, cron scheduling,
manual cooldowns and sanitized diagnostics are retained.

Default uninstall keeps editorial data. Explicit opt-in deletes plugin profiles,
results and learning metadata along with previous plugin data, but deliberately
keeps approved native categories because WordPress content may now use them.
Expired/abandoned learning metadata is bounded to one record per participant and
removed on access or opt-in uninstall, not by an automatic background task.

Restore matching files/database backups for a complete rollback. A file-only return
to 0.4.0 ignores new options and leaves approved native categories in place. Avoid
running a pre-schema-3 pruner against protected evidence. See STAGING-TESTING.md.

## Validation

- Extracted release ZIP on WordPress 7.1.3 / PHP 8.3.35: **478 integration,
  security, workflow, lifecycle and upgrade assertions passed**. The earlier 408
  assertions remain active; new fixtures cover profiles, bounded public-post learning,
  excluded descendants/private canaries, source immutability, inactive review,
  local evidence/structured-output doubles, external execution rejection, staleness,
  expiration, category approval and independent capability/nonce/method gates.
- **54 Chromium checks passed**, including scoped axe WCAG 2/2.1 A/AA and responsive
  checks on four sections at 375/768/1280px, generic starters, native-category profile
  creation, local assessment, proposal evidence and explicit review/activation.
  External browser requests are blocked. These are not full accessibility certification.
- WordPress coding standards pass for thirteen first-party PHP files. Package builds
  byte-identically twice. CI runs PHP syntax on 8.0/8.2/8.3/8.4 and extracted-package
  integration on WordPress 6.5/PHP 8.1 and current PHP 8.2/8.3, with Chromium on 8.3.
  Hosted CI status is recorded separately in the PR description and task result.
- Official **Plugin Check 2.1.0**, upstream commit
  `e8186b80c43c32b5841cabfdee8ebc21594bf89e`: **0 errors / 135 warnings**:
  70 direct-query, 62 uncached-query, one schema-change, one input-sanitization and
  one VIP post__not_in performance warning. The database warnings cover the existing
  atomic lease/custom-table work and bounded fresh public-post evidence checks.
  The input warning points to posted_ids: values are filtered to scalars then mapped
  through absint before use, with further native-ID/size validation. The scanner does
  not follow that pipeline. The exclusion warning concerns at most 32 already-chosen
  post IDs to avoid repeat evidence across date/category strata. No unbounded NOT IN,
  random order or total count is introduced. All findings remain in the raw report;
  acceptance/possible optimization still requires Directory review.
- Debug-enabled integration/browser execution produced no plugin PHP notices.
  Plugin Check bootstrap logged three WordPress core update-service TLS warnings
  inside the isolated network; these are distinct from plugin errors. Pinned Node
  action/runtime and ubuntu-latest maintenance annotations may also appear in CI.
- Runtime ZIP: seventeen allowlisted entries under `editorial-scout/`; no credentials,
  fixtures, dependencies or development files. SHA-256:
  `48aa70be69677ed7d089c37fca51db06e3606e5728b92944e7915100a7a73b68`.

No OpenAI transport, live API credential storage, token billing or semantic model
was tested or claimed. Provider doubles and an explicit no-network assertion cover
the delivered local boundary only.

## Known limitations and release gates

The live AI requirements above are deferred rather than partially enabled. Local
phrase matching can miss synonyms/context or recommend an unsuitable headline;
reviewers must verify source claims independently. Profile geography and other
editorial instructions do not affect this matcher. Learning is heuristic and can
underrepresent sparse sections; it is not a statistically representative full-site
analysis. English stop words can bias multilingual suggestions. Revalidation uses
WordPress status/password/modification timestamps and is a point-in-time check.

Catalog reads return at most 201 terms; selectors show the first 200 by ID. New
proposals and category-excluded learning refuse incomplete catalogs rather than
silently ignoring descendants or unknown duplicates. Near-name similarity is
heuristic, not semantic equivalence. Native WordPress edits outside this plugin's
lease may race with review. Preferred category order follows submitted native
selection order; semantic primary-category prediction is not implemented.

Query/result/field counts are bounded, but SQL execution time still depends on the
hosting database and archive size. Large or unusually imported date archives need
staging query profiling. Persistent caches, screen readers, Firefox/Safari and each
hosting environment need separate validation. Existing single-site/direct-feed
transport constraints and DNS timing limitations remain. No security certification
or WordPress.org approval is claimed; Directory policy acceptance remains external.

## Changed files

New runtime: provider interface, AI boundary, local provider, intelligence store/
learning/assessment service, and intelligence screen. Existing bootstrap/version,
admin routing and dashboard gain the new section/actions; readme and opt-in uninstall
cover the new local data. Feed/store/runner/scheduling security implementations are
preserved. The ZIP contains seventeen runtime files only.

Tests/build: intelligence integration/endpoint fixtures, extended lifecycle/browser
fixtures and browser flows, build allowlist and CI candidate paths. Documentation:
README, STAGING-TESTING, SECURITY, TESTING and this report. GPL-3.0-only and existing
locked dependencies are retained. No credential or test dependency enters the ZIP.
