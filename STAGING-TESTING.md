# Milestone 3 staging install and upgrade

Use an isolated single-site WordPress installation, InnoDB and PHP with cURL,
DOM and mbstring. No API keys or paid services are required. This build is a
staging candidate, not a Directory submission.

## Upgrade from working 0.2.1-rc1

1. Back up the **database and existing plugin directory together**. Deactivate
   Editorial Scout before replacing files; this stops scheduled work and invalidates
   in-flight ownership. Do not change your Plesk/server cron command.
2. Build `python3 tools/build-staging.py`, then verify the adjacent SHA-256 sidecar
   from the artifacts directory with
   `sha256sum -c editorial-scout-0.3.0-rc1.zip.sha256`.
3. Upload `artifacts/editorial-scout-0.3.0-rc1.zip` through WordPress Plugins →
   Add New → Upload Plugin and replace the old build, or extract its `editorial-scout/`
   directory under `wp-content/plugins/`. Runtime permissions: directories 0755,
   files 0644. Do not upload vendor/test/development directories.
4. Activate and open Editorial Scout as an administrator. Schema 3 adds/backfills
   local data without a feed request. Existing sources, observations and next-check
   times remain. If migration is interrupted, open an administrator page to retry;
   checks remain paused until migration succeeds. Do not ignore an upgrade notice.
5. Confirm the **Sources**, **Discoveries** and **Settings** sections. Existing
   sources start Uncategorized and existing observations start New.

## Expected checks

- Sources: confirm last/next UTC times and readable outcomes. Run Check Now on an
  enabled source. An immediate second attempt must show the five-minute cooldown;
  disabled sources must not be checked. Verify the existing Plesk cron still invokes
  the unchanged `editorial_scout_check_feeds` hook via WordPress cron.
- Sample public feeds: `https://wordpress.org/news/feed/` and
  `https://www.nasa.gov/feed/`. Availability and redirects can change; use final direct
  HTTPS XML URLs. Automated tests use local deterministic RSS/Atom fixtures.
- Settings: create/rename categories, assign a source, then filter Discoveries by
  source/category/date/status. Deleting a category must return sources to Uncategorized.
- Shortlist/dismiss a story; recheck its feed and verify the choice persists. View
  coverage and confirm original source links remain. Same URLs with known tracking
  variants group together; different meaningful URLs are not merged by title.
- On disposable data, remove a source/change its URL after shortlisting. Protected
  evidence must remain attributed to the archived source. Returning the story to New
  intentionally releases protection. Test editor/subscriber and missing nonce denial.
- Review narrow screens, keyboard controls, readable errors and debug logs.
  **No posts, public content, AI requests or image-service requests should appear.**

## Rollback

Deactivate the candidate. Keep `EDITORIAL_SCOUT_DELETE_DATA` unset/false. Restore
**both** the previous plugin directory **and matching database backup** before
reactivating the old build. Schema 3 is additive but old pruning code does not
understand editorial protection; do not activate it against the upgraded DB.
Keep cron paused for the affected plugin while restoring, then confirm its schedule
and sources. Uninstall is not a rollback mechanism; destructive opt-in uninstall
removes all four plugin tables. Never test it on valuable data.

See `docs/MILESTONE-3.md` for validation evidence, warning counts and remaining risks.
