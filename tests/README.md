# Low Seg2Cat tests

Download pinned local PHPUnit tools with `tests/.phpunit/download.sh`, then run the full matrix with `python3 tests/scripts/matrix.py`. To run one explicit runtime:

```sh
PHP_BINARY='/Users/tomjaeger/Library/Application Support/Herd/bin/php82' \
tests/.phpunit/run-phpunit.sh -c phpunit.xml
```

The complete suite intentionally retains failing regressions for unchanged shipped defects. Read `STATUS.md` before interpreting a red result. Focused selections are useful during development and are not full matrix proof. The launcher fails on empty selections and checks the pinned PHAR hash.

`SOURCE_INVENTORY.json` records all shipped PHP, including archive members and the executable stub. Regenerate it with an explicit runtime (`php82 tests/scripts/inventory.php`) only when intentionally updating a pinned source baseline. Production source and archives are immutable during this campaign.

Branch collection uses `tests/scripts/coverage.sh` with `PHP_BINARY` and `LOW_SEG2CAT_XDEBUG_EXTENSION` set explicitly. It creates fresh local reports, retains independent child graphs, and returns the actual suite status. `branch-report.py RAW.json --output METHODS.json` summarizes raw Xdebug blocks and outgoing edges while retaining unknown/uncovered source. It never treats Clover-only line data as branch proof.

Retained evidence can be inspected in a different checkout with `branch-report.py RAW.json --recorded-root /Users/tomjaeger/Sites/add-ons/low_seg2cat --output METHODS.json`; see STATUS.md for decompression and the original-root metadata. Zero matched source files fail reporting.
