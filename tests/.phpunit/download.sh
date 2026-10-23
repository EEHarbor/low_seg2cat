#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "$0")" && pwd)"
versions=(9.6.38 10.5.66 11.5.57)
hashes=(6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7 42bcac97bbf9fb1aecf5a7d6a1b37123a11e7e295458978f0e268999f6d9e50f c704fdd37c6c6ab7adb386f31b4e186cd51156801fa9028b5ea8099a2cd7f106)
for index in 0 1 2; do
    version="${versions[$index]}"
    target="$script_dir/phpunit-${version%%.*}.phar"
    download="$(mktemp "$script_dir/.download.XXXXXX")"
    trap 'rm -f "$download"' EXIT
    curl --fail --location --silent --show-error "https://phar.phpunit.de/phpunit-$version.phar" -o "$download"
    actual="$(shasum -a 256 "$download" | cut -d ' ' -f 1)"
    if [[ "$actual" != "${hashes[$index]}" ]]; then
        echo "Checksum mismatch for PHPUnit $version" >&2
        exit 2
    fi
    mv "$download" "$target"
done
