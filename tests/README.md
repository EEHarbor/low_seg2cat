# Low Seg2Cat tests

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
