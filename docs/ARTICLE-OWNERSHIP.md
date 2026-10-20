# Editorial Scout runs the newsroom; WordPress owns the newspaper

## Permanent contract

Every article created by Editorial Scout is a standard native `post` in the site's
normal WordPress posts table (with its actual configured prefix). Body, title,
excerpt, author and status live there. Categories/tags are native taxonomies,
featured images are native Media Library attachments and `_thumbnail_id` links.
Operational tables are never the sole store of article content. A standard theme
renders finished content without Editorial Scout, special shortcodes or a
proprietary renderer. Version 0.8 creates **drafts only** after separate approval;
no automatic publication, pending submission, scheduling, image or SEO work exists.

All uninstall modes and deactivation preserve native posts of every status,
revisions, attachment records/files, assigned taxonomies and ordinary/provenance
post metadata. This includes future generated images and user uploads equally.
Explicit cleanup removes only plugin operational tables/options/user metadata.
Default removal retains operational history. Administrators may still delete
native content themselves through WordPress. Do not confuse database restoration
from an old backup with uninstall: restoring a database can lose newer content.

## Durable attribution and linking in 0.8

New drafts receive `_editorial_scout_origin = editorial-scout/v1`,
`_editorial_scout_commission_key` (a random stable UUID) and
`_editorial_scout_commission_id` (reference only). Numeric IDs alone never establish
identity. The journal has one row per commission, a unique work key and a unique
nullable post ID. Keys are generated per work item, not derived from recyclable
IDs or mutable URLs. Another site's/reinstalled commission cannot accidentally
reuse an old numeric reference as its identity.

The read-only native history requires byte-exact origin key/value, standard type
`post` and native post permissions. It never infers origin from title/author/topic,
labels existing manual posts or fabricates written articles from commissions.
Duplicate marker rows do not duplicate history rows. Markers are durable provenance,
not signatures: privileged administrators/plugins can forge/remove them.

The writer's linked lookup also verifies its exact stable commission key. Native
status comes from WordPress, not a mirrored workflow field. Subsequent operations
never rewrite an editor's body, title, slug, author, taxonomy or publication status.
Deleting a linked post or changing its provenance blocks the workflow; it does not
silently create a replacement. Removing operational data leaves native attribution
available for a later reinstall, but intentionally loses the prior work journal.

## Crash consistency and conservative recovery

Native `wp_insert_post` includes hooks, caches and possibly external side effects.
It is **not** wrapped in a pretend all-or-nothing SQL transaction. Instead:

1. Evidence/profile/author/category choices are saved before per-request consent.
   A one-time sending state and a second durable dispatch claim precede the shared
   provider reservation/request. No cron/background retries exist.
2. Validated generation is durably saved before any native insertion. Human review
   and a separate nonce/approval are required for creation.
3. Under the shared expiring lease, persist `creating` before calling WordPress.
   A scoped last-stage insert-data filter rechecks lease/current commission and
   forces type `post`, status `draft`, selected author and recovery identifiers.
4. The initial native row includes `post_name = scout-{work UUID}` and
   `post_content_filtered = editorial-scout:{work UUID}` **in the same row insert**.
   This is an internal recovery marker, not article content or rendering logic.
   It can be retained harmlessly after removal. Native provenance metadata is
   supplied through normal `meta_input` APIs.
5. Before linking, find exactly one standard post with both original identifiers,
   check native edit permission and non-conflicting provenance, add only missing
   plugin provenance and persist the unique journal link. Never overwrite existing
   metadata conflicts or native content. A ready result remains available if
   saving the initial creation intent fails.
6. After intent exists, retries **search only**, never call insertion again. A
   crash after row creation but before metadata/link persistence can recover that
   row. Editor body changes are preserved. Zero/multiple matches, changed slug or
   internal marker, conflicting metadata, missing posts and unconfirmed database
   outcomes stop for manual reconciliation. An ambiguous pre-insert failure can
   therefore require administrator help, even when no post was actually inserted.

The slug predicate uses WordPress's native index; no full article-body search,
proprietary public renderer or native-schema change is required. This deliberately
trades some automatic recovery for avoiding duplicates. Do not delete/reset a
journal to force retries. A sending operation with no saved result also needs
manual investigation: no provider-response polling or duplicate paid dispatch.

The normal WordPress ecosystem is trusted. A hostile plugin with direct database
or post-hook privileges can override core behaviour outside this workflow. Do not
claim exactly-once behaviour across arbitrary third-party hooks, database recovery,
manual journal deletion or mismatched restored backups. Test actual admin plugins,
object caches and host/database failure behaviour in staging.

Future writer revisions, media downloads, archive/export, job takeover, provider
reconciliation or broader research require their own reviewed permissions,
consent, idempotency and lifecycle tests. None may compromise native ownership.
