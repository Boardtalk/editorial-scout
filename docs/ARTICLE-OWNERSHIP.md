# Editorial Scout runs the newsroom; WordPress owns the newspaper

This is the contract for a **future** writing engine. Version 0.7.1-rc1 does not
write, link, publish or retrospectively label any WordPress article. A commission
is an order waiting for writing, not a WordPress draft. Feed evidence is unverified.

## Permanent storage and rendering

A finished article must be a native `post` in the site's standard `wp_posts` table
(with its real configurable table prefix). Body, title, excerpt, author and native
publication status live there. Categories/tags use native taxonomies, featured
images use native Media Library attachment posts and `_thumbnail_id`. Normal
compatible post metadata is permitted. Content must render with a standard theme
without Editorial Scout, its tables, shortcodes, blocks or a proprietary renderer.
Operational source/evidence/commission tables must never be the sole store of an
article body. Do not embed credentials, private workflow payloads or secrets in
content or metadata. Avoid proprietary embeds that stop working after removal.

Deactivation stops the feed worker and invalidates its lease, retaining data.
Default uninstall retains operational data. Explicit
`EDITORIAL_SCOUT_DELETE_DATA === true` removes **plugin-only operational** tables,
options and plugin user metadata. Neither path may delete or change any native
post (published, draft, pending, scheduled, private, trash, revision or attachment),
its content/status, assigned taxonomies, normal or provenance post metadata,
Media Library files or featured-image links. User-uploaded and future downloaded
images have the same ownership protection. Reinstall must be able to read retained
provenance even if the old operational tables were explicitly removed. An
administrator can still delete native content through WordPress itself.

## Attribution and read-only lookup in this release

A future writer must set `_editorial_scout_origin` to the exact string
`editorial-scout/v1` only on a new post it successfully creates for that commission.
Do not infer provenance from title, subject, author, category, link, or a similarly
named metadata key. Do not label existing or manually created posts, including
posts used as Learn My Website samples. Never expose a public or REST endpoint
that lets arbitrary callers add or alter provenance. This version does not add
any provenance metadata at all.

The Articles → WordPress articles list performs a bounded native `WP_Query` over
standard `post` objects with binary exact key and value matching, supported native
statuses, 20 results per page and deterministic date/ID ordering. Duplicate marker
rows do not duplicate posts. It checks `manage_options`, `edit_others_posts`,
`read_private_posts`, and each displayed post's `read_post` capability; editor links
also require `edit_post`. No draft body is exposed, no statuses are mirrored into
commissions, and no unrelated articles are counted. Draft, Pending Review,
Scheduled and Published mean actual live WordPress statuses. Private and Trash
are explicit, separate statuses. A missing marker means no attribution.

The marker is durable provenance, **not a signature or proof of factual quality**.
An administrator or another plugin with database/post-meta privileges can forge
or remove it. No attempt is made to guess attribution after marker deletion.
Native meta-key indexing helps, but very large postmeta tables need profiling;
no plugin-only cache or operational table is required to find native content.
Capabilities or other plugins' query filters can narrow visible results. We do
not display a potentially misleading global native article count.

## Required future commission-to-post linking protocol (not implemented)

1. Add a restartable verified migration for a nullable `native_post_id` on the
   commission, with a unique key on non-null IDs. Keep one commission per canonical
   story; use a generated durable commission UUID **and site identity**, not a
   recyclable numeric ID alone, as the stable idempotency key. A reinstall after
   operational cleanup must never reuse a prior key or attach a retained post to
   an unrelated new commission.
2. Reserve/claim work with the shared lease and an ownership/revision check, plus
   an explicit writing state and per-attempt journal. Authorization, consent,
   budget controls and nonce checks remain separate from attribution. Creating a
   post requires `edit_posts`; assigning another author, native terms, media and
   statuses requires the corresponding native capabilities. Publishing always
   needs its own explicit policy and native permission; this milestone enables none.
3. Create only a native unpublished draft, using WordPress APIs and the normal
   content/attachment ownership model. Set the origin marker, a versioned
   `_editorial_scout_commission_key`, and optional numeric
   `_editorial_scout_commission_id` for reference (never sufficient for identity).
   Those post metadata keys must remain after operational cleanup.
4. Native `wp_insert_post` and its hooks/caches/files/external effects are **not**
   a safely retryable SQL transaction merely because operational tables are
   InnoDB. Before enabling a writer, implement and test a write-ahead journal,
   durable claim, exact-key recovery lookup and crash reconciliation. Treat an
   ambiguous creation as requiring reconciliation, never blindly retry creation.
   Metadata itself has no native uniqueness constraint: a meta-only lookup cannot
   guarantee exactly-once creation under concurrent writers.
5. Validate that a linked ID exists, has type `post`, has the exact origin and
   commission key, belongs to this site and is not already linked elsewhere.
   Update the operational link with a conditional ownership check and unique
   constraint. Zero/one/multiple recovery matches must have explicit safe outcomes;
   multiple matches or disagreement require administrator review, not deletion.
6. Read status from WordPress on every work-history query. Native edits, scheduled
   publication, trash/restore, permanent deletion, missing metadata and operational
   reinstall need reconciliation without overwriting native editorial changes.
   Link deletion must never cascade into WordPress content/media deletion.

Before shipping writing, test crashes at every boundary, hooks that mutate posts,
object caches, lease expiry/deactivation, retries and concurrent workers, native
content editing, multisite policy, media reuse and permission changes. This design
is not an assertion that article-writing idempotency has already been implemented.
