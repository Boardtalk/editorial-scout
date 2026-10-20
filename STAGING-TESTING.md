# 0.5.0-rc1 staging installation and rollback

Use isolated single-site WordPress with cURL/DOM/mbstring and InnoDB. No production
site, API key, paid account, image service or hosting-panel integration is needed.
This is a local editorial foundation; live AI and credential/billing handling are
deferred, not silently enabled. No posts are created by the plugin.

## Upgrade from 0.4.0-rc1

1. Back up database and plugin files together. Deactivate Editorial Scout and leave
   any correctly functioning WordPress cron runner unchanged.
2. Build `python3 tools/build-staging.py`. From `artifacts/`, verify
   `sha256sum -c editorial-scout-0.5.0-rc1.zip.sha256`.
3. Upload the ZIP through WordPress Plugins → Add New → Upload Plugin and replace
   the older build, or extract its `editorial-scout/` directory into the plugins
   directory. Use directories 0755 / files 0644. No vendor/tests/fixtures belong on
   staging. Runtime package has seventeen allowlisted files.
4. Activate. Schema 3 is unchanged: sources, coverage, categories, decisions,
   schedule configuration and cron hook remain intact. New bounded non-autoloaded
   profile/recommendation options initialize only when used. Older schema migrations
   and safe failure notices remain available.
5. Confirm existing schedules, manual checks, source outcomes, human choices and
   generic cron health. Manual Only must still stop automatic feed checks. Standard
   WP-Cron or any external runner servicing WordPress cron works without panel APIs.

## Editorial Profiles and local learning

- Create two section profiles using Briefing/Explainer/Community starters. Edit all
  fields and choose native WordPress categories/default category. Change the shared
  default, edit and delete a profile; no source-monitoring category should change.
- Supply your own relevant/excluded topic phrases. Audience, geography, tone and
  instructions are stored configuration, not inputs to semantic AI in this build.
- On disposable test posts, create public posts across dates/categories plus drafts,
  private/password posts, a page and an excluded parent/child category. Opt into
  local Learn My Website, choose UTC dates, ceiling 1–32 and up to ten category
  exclusions. Confirm private/password/non-post/excluded descendants never appear.
- Review evidence links and limited-confidence wording. Learning creates no active
  profile; choose Review proposed profile, edit and explicitly Save reviewed profile.
  Change sampled evidence to private before review and confirm the proposal disappears.
  Proposals expire after one hour. Original source posts must remain unchanged.
- Catalogs over 200 native categories disable new-category proposals and excluded
  learning because complete descendant/near-duplicate checks cannot be guaranteed.
  Broad archive samples are heuristic, not statistically representative. Very large
  archives need staging query profiling; see the implementation report.

## Relevance, taxonomy and security

- On Discoveries select one to five stories and a profile. Matching title phrases
  should give Relevant; exclusion-only matches Irrelevant; absent/conflicting matches
  Uncertain. Results appear under Editorial Profiles, explicitly labelled local.
- Record shortlist/dismiss choices first: assessment must never change them. Repeat
  unchanged inputs and confirm deduplication; edit the profile or native taxonomy
  and confirm earlier recommendations are hidden as stale until reassessed.
- Native category routing uses configured preferences and fallback, not invented AI
  semantics. For a genuinely new matched phrase review name, slug, description and
  optional existing parent. Approve and create category is the only creation action.
  Similar/existing categories must not be offered/created again. No post assignment,
  author change, tag creation or article generation occurs.
- Test editor/subscriber/anonymous access and invalid/missing nonces for every new
  mutation. Source/post markup must render as text; prompt-like instructions in
  titles cannot trigger actions. Review keyboard operation and narrow screens.
- No AI key field, live adapter or externally enabled AI exists. Do not paste keys
  into editorial text fields. External adapters, consent, billing and encryption/key
  rotation are deferred; do not describe local matching as AI assessment.
- Diagnostic copying must still exclude profile text, evidence, credentials and
  infrastructure identifiers. Check debug logs; no raw provider errors are exposed.

## Rollback and removal

Deactivate, keep EDITORIAL_SCOUT_DELETE_DATA unset/false, and restore matching
previous files/database backups before reactivation for a complete rollback.
0.4.0 understands schema 3 but ignores new profiles/results; file-only downgrade
leaves that data and explicitly approved WordPress categories in place. Do not
restore a pre-schema-3 pruner against protected editorial evidence.

Default uninstall retains editorial data. Explicit opt-in removes plugin tables,
options and plugin-specific user metadata. It deliberately retains administrator-
approved native WordPress categories because these may be used by other content.
Do not test destructive uninstall or full-database restoration on valuable data.
