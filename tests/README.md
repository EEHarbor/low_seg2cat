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

Retained evidence can be inspected in a different checkout with `branch-report.py RAW.json --recorded-root /Users/tomjaeger/Sites/add-ons/low_seg2cat --output METHODS.json`; see STATUS.md for decompression and the original-root metadata. Zero matched source files fail reporting.

Native stub vectors additionally exercise zlib and bzip2 payloads through the original PHAR helper methods; both extensions are present in all seven verified Herd runtimes. Normal archive loading exposes the early-bound extraction helper class without bypassing the separately failing fallback loader. Original-manifest extraction is checked against PHP’s native PHAR reader; temporary payload streams never rebuild the archive.

Owned native PHP probes have a 15-second deadline with direct-child TERM/KILL/reaping and retain prefix output on timeout. Harness-only stall controls load strict support without shipped source and emit no source coverage graph. The seven verified Herd runtimes supply pcntl/posix for termination controls.
