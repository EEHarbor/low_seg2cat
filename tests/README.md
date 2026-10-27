# Low Seg2Cat tests

The configured suite covers ordinary add-on behavior using finite EE collaborators and the original add-on source. The shipped PHAR remains available for normal inheritance and loading. Shared Foundation classes, archive/loader/compilation coverage, synthetic fixture/runner contracts and impossible caller states are excluded; Foundation owns its own tests.

Download the pinned PHPUnit 9.6.38 runner with `tests/.phpunit/download.sh`, then run an explicit Herd binary directly:

```sh
'/Users/tomjaeger/Library/Application Support/Herd/bin/php74' \
  -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
  tests/.phpunit/phpunit-9.phar -c phpunit.xml
```

Use the same command with php80, php81, php82, php83, php84 and php85. No coverage or archive inspection is needed. The direct-access guard uses a finite isolated PHP child.

The practical cleanup was checked on all seven runtimes on 2026-10-27: 33 tests and 117 assertions each. Each run reports four errors and one failure for unchanged source defects: three pre-2.7 upgrade cases omit the required settings argument, the documented false constructor input reaches an array-only method, and failed settings persistence still shows success. These remain visible; this is not a passing compatibility gate.

Retained behavior covers category/segment variables, filtering, pagination suffixes, templates/hooks, settings forms and persistence, lifecycle, metadata/language, loose views and the request guard. Removed null per-site settings were malformed persisted structures, and over-limit URI fixtures bypassed the EE URI parser's rejection.

Historical coverage/status/evidence files describe earlier campaign snapshots and are retained as history. Their removed collector scripts and prior test counts do not describe the current configured suite.
