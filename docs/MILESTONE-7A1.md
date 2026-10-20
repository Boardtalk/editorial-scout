# Milestone 7A.1 — Newsroom housekeeping

Base: `feature/ai-article-drafting` at
`cf6c7101151e0ae2bd2e037ee47ca1ee044381db`.
Branch: `feature/newsroom-housekeeping`. Release **0.8.1-rc1**, GPL-3.0-only.
No merge, production changes, deployment, paid AI calls or public posting.

## Implementation

New Stories checks for **any enabled, non-archived supporting source** before
ordering or pagination. Mixed-source stories remain visible. The administrator's
Include stories from disabled sources view filter reveals historical New Stories;
other editorial states remain accessible regardless. All source observations are
retained. Counters use the same view predicates, excluding only the selected
editorial state so the status strip describes available decisions in those filters.

A separate housekeeping service implements administrator-owned, one-hour,
single-use previews and receipts. Selection is capped at 20 validated story hashes.
Disabled-source cleanup selects and counts eligible stories on the server: only
uncommissioned New Stories with no active supporting source. Held stories and all
commissions are excluded. Selected dismissal also permits uncommissioned held
stories. These are **status changes**, never evidence deletion. Ordinary protected
retention/capacity limits still apply; cleanup is not a storage-reclamation tool.

Articles has Cancel commission and Restore commission actions, each with a preview
and explicit confirmation. Cancellation maps to the existing dismissed commission
state; restoration returns it to Waiting for Writing. Commission IDs, dates,
profile snapshots and evidence survive. No schema or writing-engine changes.

Any writing-table row protects the commission, independently of its state, data,
post ID or owner. This includes prepared, failed, unknown and interrupted operations.
The shared Store decision implementation enforces protection too, so Newsroom and
legacy Discoveries controls cannot bypass it. Cancellation cannot unlink or delete
native posts, clear research notes or reset recovery journals. This conservative
rule deliberately offers no journal-discard shortcut.

All mutations require manage_options, POST and action-specific nonces. Services
check administrator access; fixed redirect tabs and escaped output keep untrusted
feed text out of code. A shared renewed SQL lease serializes housekeeping, sources,
cron and writing operations. Fresh snapshots compare decision revision, commission
state, journal presence and source/evidence state at confirmation. Stale/protected
rows are skipped explicitly. Two concurrent submitters cannot replay a manifest.
Options/usermeta InnoDB is verified before mutation. Processing is durably claimed
before the transaction; decisions and completed receipt commit together. Rollback
restores the correct lease value and invalidates metadata cache. Failed/uncertain
processing remains consumed and requires current-state review plus a fresh preview.

Forms use native controls, labelled selection checkboxes, required confirmation,
focus anchors, status messages, translated plain-English text and compact collapsed
housekeeping details outside active previews/results. No JavaScript is required.
Recovery links expose dismissed stories; no global undo overwrites later decisions.

## Changed files

- `includes/class-editorial-scout-housekeeping.php`: preview, eligibility, bounded
  cleanup, transactional confirmation and compact UI.
- `includes/class-editorial-scout-newsroom.php`, `newsroom-screen.php`: active
  support visibility, matching counters, filter, selection and recovery entry points.
- `includes/class-editorial-scout-articles.php`, `admin.php`, `store.php`: commission
  controls, authorized routing and shared journal guard.
- `editorial-scout.php`, `readme.txt`, `uninstall.php`, `tools/build-staging.py`,
  `.github/workflows/ci.yml`: version/loading, opt-in own metadata cleanup, allowlist
  and package CI. Default uninstall still retains operational data.
- `tests/housekeeping.php`, `housekeeping-endpoint.php`,
  `browser-housekeeping-setup.php`, `integration.php`, `newsroom.php`, `browser/browser.cjs`: real
  transactions, independent concurrent processes, authorization, retention and UI.
- `STAGING-TESTING.md`, this report: staging, limitations and same-schema rollback.

## Validation

- Runtime package: WordPress **7.1.3 / PHP 8.3.35**, MariaDB 11, real InnoDB
  transactions and isolated DNS/TLS fixtures. **815 integration assertions pass**, including all earlier regressions. Raw log:
  `artifacts/milestone7a1-integration.log`.
- Chromium: **161 checks passed**, including multi-source disable/re-enable,
  selected dismissal/recovery, exact preview/receipt counts, repeated POST,
  re-enable-after-preview stale rejection, cancellation/restoration, protected
  uncertain work and byte-identical native post/journal retention. Scoped axe
  A/AA scans and overflow checks pass at 375, 768 and 1280px. The existing no-JS
  workflow also passes. No browser errors in the final run.
- WordPress Core/Extra coding standards: **21 PHP files pass**. PHP 8.3 syntax:
  **52 PHP files pass**, including test fixtures; JS syntax, shell syntax and
  `git diff --check` pass.
- Reproducible ZIP: **25 runtime files, 343,842 bytes**; a second build is byte-identical.
  SHA-256: `1d8bc00b386e6378190327a53a1b7f32d9a60c19f5f610ad159d20de9fc66f6c`.
  Each extracted entry matches the current runtime file. No test/dependency paths.
- Unchanged-file comparison against `cf6c710` confirms the drafting engine, drafting
  screen, OpenAI bridge, feed security and scheduling implementations are untouched.
- GitHub Actions retains nine jobs: PHP 8.0/8.2/8.3/8.4 syntax, WPCS, reproducible
  packaging, minimum WP 6.5/PHP 8.1 and current PHP 8.2/8.3 integration, plus browser
  checks in the PHP 8.3 job. Final CI run URL/outcome is supplied with the PR text;
  [branch runs](https://github.com/Boardtalk/editorial-scout/actions?query=branch%3Afeature%2Fnewsroom-housekeeping)
  expose the matrix evidence.

Integration covers stale/expired/replaced and cross-administrator previews, malformed
inputs, capabilities/nonces/GET rejection, independent concurrent handler processes,
receipt-write rollback, consumed uncertain processing, lease recovery, failed count
queries, legacy decision routes, every known/unknown writing state and restoration
of old cancelled journal-backed work. Default and explicit uninstall retain native
content; opt-in cleanup removes only the plugin's own housekeeping metadata.

### Official Plugin Check 2.1.0

**0 errors / 201 warnings**, empty stderr, checked against the extracted runtime
package. Raw output: `artifacts/milestone7a1-plugin-check.txt`. These warnings are
reviewed, not hidden or represented as Directory approval:

| Count | Warning | Assessment |
| ---: | --- | --- |
| 102 | DirectDatabaseQuery.DirectQuery | Plugin tables, lease operations, transactions, schema verification and bounded receipts intentionally use direct prepared SQL. |
| 87 | DirectDatabaseQuery.NoCaching | Operational state must be read fresh for concurrency, eligibility and recovery; caching would require equivalent invalidation guarantees. |
| 3 + 3 | SlowDBQuery meta_value / meta_key | Bounded transactional user receipts update native user metadata by administrator ID and fixed metadata key; native content is never bulk-written. |
| 1 | DirectDB.UnescapedDBParameter | Newsroom counts wrap the already prepared offers SQL in a fixed aggregate subquery. No raw input is interpolated into that wrapper; offer/count literal-injection regressions pass. |
| 1 | InputNotSanitized | Existing intelligence-screen scalar/absint posted-ID pipeline, followed by native validation and authorization; analyzer does not follow the pipeline. |
| 2 | WPQueryParams.PostNotIn | Existing bounded local website-learning exclusions; unchanged. |
| 1 | DirectDatabaseQuery.SchemaChange | Existing explicitly opted-in operational-table uninstall, with native retention tests. |
| 1 | AIProvider.DirectIntegration | Existing direct adapter retains minimum WP 6.5 support, fixed transport, consent, privacy and budgets; checker suggests considering the newer WP AI Client. |

Earlier browser attempts exposed one new test timing race (asserting before a GET
filter navigation completed), now corrected with explicit navigation/load/transition
waits. Another attempt captured three WordPress core startup dependency errors during
native admin navigation, as seen in prior milestone review. Error capture now
includes page URLs; no error is suppressed. The final long-session run passed all
161 checks without errors. Actual admin plugin/theme combinations and long sessions
remain part of staging review.

## Limits and staging review

The disabled-source option is per view. Cleanup counts are a preview-time snapshot;
later changes can reduce actual affected counts, and the receipt reports that.
Only the latest housekeeping preview/receipt per administrator is retained, separate
from commission history and the existing Newsroom decision receipt. Interrupted
operations require review rather than automatic retry. Status cleanup consumes the
existing protected-decision capacity (1,000 decisions / 10,000 observations); it
does not reclaim disk space. Evidence remains subject to existing retention rules,
which protect stored editorial decisions and commissions.

No schema migration is needed; rollback to 0.8.0-rc1 reads the same schema/states.
Older code lacks the new guard on journal-backed editorial transitions. Accessibility
automation is scoped to the plugin region; it does not certify all assistive
technologies, admin themes, plugins or screen readers. Manual production-like
staging review remains necessary. Plugin Check is neither security certification
nor WordPress.org approval.
