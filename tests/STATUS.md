# Low Seg2Cat PHPUnit campaign

Status: **incomplete, source and coverage blockers retained**. Tests only; no production fixes.

Baseline `38a3d826df9b2ef2fbf96eca08efce5211386e68`; branch `unit-tests-2026`; origin `git@github.com:EEHarbor/low_seg2cat.git`. The clean develop checkout was freshly fetched and successfully pulled with `git pull --ff-only origin develop`; HEAD/origin parity was `0 0`. No other worktree or existing unit-tests branch was present. Network commands needed sandbox escalation after ordinary DNS failures. Production `low_seg2cat/` remains byte-for-byte unchanged against the pinned baseline.

## Scope and tooling

`SOURCE_INVENTORY.json` inventories six loose PHP files, 22 PHP archive members, and the executable PHAR stub, including constructors, closures, abstract declarations, templates and language code. The five executable stub method declarations are inventoried independently of coverage graphs. Shipped archives are executed directly. Compilation checks use temporary byte-identical copies, compare SHA-256 before compiling, and never rebuild the PHAR. The original repository had no suite or PHPUnit harness; its baseline coverage was unmeasured, not a fabricated zero/100% report.

Preflight now reports `PRECHECK_STATUS=ok` with `PHPUNIT_COMMAND=./tests/.phpunit/run-phpunit.sh -c phpunit.xml`. Set `PHP_BINARY` to an explicit Herd executable. PHPUnit 9.6.38 is selected consistently on all seven runtimes; its SHA-256 is checked by the launcher. The download script also pins the skill's optional PHPUnit 10.5.66 and 11.5.57 tools. No production Composer dependencies or global PHP/Herd settings changed.

The diagnostic handler throws for E_ALL source and test-harness diagnostics even when hidden by `@`. A sentinel test verifies suppressed E_USER_DEPRECATED is detected. Native compilation is checked in fresh interpreters; subprocesses inherit the exact PHP binary and strict settings. Native probes receive unique TMPDIR/TMP/TEMP and sys_temp_dir values. Coverage children receive the matching Xdebug extension and coverage mode, and each compiled graph is retained independently.

## Latest complete matrix

Command: `python3 tests/scripts/matrix.py`. The script runs the complete configured suite, creates a unique report directory, rejects missing/zero-test JUnit reports, runs all versions after failures, and exits nonzero when any runtime is red.

| Herd PHP | Cases | Assertions | Errors | Failures | Warnings | Risky | Skipped | Incomplete |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 7.4.33 | 144 | 831 | 8 | 15 | 0 | 0 | 0 | 0 |
| 8.0.30 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |
| 8.1.33 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |
| 8.2.30 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |
| 8.3.28 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |
| 8.4.15 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |
| 8.5.0 | 144 | 757 | 8 | 17 | 0 | 0 | 0 | 0 |

All 29 compilation units passed under each version. Errors/failures below are unchanged-source regressions. No PHPUnit/harness deprecation was present in these logs. The shorter assertion count on PHP 8 reflects the two EE2 datasets aborting at a source ValueError. See `evidence/matrix.json` for exact commands and runtime results; complete local logs are in ignored `tests/reports/matrix-ogmw17ik/`.

## Behavior covered

Settings default/site merging, sorting/filtering category groups, form field variants and posted persistence; category name/path formatting, site/group filtering, URI pattern and pagination handling, placeholders, unmatched/last segments, global-variable precedence and original URI preservation; chained template-hook results and once-only state; lifecycle registration/upgrades/deletion. Native PHAR coverage exercises base lifecycle aliases, module parameters, plugin/tab construction, fieldtype contexts/channel resolution/settings, installer operations, control-panel license displays/submissions and model pagination, all modern bridges' URLs/settings/caches/navigation/view/parser/helper delegates, EE2 routes/cache/session shapes, bridge selection for EE2-7, license schema validation/encrypted footer/idempotency, static and real-filesystem caches, both license templates, setup/language files and namespace loading.

Collaborator shapes, including empty/null/numeric/cached DB rows, boolean mutation outcomes, fresh query builders, and config/input fallbacks, were checked against `ee_repo_2/system/ee/legacy/core/URI.php`, Model Query Builder and Library Data Collection, DB_result, Config/Input, and the app.setup.php database service factory. Tests prove modern builder operations leave the existing legacy query/cache unchanged. Anonymous temporary output files avoid stdout/stderr pipe deadlocks; a 200000-byte stderr regression passes. License submission probes use EE's terminating redirect contract. Filesystem caches and extraction probes run only in disposable paths.

## Confirmed source regressions

| Case | Source location | Evidence |
| --- | --- | --- |
| Legacy upgrade, flat/nested/pre-2.7 settings | `ext.low_seg2cat.php:585` | Three datasets call required-argument `save_settings()` without an argument. |
| Documented false constructor input | `ext.low_seg2cat.php:147,629` | FALSE reaches array-typed `get_site_settings`. |
| Persisted null site settings | `ext.low_seg2cat.php:637` | `array_merge` receives null; strict diagnostic on PHP 7.4, TypeError on PHP 8. |
| URI longer than configured maximum | `ext.low_seg2cat.php:521` | Last category reads undefined `segment_3_category_id` after two placeholders. |
| Static plugin metadata | PHAR `Base/Pi.php:30` | Static `info()` accesses `$this`. |
| Legacy registration/unregistration aliases | PHAR `Base/Ext.php:189,194` | Explicit hook/options are overwritten by default assignments. |
| Missing field setting | PHAR `Conduit/GlobalFieldSettings.php:21` | `@` hides an undefined-key warning. |
| Documented array persistent-cache key | PHAR `Conduit/PersistentCache.php:21`, `StaticCache.php:18` | Reading an actually stored array key passes an array as a processed array offset. |
| Navigation class loading | PHAR `Conduit/McpNav.php:7` | `@include_once` references absent `low_seg2cat/Conduit/McpNav.php`. |
| No-phar-stream fallback | executable PHAR stub `:177` | Repeated `@mkdir` on an existing directory emits a warning during extraction. |
| Falsey static cache values | PHAR `Conduit/StaticCache.php:18` | Four isolated datasets lose stored 0, "0", empty string, and null as FALSE; FALSE itself round-trips. |
| Static cache deletion result | PHAR `Conduit/StaticCache.php:55` | Successful removal always returns FALSE instead of documented success. |
| Failed base extension insertion | PHAR `Base/Ext.php:123` | `registerExtension` returns TRUE after the database reports failed insert. |
| Failed settings persistence | `ext.low_seg2cat.php:296,307` | A success alert is emitted despite a failed database update. |
| Mapped navigation page title | PHAR `Bridge/EE3.php`, `EE4.php`, `EE5.php:96` | Active mapping stores a header object as `cp_page_title` rather than the mapped label (three datasets). |
| EE2 >=2.6 URL construction on PHP 8 | PHAR `Bridge/EE2.php:47` | Invalid comparison operator `=<` throws ValueError (two datasets). |

No source regression is skipped, suppressed, or made green by expecting the defective result. PHP 7.4 accepts the invalid comparison operator without the PHP 8 ValueError; those two legacy datasets therefore finish on 7.4.

## Coverage proof and remaining gaps

Run:

```sh
PHP_BINARY='/Users/tomjaeger/Library/Application Support/Herd/bin/php82' \
LOW_SEG2CAT_XDEBUG_EXTENSION=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-82-arm64.so \
tests/scripts/coverage.sh
```

The latest raw branch run used PHP 8.2.30 with Xdebug, all 144 cases, and retained the expected 8 errors/17 failures. `evidence/coverage-graphs.json` maps independently compressed raw source graphs to their SHA-256 and runtime/context. `branch-report.py` retains basic blocks separately from `out`/`out_hit` exits; block hits alone never close a branch. Synthetic VM exit edges remain explicitly identified, with their known zero-hit limitation. Full path coverage is not a target; path enumeration is omitted from retained graphs. Source files absent from a graph remain **unmeasured**, not 100%. Different subprocess/context graphs are never merged.

The skill method report was run with `--target low_seg2cat --include-magic` against a freshly generated Clover file. `evidence/skill-method-lines.json` retains its exact method rows. Its `branch_percent: null` means unknown; its `at_target` labels are line-only output and do not establish campaign completion. Main extension methods show 100% measured lines except `update_extension`, 14/18 (77.78%): the missing-argument regression prevents the legacy path completing. `js()` has zero Clover executable lines despite behavioral string assertions; do not call 0/0 branch proof.

Parent raw graph: main extension 162/163 lines including procedural code, 129/144 recorded non-synthetic outcomes; autoloader 27/32 lines, 24/40 outcomes. Modern EE3/4/5 bridges are respectively 179/181, 178/180, 178/180 parent lines and 151/171, 151/171, 152/171 outcomes respectively. Their separately shipped code has not been conflated. Every exact uncovered line and edge is available by running `branch-report.py` on each retained graph. From a different checkout, explicitly remap the recorded root:

```sh
gzip -dc tests/evidence/main.json.gz > /tmp/low-seg2cat-main.json
python3 tests/scripts/branch-report.py /tmp/low-seg2cat-main.json \
  --recorded-root /Users/tomjaeger/Sites/add-ons/low_seg2cat \
  --output /tmp/low-seg2cat-methods.json
```

The original root is also recorded in each graph's metadata. A graph with zero inventoried source matches now fails rather than silently succeeding with every source unmeasured.

Blocking/gap examples:

- Strict `McpNav.php:7` loading fails before executable nav methods can run. The complete declaration inventory remains in scope and unexecuted.
- Static `Pi::info()` cannot complete while `$this` is used in a static method; the remaining metadata construction lines are unreachable after that error.
- Legacy upgrade paths cannot continue beyond the missing argument, and EE2 >=2.6 URL paths cannot continue past the invalid operator on PHP 8.
- PHAR fallback extraction stops at its strict hidden filesystem warning. Its later extraction/error/web-serving branches remain incomplete.
- Native source includes compile into separate `{main}` graphs whose branch hit data can remain zero despite executed line data. Both raw facts are retained; no artificial 100% is assigned.
- PHP <5.3.6 branches in bundled `Mcp::paginateModel`, Windows-only PHAR stub paths on macOS, and false branches guarded by the shipped constant `FluxCapacitor::L === true` cannot be exercised in this required runtime/platform matrix.
- Private invariant collections and normalization can make zero-iteration outcomes unreachable without artificial production-state mutation. These are retained as uncovered outcomes, not removed from metrics.
- Remaining healthy-path branch gaps (including some installer/EE2/fieldtype CP paths and persistent cache I/O-failure paths) remain explicitly incomplete. No full-coverage milestone is claimed.

## Review and publication

Independent reviewer `tests_smart_switch` approved final exact patch `77d6974fef6033f1c493ca4f18a20f53b97875ded0650cbf5347d6487e593361` against the pinned baseline. The reviewer independently replayed every full runtime, random order on PHP 7.4/8.5, focused files, zero-test rejection, all 29 source/compilation units, all 20 graph hashes/root mappings and five stub declarations. A fresh PHP 8.2 parent graph exactly matched retained evidence after root normalization. No actionable test/harness/security/isolation finding remains. Source defects, incomplete actual branches/healthy paths, and unproven full real-EE/database integration remain explicit limitations.

All fixture-contract, falsey-result, subprocess-output, PHAR pinning, evidence-portability and stub-inventory review findings were resolved before approval. Scoped code commits:

- `ff040a1`: pinned strict harness, fixtures, source inventory, compilation/process/database checks.
- `649c2f3`: extension settings/category/hook behavior and intentional source regressions.
- `6607658`: shipped PHAR/procedural behavior and intentional bundled regressions.

The union of these commits plus the reviewed evidence/documentation was verified byte-for-byte against the approved patch before this publication-bookkeeping update. Origin refs were refreshed; origin/develop remains the pinned baseline, and origin/unit-tests-2026 was absent. Publication follows the documentation/evidence commit; verified remote evidence will be recorded after pushing. This is a reviewed source-red checkpoint, not full coverage completion.
