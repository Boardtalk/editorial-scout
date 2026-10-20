# Editorial Scout 0.8.0-rc1 staging and rollback

Use a disposable single-site WordPress 6.5+ installation with PHP 8.0+,
cURL/DOM/mbstring and InnoDB plugin tables. Newsroom batch decisions also require
native options/usermeta tables to use InnoDB; the plugin never changes native engines. Prefer maintained versions. Do not
install over valuable content without matching database/plugin backups.

## Upgrade from 0.7.1-rc1

1. Back up **database and plugin files together**. Deactivate Editorial Scout.
   Leave the site's existing WordPress cron runner unchanged, regardless of host.
2. Build `python3 tools/build-staging.py`. Inside `artifacts/`, run
   `sha256sum -c editorial-scout-0.8.0-rc1.zip.sha256`.
3. Install/replace the ZIP's `editorial-scout/` directory and activate. It contains
   24 allowlisted runtime files, no tests/vendor/credentials. Normal WordPress ZIP
   upgrading invalidates opcode caches; if replacing files manually, use your
   host's supported opcode-cache reset (ZIP timestamps are intentionally fixed).
4. Schema 6 adds the `editorial_scout_writing` journal, with unique work keys and
   unique nullable native post IDs. Existing commissions, choices, source evidence,
   profiles, cron schedules and AI controls are preserved. Migration verifies
   structure/engines/indexes before advancing and can restart. If it fails, check
   database permissions/InnoDB and reload an administrator page. Never force a
   schema marker or delete journals to bypass an error.
5. Confirm Sources, Discoveries, Newsroom, Profiles, Settings and Articles still
   work. Check Now/Check All and all cron modes retain their existing behaviour.
   Optional manual feed: `https://wordpress.org/news/feed/`.

## Writing without a paid service: automated validation

The integration/browser suites use fake credentials and mocked Responses API
output inside disposable Docker databases. No real API request or deployment is
needed. Build the **current** ZIP, extract it to a readable temporary directory,
set `SCOUT_PLUGIN_ROOT` to its plugin directory and run `./tests/run.sh`.
Use `SCOUT_KEEP_TESTS=1` for the browser follow-up, then
`node tests/browser/browser.cjs`. Finally remove only the test project's services
and volumes using the compose files in `tests/run.sh`. Do not point these tests at
staging/production databases: fixtures deliberately create native drafts and test
uninstall. CI covers the same package and prior regression suites.

## Optional human writing test — explicit billable action

Codex has **not** performed this test. No real credentials or paid requests were
used. A human may separately choose to test the release in disposable staging:

1. Reuse the existing **server-configured** OpenAI connection in Editorial Profiles.
   No secret-entry field or new credential is required. Keep production credentials
   out of this environment. See the connection/privacy instructions and
   `docs/MILESTONE-6A.md` for configuration. Enable execution deliberately, check
   provider-side limits and dated input/output pricing assumptions.
2. Create a saved editorial profile. Commission a discovery in Newsroom. Open
   Articles → **Prepare article draft** on a commission waiting for writing.
3. Review the original source links and full discovery evidence. RSS headlines
   are not factual proof. This release **does not crawl article pages** or infer
   permission/robots policy. Supply only your own original research notes or short
   material you have permission to use: at most four sources, 40–600 characters
   each. Do not paste paywalled/restricted/private/sensitive material or substantial
   copyrighted bodies. The browser links are for your own lawful research.
4. Confirm/choose the saved profile, an existing category and a permitted native
   author. Up to 50 users plus yourself are offered; assigning another author
   requires native permissions. The category catalog is bounded at 200. Click
   **Prepare writing request**, confirming usage permission.
5. Review the human-readable source/profile preview. Exact request data, system
   writing rules and token assumptions are expandable. Only the shown story,
   profile snapshot, selected source names/URLs/notes and category go to OpenAI;
   no author identity, private posts, existing article bodies or media are sent.
   To revise anything, prepare the request again before consent.
6. The cost is an **estimated conservative allowance**, not a billing guarantee.
   Check one-request consent and click **Write draft** once. No background paid
   work or automatic retry exists. Budget/cooldown checks are shared with website
   learning. Reservations remain after failures, timeouts or ambiguous outcomes.
7. A successful generation shows an article proposal and **Needs fact-checking**.
   Review supported claims, missing independent verification, opinions, unavailable
   information and source references separately from article content. Grounding
   checks are structural/screens, not semantic fact checking or plagiarism proof.
   Research incomplete has no create button. No post exists after generation alone.
8. Only after a separate review/approval, click **Create WordPress draft**. It must
   create one normal `post` with status `draft`, selected author/category and native
   title/body/excerpt. Suggested tags remain notes and are not applied. No image,
   SEO metadata, pending-review submission, scheduling or publication occurs.
9. Use **Open WordPress draft** and ordinary WordPress → Posts. Edit its body/title
   in WordPress, then refresh/replay the old creation request: no duplicate or
   overwrite is allowed. Native status changes are shown live. Internal notes stay
   in plugin workflow data, never in the public article body.
10. Test keyboard-only navigation, JavaScript-disabled forms, screen readers and
    narrow screens in your actual admin theme. Automated axe is partial coverage.

## Failure and recovery checks

- Without usable notes/permission/profile, no request may occur. A stale or expired
  preview, missing consent/nonce, another account, disabled connection, low budget,
  cooldown, network timeout or invalid output must fail safely.
- An uncertain sending state is **not repeated**. There is no provider-result
  retrieval/retry job. Contact the website administrator; check provider spend.
- Generated results are saved before native creation. If creation is interrupted,
  reopen its work and use **Recover WordPress draft**. Recovery only searches and
  links an existing exact native anchor; it never inserts again after creation
  intent has been recorded. Matching editor changes are preserved.
- Zero/multiple matches, removed/changed recovery anchors, missing native posts or
  conflicting provenance require manual reconciliation. This intentionally stops
  rather than risking duplicates. Do not clear the journal or change SQL to retry.
  A WordPress/database failure before insertion can therefore require administrator
  help even though the generated proposal is still reviewable.
- An author/category permission change may block native creation until corrected.
  Do not create another commission or paid request simply to work around it.

## Ownership and rollback

Default uninstall/deactivation retain plugin workflow. Explicit
`EDITORIAL_SCOUT_DELETE_DATA === true` removes plugin-only operational data,
including source material, notes and journals, **never native posts (even drafts),
attachments/files, assigned terms or ordinary/provenance post metadata**. Reinstall
can still list exact provenance-marked native posts. Explicit cleanup deliberately
loses old workflow/idempotency history; do not use it as a retry mechanism. Native
content remains self-contained and renders without plugin shortcodes/tables.

For rollback deactivate, preserve/export any new drafts, other WordPress content
and editorial work since backup, then restore **matching pre-upgrade database and
0.7.1 plugin files**. Version 0.7.1 refuses schema 6: file-only downgrade is not an
operational rollback. Never lower the schema marker. Restoring a whole database
can erase newer content and the reserved-token ledger; coordinate recovery and
check actual provider spend before re-enabling paid work. Removing a server secret
is a separate hosting-administration task; uninstall does not remove it.

See `docs/MILESTONE-7A.md` for implementation/test/warning evidence and
`docs/ARTICLE-OWNERSHIP.md` for the durable native-content contract.
