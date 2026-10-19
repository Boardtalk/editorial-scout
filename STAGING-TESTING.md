# Editorial Scout staging test

Use a disposable **single-site** WordPress 6.5+ staging installation with PHP 8.0+
(prefer a supported PHP release), cURL, DOM, mbstring and InnoDB. Back up files and
database first. Final integration tested WordPress 6.5.5/PHP 8.1.29 and
WordPress 7.1.3/PHP 8.2.34; PHP 8.0 full runtime was not available here.
This release candidate is for review, not a WordPress.org submission.

1. Build: `python3 tools/build-staging.py`. Verify the adjacent `.zip.sha256` with
   `sha256sum -c editorial-scout-0.2.1-rc1.zip.sha256` from the artifacts directory.
   Rebuilding unchanged runtime files produces the same bytes.
2. In staging, upload `artifacts/editorial-scout-0.2.1-rc1.zip` through Plugins →
   Add New → Upload Plugin, or extract its `editorial-scout/` directory under
   `wp-content/plugins/`. Replace the existing development build only after backup.
   Activate and open **Editorial Scout** as an administrator. No Composer install,
   credentials or paid services are needed. The ZIP contains nine runtime/license/readme files.
3. Try public RSS feeds such as `https://wordpress.org/news/feed/` and
   `https://www.nasa.gov/feed/`. These are third-party examples, not endorsed or
   guaranteed test fixtures; availability/redirect policies may change. Use the final
   HTTPS URL returning XML directly. The automated suite uses local deterministic
   RSS/Atom/TLS fixtures instead of relying on these websites.
4. Add/edit/disable/re-enable a source. Wait for due WP-Cron checks (a five-minute
   worker, normally hourly per source) or use
   `wp cron event run editorial_scout_check_feeds`. Only due sources are fetched.
   Expect titles, HTTPS links and dates in the read-only queue; repeat checks should
   deduplicate a source's article links. **No WordPress posts or public content are created.**
5. Try `http://`, `https://127.0.0.1/`, an invalid port, malformed XML, HTML and a
   redirecting feed on a controlled host. Expect rejection/error status and bounded
   retries. Editors/subscribers must not see the manager or access it directly.
   Check keyboard navigation, narrow screens, escaped titles and debug logs.
6. Deleting a source or changing its URL removes that source's history. Retention is
   pruned to 30 days/5,000 items when workers run. Requests require direct HTTPS;
   proxy-dependent hosting and multisite are unsupported.

## Rollback

Deactivate Editorial Scout to stop scheduled work. Keep
`EDITORIAL_SCOUT_DELETE_DATA` unset/false so uninstall retains plugin data.
Restore the previous plugin directory from backup; restore the database backup too
if you need the exact previous schema/history (this release upgrades schema 1 to 2).
Reactivate the chosen build and confirm its cron event and administrator page.
Do not use uninstall as a database rollback. Never opt into destructive data deletion
unless the staging data is disposable. The plugin never changes WordPress posts.

See `docs/HARDENING-REVIEW.md` in the source repository for tested configurations,
findings and remaining release checks. The package is not a security certification
or approval from WordPress.org.
