# Article preparation priority fix — 0.8.2-rc1

Base: `feature/newsroom-housekeeping` at
`17388c20c811cd7428783f813b2fde2d436dca3f`.
Branch: `fix/article-preparation`. Target PR: `feature/newsroom-housekeeping`.
GPL-3.0-only; schema 6 unchanged. No commissioning features, production changes,
deployment, merge or paid requests.

## Reproduction and diagnosis

`tests/preparation-repro.php` requires the disposable test environment and empty
plugin tables. It creates **commission #4** with a normal profile, author, existing
category, permitted source notes and acknowledgement. On the unmodified 17388c2
runtime, preparation succeeds with **zero HTTP requests**, unchanged budget and
unchanged native posts. Preparation cannot itself return `ai_output`: its path
validates local form data and saves a preview, without invoking OpenAI.

The fixture then represents an earlier failed writing job with `error=ai_output`
and its corresponding transient. The baseline renders the **website-learning
explanation twice**, once from each state. This reproduces the misleading notice;
it does not prove which previous operation ran on the user's live commission.
No live commission or response was accessed. Existing historic `ai_output` records
lack a validation stage; their exact past cause cannot be reconstructed safely.

A separate fixture demonstrates a genuine validation defect: top-level article,
section and claim object keys in a different order are rejected, although JSON
object key order is immaterial. The fix requires the exact allowed key set while
accepting reordered keys. Missing/extra keys, types, sizes, unsafe text, source
references, verification coverage, copying and unsupported numbers remain checked.
No claim is made that reordered keys caused the reported live failure.

## Fix

- Article-specific messages override learning-only `ai_output` and consent wording.
  New writing failures use `writing_output`; legacy jobs still display accurate
  article wording. Website-learning messages remain separate.
- Notices are scoped to administrator **and commission**. A current operation's
  notice takes precedence over the old failed-job message, producing one current
  explanation. Old unscoped notices are ignored and expire normally.
- On failed preparation, a bounded one-hour WordPress transient retains valid
  profile/category/author selections and up to four safe notes of at most 600
  characters. It is available only to its originating authorized administrator
  and commission, bound to the current source evidence and writing journal. A newer writing
  outcome invalidates an older correction form and its explanation. Configured credentials,
  invalid fields, permission acknowledgements, paid consent and request tokens
  are not copied into this cache. Cached values are escaped on display.
- Correction forms restore the submitted choices rather than profile defaults.
  The permission checkbox remains unchecked. A previous prepared send form is
  hidden during correction, so the administrator must successfully prepare and
  review the new request before its own separate paid consent. The older journal
  is retained unchanged after failure. Successful preparation clears correction
  data and creates the usual fresh request token.
- Textarea output has no template indentation added to submitted notes. Existing
  journal author/category choices also populate the preparation form.
- Response diagnostics contain **fixed enums only**: response envelope, JSON,
  secret screening, structure, field/section text, claims, source references,
  verification coverage, tags, unsupported numbers and copying. No response text,
  source text, credentials, raw exception or provider errors are logged. The
  classifier is used for dispatch results and saved-result validation before
  native creation. Invalid provider results are not stored as generated articles.

Preparation remains local. Request consent, frozen payload/fingerprint, one-shot
claim, token budgets, cooldown, reservation on failure, durable writing journal,
crash reconciliation and native content ownership remain enforced. No old consent
is inferred from retained form data. Existing posts and commission rows are not
rewritten by the fix or failed preparation.

## Validation

Local WordPress 7.1.3 / PHP 8.3.35 / MariaDB 11: **842 integration assertions
pass**, plus the three commission #4 reproduction checks. The new focused suite
includes 27 preparation/validation regressions. WordPress Core/Extra standards
pass 21 runtime PHP files; syntax passes all 55 PHP files. JavaScript/shell syntax
and `git diff --check` pass. Two ZIP builds are byte-identical; the 25 allowlisted
runtime files contain no test fixtures or dependencies.

Browser regressions exercise failed preparation, exact retained choices/notes,
unchecked acknowledgement, correction refresh and successful local preview before
continuing the existing consent/native-draft and housekeeping tests. Scoped axe
and responsive checks run at 375/768/1280px. The retained nine-job GitHub Actions
matrix covers PHP 8.0/8.2/8.3/8.4 lint, WPCS, packaging, minimum WordPress 6.5/PHP
8.1 and current PHP 8.2/8.3 integration plus Chromium in the PHP 8.3 job.
[Branch CI runs](https://github.com/Boardtalk/editorial-scout/actions?query=branch%3Afix%2Farticle-preparation).
Final browser/CI and official Plugin Check outcomes, warning review and raw-log
paths are supplied with the release evidence and prepared PR description.

Package SHA-256: `aa8cc7c109a753c0d72f9cad8abb656ffcea98483aa844e9470b755b031e30b4`.
The runtime is 352,188 bytes. No schema migration or changes to source discovery,
scheduling, housekeeping decisions or native creation/recovery rules.

## Staging and rollback

Back up files/database, deactivate, install the verified 0.8.2-rc1 ZIP and activate.
No schema migration is required. Leave the site's existing cron runner unchanged.
Open an unstarted commission. Choose a saved profile, permitted author and category.
Submit a short note (under 40 characters) with permission checked: expect one
research-incomplete explanation, restored selections/notes and an unchecked
permission box. Correct the notes to 40–600 characters, reconfirm permission and
prepare: a preview should appear without any provider request or budget change.
The separate Write draft action is billable on a real configured provider; it was
**not used against a live service during this work**.

Test old failed writing history: it must reference article validation, not profile
learning, and must not appear twice beside a current preparation error. Verify
other administrators/commissions cannot see correction data. Existing native
drafts/articles and saved journal/recovery records must remain intact.

Rollback by deactivating, restoring backed-up 0.8.1-rc1 plugin files and reactivating.
Both versions use schema 6. Do not delete journals or restore an old database over
newer WordPress content. Older code ignores the new scoped correction/notice keys,
but retains its prior notice/key-order bugs. Expired WordPress transients become
unavailable after one hour; physical removal depends on WordPress cache/option
maintenance. No instant database erasure is promised. Correction data can be lost
on expiry, cache eviction or source changes; it is not a substitute for saved
research journals. Legacy stage-less errors remain generic article failures.

The exact live cause of commission #4's previous error remains unverified. Browser
and automated checks do not certify every admin theme/plugin combination or
assistive technology. Plugin Check is not security certification or Directory
approval.
