#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
php_binary="${PHP_BINARY:?Set PHP_BINARY to an explicit Herd PHP executable.}"
xdebug_extension="${LOW_SEG2CAT_XDEBUG_EXTENSION:?Set LOW_SEG2CAT_XDEBUG_EXTENSION to the matching Herd Xdebug binary.}"
report_root="${LOW_SEG2CAT_REPORT_ROOT:-$repo_root/tests/reports}"
mkdir -p "$report_root"
report_run="$(mktemp -d "$report_root/coverage.XXXXXX")"
export XDEBUG_MODE=coverage
export LOW_SEG2CAT_COVERAGE="$report_run/raw.json"
export LOW_SEG2CAT_XDEBUG_EXTENSION="$xdebug_extension"
set +e
"$php_binary" -d "zend_extension=$xdebug_extension" -d xdebug.mode=coverage -d error_reporting=-1 "$repo_root/tests/scripts/coverage.php" -c "$repo_root/phpunit.xml" --no-coverage > "$report_run/phpunit.log" 2>&1
suite_status=$?
set -e
python3 "$repo_root/tests/scripts/branch-report.py" "$report_run/raw.json" --output "$report_run/methods.json"
for child in "$report_run/raw.json.children"/*.json; do
    [[ -f "$child" ]] || continue
    [[ "$child" == *.metadata.json ]] && continue
    python3 "$repo_root/tests/scripts/branch-report.py" "$child" --output "${child%.json}.methods.json" > /dev/null
done
printf 'Suite exit: %s\nReports: %s\n' "$suite_status" "$report_run"
exit "$suite_status"
