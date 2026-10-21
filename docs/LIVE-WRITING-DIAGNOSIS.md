# Commission #4 — safe writing-failure investigation

Repository: Boardtalk/editorial-scout. Inspected runtime: 0.8.2-rc1,
`fix/article-preparation` at `3bc330c4c82faf4dfd621ad1724b732321e00b64`.
Investigation branch: `diagnose/writing-validation`.

**The live failure's cause is not yet established.** No staging access, provider
response or live journal was available. No paid call, retry, live journal change,
plugin deployment or runtime code change was made. The earlier JSON key-order fix
is present and does not establish the cause of this new failure.

## What 0.8.2 already persists

`Editorial_Scout_Drafting::write()` stores the fixed failure identifier at
`context.error_stage` in the existing writing journal, alongside `error` and
`state`, before returning the failure. `failure_stage()` allowlists 13 identifiers.
The provider bridge preserves these identifiers when converting `ai_output` to
`writing_output`. A normal saved 0.8.2 writing-validation failure should therefore
have a stage. A failure saving the journal instead returns `writing_interrupted`.

This is independent of the short-lived notice. The UI only appends extra wording
for seven stages. `structure`, `field_text`, `sections`, `section_text`, `claims`
and `tags` produce the same generic message even when their stage is persisted.
The reported wording alone cannot distinguish these from a missing stage; the
administrator may also have quoted only the message's first sentence.

The journal may be older, replaced by a later explicit preparation, missing or
unreadable, or lack a stage. If no stage is present, the historical validation
failure **cannot be reconstructed** from this message. Invalid model output was
not retained as a generated result; no retrospective claim should be invented.
Do not prepare/write again merely to recover diagnostic information.

## Read only the allowlisted stage

An administrator can extract the existing stage without installing the helper or
changing the plugin. Use the isolated inline procedure below. It skips ordinary
plugins and themes and loads only the installed Store and Drafting class definitions;
these files register no hooks. The diagnostic calls only capability checks, the
journal `SELECT`, JSON decoding and the fixed stage allowlist. It does not call
`prepare()`, `write()`, any provider method, migrations or scheduling methods.

Return **only the identifier**. Never use `print_r`, `var_dump`, a whole-row SQL
export or a general journal dump: the journal contains private approved research,
profile snapshots and consent/recovery information. Fixed absence markers are
`not_recorded` and `unrecognized`; they are not diagnoses.

### Staging execution procedure (administrator only)

These instructions are hosting-panel agnostic and also apply to Plesk. **No remote
path, PHP executable or WP-CLI executable has been verified by us.** The URL
`https://boardtalk.net/wp-blog` is not a filesystem path.

1. Connect over SSH as the staging subscription/site owner, not root. In Plesk,
   inspect the domain/subdomain Hosting Settings document root and PHP Settings,
   or ask the hosting provider for the **staging** WordPress absolute directory,
   compatible PHP CLI executable and WP-CLI PHP script/PHAR. Verify whether the
   staging site really uses this URL before proceeding; do not target production.
   The installation directory must contain `wp-load.php`, `wp-admin/` and
   `wp-includes/`. A URL subdirectory may map differently on disk.
2. `command -v php` and `command -v wp` locate PATH candidates only. `wp --info`
   reports PHP/runtime details when a `wp` launcher exists. If `wp` is a shell
   wrapper, ask for its underlying WP-CLI PHP script/PHAR; do not pass a shell
   wrapper to PHP. Use the hosting-provided PHP CLI compatible with staging
   (tested locally: PHP 8.3). Do not guess a versioned panel path.
3. Run the following in Bash, supplying the three verified **absolute paths** and
   a WordPress administrator login when prompted. This uses the PHP script/PHAR
   explicitly, so it does not depend on a launcher shebang or PATH PHP version.

```bash
read -r -p 'Absolute staging WordPress directory: ' SCOUT_WP_ROOT
read -r -p 'Absolute compatible PHP CLI executable: ' SCOUT_PHP
read -r -p 'Absolute WP-CLI PHP script or PHAR (not shell wrapper): ' SCOUT_WPCLI
read -r -p 'WordPress administrator login: ' SCOUT_ADMIN
cd -- "$SCOUT_WP_ROOT" || exit 1
test -f wp-load.php && test -d wp-admin && test -d wp-includes || exit 1
"$SCOUT_PHP" -v || exit 1
"$SCOUT_PHP" "$SCOUT_WPCLI" --info || exit 1
```

Check the displayed versions before continuing. Then execute this **exact
read-only diagnostic** from the same shell. No password or API key is an argument.
`--url` selects the intended site; it does not contact that URL.

```bash
"$SCOUT_PHP" "$SCOUT_WPCLI" --path="$SCOUT_WP_ROOT" \
  --url='https://boardtalk.net/wp-blog' --user="$SCOUT_ADMIN" \
  --skip-plugins --skip-themes eval '
if (!current_user_can("manage_options") || !current_user_can("edit_posts")) {
    WP_CLI::error("Administrator required.");
}
foreach (array("store", "drafting") as $part) {
    $file = WP_PLUGIN_DIR . "/editorial-scout/includes/class-editorial-scout-" . $part . ".php";
    if (!is_readable($file)) { WP_CLI::error("Installed plugin class file unavailable; stop and verify the installation path."); }
    require_once $file;
}
if (!method_exists("Editorial_Scout_Drafting", "failure_stage")) {
    WP_CLI::error("Installed version lacks safe stage extraction; stop without upgrading or retrying.");
}
$job = Editorial_Scout_Drafting::job(4);
$stage = $job["context"]["error_stage"] ?? null;
$safe = Editorial_Scout_Drafting::failure_stage(new WP_Error("diagnostic", "", array("stage" => $stage)));
WP_CLI::line($safe ?: (($stage === null || $stage === "") ? "not_recorded" : "unrecognized"));
'
```

Do not run `wp cron event run`, prepare/write actions, journal updates or resets.
A missing class/file is a reason to stop, not install/upgrade anything. A missing
journal produces `not_recorded`; this command deliberately does not print row
availability or distinguish missing/corrupt/old journals.

**Bootstrap boundary:** the diagnostic itself has no write or network operation.
WP-CLI skips regular plugins/themes here, avoiding Editorial Scout startup hooks;
standard WP-CLI bootstrap also suppresses normal cron spawning (verified in the
local WP-CLI Runner). This does not change `wp-config.php` or saved schedules.
WordPress still loads `wp-config.php`,
MU plugins and drop-ins. Site-specific bootstrap code may have its own side effects
or output, outside this diagnostic's control. If those are unknown, ask the host
administrator to review them first. Do not interpret a warning or extra output as
an identifier or share it without review. A read-only database account adds a
separate enforcement boundary if the host already provides such an environment;
do not change staging credentials for this task.

`tools/diagnose-writing-stage.php` is a development-only helper for `wp eval-file`
with an already active plugin. It requires WP-CLI and both administrator
capabilities; it prints only an allowlisted identifier or fixed absence marker.
It is not a web endpoint and is excluded from the runtime ZIP. The isolated inline
procedure above is preferred because it requires no helper deployment and skips
normal plugin startup. Local tests cover known, missing, unrecognized and
unauthorized cases; private canaries must never appear in output.

## Findings reproduced with synthetic provider responses

`tests/writing-validation-diagnosis.php` exercises the real preparation, consent,
mocked provider bridge and durable stage recording using a new disposable fixture,
not the staging commission. These are characterization regressions of 0.8.2's
current behavior, not assertions that the policy is always desirable.

| Synthetic response | Recorded stage | Assessment |
| --- | --- | --- |
| Verification note uses double quotation marks around a descriptive term | `claims` | `plain()` prohibits double quotes in every field, including non-public verification notes. The instructions prohibit quotations in **article text**, so this metadata rejection is broader than the stated rule. |
| Introduction has 1,201 characters | `field_text` | Local limit is 1,200; the provider schema only says string and instructions omit this character ceiling. Schema-valid, ordinary writing can fail locally. |
| Section heading starts with `1.` while the supplied notes have no digit 1 | `unsupported_number` | The numeric check treats structural numbering as an unsupported factual number. Do not disable factual-number checking globally. |
| A ready article has a supported note plus an opinion note explicitly saying independent verification is missing | `claim_coverage` | Local rules require a separate note **kind** `verify` or `unavailable`. The instructions ask for missing verification but never explicitly state the mandatory kind combination. Keep that substantive requirement and clarify it. |
| A tag contains a quoted descriptive term | `tags` | The same quotation ban applies to metadata; its constraints are not explicit in the schema/instructions. |
| One completed response message contains two output-text blocks that concatenate to the same valid article JSON | `response_envelope` | Parser insists on exactly one item and one block. This is a possible envelope incompatibility, not proof the fixed current model emitted this shape. |

The reported headline's en dash and **curly single quotes** around ‘Garden’ pass
the current text validator. They are not the prohibited double quotation marks.
The supplied title alone is not evidence of the cause.

## Other constraints reviewed

Local limits are headline 180, introduction 1,200, excerpt 400, section heading
160, section text 2,000, claim text 800 and tag 60 characters. There are also caps
of six sections, twelve notes, five tags, four references per note and 30,000
encoded result bytes. The schema specifies shape/types/required fields, without
most local size or cardinality constraints. Instructions specify some counts but
not the full contract. Aligning provider guidance with local validation is needed;
only use schema constraints supported by this fixed provider/model's strict schema
subset.

The fixed model is `gpt-4.1-mini-2025-04-14`. Output is capped at 4,096 tokens.
Completed status, JSON content type, body size, usage accounting, one message/text
block and JSON decoding are checked before article validation. `response_envelope`
currently conflates incomplete/token-limited results, bad usage, body format and
unsupported output shape. Do not increase budgets or loosen envelope checks just
because the message is generic. JSON shape, exact keys, source references,
verification coverage, unsafe markup/control characters, unsupported numbers and
copying checks remain important. The copying heuristic is conservative and is not
proof of copyright infringement or originality.

## Smallest correct next step and conditional fixes

1. **Read the existing stage first.** A read-only “Show safe validation check” UI
   could expose the same existing enum to an authorized administrator, without an
   API call or journal mutation. Missing stages must say “not recorded”. Mapping
   all existing stages to plain-English messages is a small observability fix,
   independent of any change to acceptance rules.
2. If stage is `claims`/`claim_coverage`/text-related, align instructions with the
   exact local requirements: mandatory supported plus verify/unavailable note
   kinds for ready articles, full length/count limits, quote restrictions and no
   numbered headings. Keep factual grounding and independent-verification rules.
   An instruction/schema alignment is preferable to dropping safety checks.
3. If diagnostics specifically confirm harmless quotation marks only in private
   claim notes/tags, consider separating metadata plain-text rules from the public
   article's quotation policy. Keep escaping, markup/shortcode/control/URL
   restrictions, length/type checks, source IDs, claim coverage, numeric grounding
   and copying protections. Do not broadly accept arbitrary response text.
4. If `unsupported_number`, distinguish structural headings only after a narrow
   regression-backed policy decision; retain checks on factual numbers in public
   text. If `response_envelope`, determine a more specific safe reason before
   considering a bounded parser that accepts supported metadata/multiple text
   blocks. Do not discard unknown content or silently accept incomplete responses.

**No runtime fix is applied by this investigation.** Any changed writing rules
must use a newly prepared/reviewed request and fresh explicit consent, not replay
commission #4's token or rewrite its failed journal. No automatic paid retry,
refund assumption, native post insertion or publishing is appropriate.

## If the historical stage is unavailable

For future requests, propose a separately enabled, administrator-controlled safe
diagnostic recording only fixed validation version/stage/reason/field enums
(for example `claims` / `quote_character` / `claim_text`). An envelope failure
could record a fixed reason such as `incomplete`, `usage`, `content_type` or
`text_block_count`. Do not record raw output, research excerpts, profile text,
credentials, provider error bodies, request tokens or arbitrary error messages.

Enabling diagnostics must not dispatch or retry a request. Diagnostic collection
must not alter consent, allowance reservation, cooldown, one-shot dispatch,
journal recovery or native WordPress ownership. Existing generic errors without
such information remain historically unclassified. This proposed finer-grained
opt-in diagnostic is not implemented or deployed here.

## Validation evidence

The full disposable integration suite passed 863 assertions on WordPress 7.1.3 /
PHP 8.3.35, using mocked provider responses. The original four targeted extractor checks passed. After narrowing helper output
to the stage alone, the standalone `tests/diagnostic-extractor.php` also passed
eight allowlist/absence cases and unauthorized access denial before any database
read, using the actual Drafting read/allowlist methods and a read-only database
stub. These checks require no WordPress installation or provider access.
The investigation PHP files passed syntax checks, and `git diff --check` passed. The
integration log is saved as `artifacts/writing-diagnosis-integration.log`.
Browser tests, remote CI and Plugin Check were not rerun for this development-only
investigation; no runtime implementation changed. Production/runtime plugin files remain byte
identical to `3bc330c`; no new release ZIP or deployment is required to review these
findings. Existing commission, journal, post and metadata retention assertions
continue to run around the synthetic cases.
