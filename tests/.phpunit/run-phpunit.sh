#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "$0")" && pwd)"
php_binary="${PHP_BINARY:?Set PHP_BINARY to an explicit Herd PHP executable.}"
expected="6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7"
actual="$(shasum -a 256 "$script_dir/phpunit-9.phar" | cut -d ' ' -f 1)"
if [[ "$actual" != "$expected" ]]; then
    echo 'Pinned PHPUnit 9.6.38 checksum mismatch.' >&2
    exit 2
fi
exec "$php_binary" -d error_reporting=-1 -d display_errors=1 "$script_dir/phpunit-9.phar" "$@"
