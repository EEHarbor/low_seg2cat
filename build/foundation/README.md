# Scoped Low Seg2Cat Foundation build

This source tree begins with the 22 processed members shipped in `low_seg2cat.phar` at tests baseline `58fa1a9abf8b23faee93c5561c36e2172101480d`. That artifact contains Foundation 4.10.0. The shared PacketTide tools checkout at `11a2ebabc92280b424d7aac76e78ac689eb29df7` supplies the established `Phar::buildFromDirectory` process; its Foundation 4.11 templates match 18 of these 22 processed members. The original Version implementation, generated validation JavaScript, license flag=true and license view remain byte-identical. No shared tools or broad gulp task is executed.

Selected cache, bridge, alias and channel-resolution fixes and the length-aware serialized-data decoder adapt the owner's approved Fieldpack implementation at `e1b07ae668c405ff214d006f718d3f56eb07c571`. Low Seg2Cat retains its original missing-module null result, other false-like toolbar defaults, MCP property visibility, content-type matching and Foundation 4.10 licensing behavior. `SerializedData.php` is one new namespaced archive member so both normal and no-stream loaders distribute it with its callers. Unexpected native/callback diagnostics remain observable; only exact known parser errors at the actual unserialize call become malformed-storage fallback. Valid PHP 7.4 serialized prefixes and legacy escaped strings remain readable.

Run with an explicitly selected PHP interpreter:

```sh
php -d phar.readonly=0 -d error_reporting=-1 -d display_errors=stderr -d display_startup_errors=1 build/foundation/build.php
```

The builder writes only this repository's archive after checking all 23 expected members and their processed bytes. Original raw 0755/0644 modes, compression, metadata, alias and SHA-256 signature algorithm are preserved; the new helper uses 0644. Normal archive build timestamps change. `PhpPackagingTest` reads raw manifest flags because a read-only Phar view masks write bits, checks the original Version hash and runs a complete build in an owned disposable directory on each selected runtime.

The fallback stub preserves ordinary extraction and serving behavior while guarding existing directories, removing stale children before their parents, and keeping child/root links from being traversed. The configured temporary base is canonicalized; an existing or dangling link at its managed `pharextract` container terminates extraction with an explicit message and leaves its target untouched. A linked add-on root is replaced before resolution. Actual go controls and HTTP sibling-path controls exercise the distributed stub. LEN is recalculated after changes.

After rebuilding, regenerate the shipped-source inventory with `php tests/scripts/inventory.php > tests/SOURCE_INVENTORY.json`, then run the complete strict matrix. Original source/member manifests and readable source/stub diffs remain outside the repository in `/private/tmp/php-compat-2026-01a16ed8/low_seg2cat/`.
