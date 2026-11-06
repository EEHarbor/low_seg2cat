# Low Seg2Cat tests

## Test scope as of 2026-11-05

The configured suite now tests add-on behavior and its diagnostic harness. Direct shared Foundation class, PHAR member/stub, and archive-template tests were removed because Foundation maintains its own suite. Add-on cases that use the real bundled dependency, loose templates, normal loose-source loading boundaries, source identity inventories, and strict diagnostics remain. No production code or PHAR was changed by this cleanup.

The older coverage descriptions, counts, and dependency failures below are historical evidence from the broader suite. They do not describe the current test scope. Read fresh complete checked-launcher results for current validation; retained failures remain visible. This cleanup does not certify production compatibility or reapprove inherited production changes.

## Historical checkpoint notes

Download pinned local PHPUnit tools with `tests/.phpunit/download.sh`, then run the full matrix with `python3 tests/scripts/matrix.py`. To run one explicit runtime:

```sh
PHP_BINARY='/Users/tomjaeger/Library/Application Support/Herd/bin/php82' \
tests/.phpunit/run-phpunit.sh -c phpunit.xml
```

Four native fieldtype probes also require a read-only ExpressionEngine source tree. Set `EE_SYSTEM_PATH=/path/to/expressionengine/system/`; this checkout defaults to the neighboring `/Users/tomjaeger/Sites/ee_repo_2/system/`. The actual `EE_Fieldtype.php` and `ModifiableTrait.php` declarations are loaded directly (the installer-layout parent copy is byte-identical and disposable). Their runtime SHA-256 provenance is recorded in child coverage metadata. No application boot or database connection is performed. Private review snapshots must pass `EE_SYSTEM_PATH` explicitly.

The complete suite intentionally retains failing regressions for unchanged shipped defects. Read `STATUS.md` before interpreting a red result. Focused selections are useful during development and are not full matrix proof. The launcher checks the pinned PHAR hash and validates a fresh nonempty JUnit report after execution, including early bootstrap exits. It uses PHPUnit’s own option parser, supports help/listing and both caller `--log-junit` forms, and preserves source-red exit codes. Coverage uses the same report validator before publishing parent raw/metadata; rejected or inspection-only runs leave no accepted parent measurement.

`SOURCE_INVENTORY.json` records all shipped PHP, including archive members and the executable stub. Regenerate it with an explicit runtime (`php82 tests/scripts/inventory.php`) only when intentionally updating a pinned source baseline. Production source and archives are immutable during this campaign.

Branch collection uses `tests/scripts/coverage.sh` with `PHP_BINARY` and `LOW_SEG2CAT_XDEBUG_EXTENSION` set explicitly. It creates fresh local reports, retains independent child graphs, and returns the actual suite status. `branch-report.py RAW.json --output METHODS.json` summarizes raw Xdebug blocks and outgoing edges while retaining unknown/uncovered source. It never treats Clover-only line data as branch proof.

The six conditional constructors in `Bridge/EE.php` share one native graph name and cannot be attributed individually. Their line/block/outcome metrics stay unknown, with the shared graph retained under `raw_graph_metrics` and every declaration listed independently. Run `python3 tests/scripts/test_branch_report.py` for the seven synthetic provenance/attribution controls; those graphs are never production coverage.

Retained evidence can be inspected in a different checkout with `branch-report.py RAW.json --recorded-root /Users/tomjaeger/Sites/add-ons/low_seg2cat --output METHODS.json`; see STATUS.md for decompression and the original-root metadata. Zero matched source files fail reporting.

Native stub vectors additionally exercise zlib and bzip2 payloads through the original PHAR helper methods; both extensions are present in all seven verified Herd runtimes. Normal archive loading exposes the early-bound extraction helper class without bypassing the separately failing fallback loader. Original-manifest extraction is checked against PHP’s native PHAR reader; temporary payload streams never rebuild the archive.

Entry-time E_ALL handling is installed before ordinary bootstrap/support loading and through process-local auto_prepend_file in the checked launcher. Native generated probes execute as owned script files because PHP ignores auto_prepend_file for -r code. Primary-view generated setup is loaded behind a minimal diagnostic gate before PHP compiles that setup. Per-case reinstallation keeps that unconditional policy, including nested generated files. Runtime diagnostics hidden by @ and fresh compilation warnings fail startup; rejected startup runs publish no parent raw coverage. These settings do not change global PHP configuration.

Owned native PHP probes have a 15-second deadline with direct-child TERM/KILL/reaping and retain prefix output on timeout. Harness-only stall controls load strict support without shipped source and emit no source coverage graph. The seven verified Herd runtimes supply pcntl/posix for termination controls.

The pinned parser normalizes forwarded CLI options and preserves a real end-of-options boundary. The owned JUnit path cannot be overridden by caller report options or positional arguments. String-zero minimum-version inspection remains non-executing. Retained coverage deliberately omits functions.paths; each index entry records this omission and a null path percentage. Equality comparisons cover the retained line/function/block/out/out_hit representation only.


`Support/PrimaryLegacy.php` measures the original archive `Bridge/EE.php` member through a byte-identical primary copy. Its minimal APP_VER 2.5.5 bootstrap loads the original AbstractBridge/EE2 definitions and a bounded get_instance() collaborator, without the ordinary test facade defining ee() first. Three cases verify fallback delegation to the current facade, preservation of an existing accessor, and configuration-based version discovery. The source constructor is not invoked in these contexts. Runtime/source hashes, Reflection function-file identity, strict diagnostics, exact auto-append completion and owned cleanup are checked; rejected early-exit/runtime/compile controls publish no graph. `evidence/legacy-accessor-contract.json` records the read-only EE accessor reference and the limited bootstrap context. This does not execute a complete EE2 request.


NativeArchiveAutoloadTest isolates the original PHAR index callback by unregistering only the loose loader in its owned child. Foreign/missing and cold/warm bundled-class lookups run from both the repository and an owned empty working directory; class Reflection paths and inventory hashes prove original member loading. The retained callback outcomes are separate from the included procedural main graph, whose zero branch counters remain unknown. See evidence/archive-autoload-contract.json for the two code/context/raw mappings.


NativeUpdaterCallbackTest executes skipped/current versions and matching/missing optional extension callbacks in one original compiled updater graph. The shared EE redirect fixture terminates through an owned sentinel, and the POST settings/index tests catch it only at their unit boundary; POST settings must not render a view or footer afterward. evidence/updater-cache-contract.json records actual EE caller/redirect hashes, unknown exception policy and the measured graph limits. Three modern cache cases cover unknown null/false/empty-array modes without service calls; their remaining switch edges stay open.

Owned probe cleanup restores newly owned directory permissions before descending, including deadline-interrupted 0000 nested directories, while preserving symlink targets. Two direct normal/deadline controls verify this boundary. `evidence/source-scope-audit.json` records the byte-based loose/archive mixed-PHP audit; no omitted executable unit was found.

Modern pagination forwarding uses a URL-factory protocol object and nullable rendered output. Seven guarded primary-definition controls establish the actual EE Url argument and zero-page return contract; they provide no add-on coverage or full EE rendering claim.

Current native stdout authority: checked main and native-child output is retained byte-for-byte before admission. Complete native plain/HTML PHP diagnostic frames must name an existing owned source file and valid line (or the actual inline command); those frames join the durable failure journal even when logging was temporarily disabled and the returned child result is ignored. Bare warning prose/markup is ordinary output. A deliberately printed complete native-shaped frame with matching owned source is indistinguishable and fails closed; no general text/HTML ban is used. Checked main attempts retain exact argv, PID, raw stdout/stderr and hash-bound terminal results beside the owned durable journal in checked-captures/checked-*; existing startup/log/finite controls and original Foundation failures remain unchanged.

The same checked-main recognizer covers both stdout and stderr; frame records retain the original channel. Native children already reject any stderr, and their verified inline-source recognition retains the supported separate/attached -r and --run spellings. Literal data and every original test/input/assertion remain unchanged.

Diagnostic frame bodies may span lines. Original complete channels and exact matched frame bytes (base64) are retained; no line-only message reduction is used. Checked-attempt journals and engine logs are snapshotted with their raw stream hashes before completion. Source filename aliases are matched to the actual command file, without resolving unrelated fixture targets.

## Original case-output capture

The checked runner verifies a separately generated PHPUnit TOOL copy before each launch. The original pinned `phpunit-9.phar`, add-on source, stock Foundation archive, and test/assertion bytes remain unchanged by this tool adaptation. `tests/.phpunit/prepare-output.php` derives only the original `Framework/TestCase.php` output-buffer boundary: same-line snapshots immediately after `ob_get_contents()` and, when a user output callback returns successfully, after its original assignment but before `ob_end_clean()`. The callback is invoked once with its original return, exception and cleanup order. Every actual TestCase subclass reaches that boundary, including native PHPUnit children.

The private append channel retains each unmodified buffer with its original/callback stage, active case/source bytes, PID, nonce, byte count and SHA-256. The checked Python parent joins those bytes after PHP exits, before accepting its status. Original output is neither filtered nor rewritten. Complete source-bound native framing is rejected even when a PHPUnit assertion or callback consumed it; bare Warning prose remains valid output. The existing native stream, stderr, startup, finite fault authorization and deadline checks remain active.

The generated tool is cached to avoid adding a PHP startup inside an existing native deadline. Each checked run revalidates the recipe and artifact hash and retains its own identity record. A cache miss uses one separately captured strict PHP preparation attempt; its full streams, PID and result remain in the checked capture. Generated artifacts and stamps are ignored tooling, with original/member/recipe/artifact hashes retained per invocation. The single-member seam design is independently adapted from slot03's current Channel Polls recipe; unrelated Matrix exporter/process changes and Foundation changes are excluded.

Earlier matrices remain historical source evidence. A renewed matrix is required after this capture-only change; stock Foundation errors are reported as existing full-suite failures, with no compatibility or publication approval inferred.

The previous cleanup matrices also contain two stale direct-entry tool-fixture bindings. Their complete original diagnostic, frame, stream and outcome fields are retained; the renewed catalog changes only copied diagnostic/bootstrap tool source hashes. Those historical failures are tooling failures and are not attributed to Foundation. All original profiles remain available alongside the source-bound replacements.
