# Low Seg2Cat PHPUnit campaign

Status: **incomplete, source and coverage blockers retained**. Tests only; no production fixes.

Baseline `38a3d826df9b2ef2fbf96eca08efce5211386e68`; branch `unit-tests-2026`; origin `git@github.com:EEHarbor/low_seg2cat.git`. The clean develop checkout was freshly fetched and successfully pulled with `git pull --ff-only origin develop`; HEAD/origin parity was `0 0`. No other worktree or existing unit-tests branch was present. Network commands needed sandbox escalation after ordinary DNS failures. Production `low_seg2cat/` remains byte-for-byte unchanged against the pinned baseline.

## Scope and tooling

`SOURCE_INVENTORY.json` inventories six loose PHP files, 22 PHP archive members, and the executable PHAR stub, including constructors, closures, abstract declarations, templates and language code. The five executable stub method declarations are inventoried independently of coverage graphs. Shipped archives are executed directly. Compilation checks use temporary byte-identical copies, compare SHA-256 before compiling, and never rebuild the PHAR. The original repository had no suite or PHPUnit harness; its baseline coverage was unmeasured, not a fabricated zero/100% report.

Preflight now reports `PRECHECK_STATUS=ok` with `PHPUNIT_COMMAND=./tests/.phpunit/run-phpunit.sh -c phpunit.xml`. Set `PHP_BINARY` to an explicit Herd executable. PHPUnit 9.6.38 is selected consistently on all seven runtimes; its SHA-256 is checked by the launcher. The download script also pins the skill's optional PHPUnit 10.5.66 and 11.5.57 tools. No production Composer dependencies or global PHP/Herd settings changed.

Native fieldtype loading additionally reads the unchanged EE parent and its trait from `EE_SYSTEM_PATH` (default `/Users/tomjaeger/Sites/ee_repo_2/system/` in this checkout). The system and legacy paths use those original files. The installer fallback copies the parent byte-for-byte into a disposable installer layout and checks SHA-256 before execution; its original trait remains read-only. Current external provenance is EE commit `ad6358a2b67db30cd2286fa39711caa975aceee1`, clean for both dependency files; parent SHA-256 `d231220422932ed1139d92ade580d6dee9621635c3b5b22a1ddc4f68534cdb49`, trait `20431c4d7373c059f5531498c66c94354745d16bc9cfbe2aafd01cec7cde87f6`. Each native coverage context records these hashes. These probes load actual EE declarations without booting the full application.

The diagnostic handler throws for E_ALL source and test-harness diagnostics even when hidden by `@`. A sentinel test verifies suppressed E_USER_DEPRECATED is detected. Native compilation is checked in fresh interpreters; subprocesses inherit the exact PHP binary and strict settings. Native probes receive unique TMPDIR/TMP/TEMP and sys_temp_dir values. Coverage children receive the matching Xdebug extension and coverage mode, and each compiled graph is retained independently.

## Latest complete matrix

Command: `python3 tests/scripts/matrix.py`. The script runs the complete configured suite, creates a unique report directory, rejects missing/zero-test JUnit reports, runs all versions after failures, and exits nonzero when any runtime is red.

| Herd PHP | Cases | Assertions | Errors | Failures | Warnings | Risky | Skipped | Incomplete |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 7.4.33 | 194 | 1087 | 11 | 18 | 0 | 0 | 0 | 0 |
| 8.0.30 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |
| 8.1.33 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |
| 8.2.30 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |
| 8.3.28 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |
| 8.4.15 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |
| 8.5.0 | 194 | 1013 | 11 | 20 | 0 | 0 | 0 | 0 |

All 29 compilation units passed under each version. Errors/failures below are unchanged-source regressions. No PHPUnit/harness deprecation was present in these logs. The shorter assertion count on PHP 8 reflects the two EE2 datasets aborting at a source ValueError. See `evidence/matrix.json` for exact commands and runtime results, and `evidence/matrix-cases.json` for every error/failure identity and type. Complete local logs are in ignored `tests/reports/matrix-fg1a7c8c/`.

## Behavior covered

Settings default/site merging, sorting/filtering category groups, form field variants and posted persistence; category name/path formatting, site/group filtering, URI pattern and pagination handling, placeholders, unmatched/last segments, global-variable precedence and original URI preservation; chained template-hook results and once-only state; lifecycle registration/upgrades/deletion. Native PHAR coverage exercises direct-access/missing-archive guards, OPcache permission validation where available (the installed PHP 7.4 has no OPcache extension), native modern/legacy/installer fieldtype parent loading and predefined version constants, CP-only constructors/menu callbacks, legacy extension-only deletion/module-group uninstallation, base lifecycle aliases, module parameters, plugin/tab construction, fieldtype contexts/channel resolution/settings, installer operations, control-panel license displays/submissions and model pagination, all modern bridges' URLs/settings/caches/navigation/view/parser/helper delegates, EE2 routes/cache/session shapes, bridge selection for EE2-7, license schema validation/encrypted footer/idempotency, static and real-filesystem caches (missing files, unwritable parent/module directories, empty clearing and nested directory preservation), both license templates, setup/language files and namespace loading.

Collaborator shapes, including empty/null/numeric/cached DB rows, boolean mutation outcomes, fresh query builders, and config/input fallbacks, were checked against `ee_repo_2/system/ee/legacy/core/URI.php`, Model Query Builder and Library Data Collection, DB_result, Config/Input, and the app.setup.php database service factory. The DI database factory retains its own original connection prototype independently of the loaded legacy `db` name: removing that name still permits fresh queries, and replacing it does not replace the DI connection or its fixture records. Separate removal/replacement vectors verify that distinction. The loaded legacy facade registry also follows real `ExpressionEngine/Legacy/Facade.php` set/remove/get/has semantics, separately from DI services: replacing an existing name throws, removal permits registration, null remains loaded, and session registration initializes cookies. Tests prove modern builder operations and legacy cloned queries leave the existing legacy query/cache unchanged. Historical key/value settings rows and empty historical results are tested in EE2 and every separately shipped modern bridge; EE2 persistent cache and session cache operations run independently of its defective URL construction. License cases include the 50-character boundary, missing ignore checkbox and absent duplicate-site title. Top-level pagination exercises its empty-URL fallback. Anonymous temporary output files avoid stdout/stderr pipe deadlocks; a 200000-byte stderr regression passes. License submission probes use EE's terminating redirect contract. Filesystem caches and extraction probes run only in disposable paths.

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
| Ordinary persistent-cache request-memory reuse | PHAR `Conduit/PersistentCache.php:21,79` | Writes populate StaticCache under the SHA1 key, while reads look up the raw string as already processed. After a successful write and removal of the disk file, the intended cached array is lost as FALSE. |
| Navigation class loading | PHAR `Conduit/McpNav.php:7` | `@include_once` references absent `low_seg2cat/Conduit/McpNav.php`. |
| No-phar-stream fallback | executable PHAR stub `:177` | Repeated `@mkdir` on an existing directory emits a warning during extraction. |
| Falsey static cache values | PHAR `Conduit/StaticCache.php:18` | Five isolated datasets lose stored 0, "0", empty string, null and empty array as FALSE; FALSE itself round-trips. |
| Compound zero static-cache key | PHAR `Conduit/StaticCache.php:96` | `array("site", 0)` collides with the distinct key `array("site")` and overwrites its value. |
| Already-loaded modern EE service replacement | PHAR `Bridge/EE3.php`, `EE4.php`, `EE5.php:346` | `overwriteEEClass` calls facade `set()` without removing the loaded entry; the real facade rejects replacement (three datasets). The fixture-origin RuntimeException represents this real collaborator contract. |
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

The latest raw branch run used PHP 8.2.30 with Xdebug, all 194 cases, and retained the expected 11 errors/20 failures. `evidence/coverage-graphs.json` maps all 41 independently compressed raw source graphs to their SHA-256 and runtime/context. `branch-report.py` retains basic blocks separately from `out`/`out_hit` exits; block hits alone never close a branch. Synthetic VM exit edges remain explicitly identified, with their known zero-hit limitation. Full path coverage is not a target; path enumeration is omitted from retained graphs. Source files absent from a graph remain **unmeasured**, not 100%. Different subprocess/context graphs are never merged.

The skill method report was run with `--target low_seg2cat --include-magic` against a freshly generated Clover file. `evidence/skill-method-lines.json` retains its exact method rows. Its `branch_percent: null` means unknown; its `at_target` labels are line-only output and do not establish campaign completion. Main extension methods show 100% measured lines except `update_extension`, 14/18 (77.78%): the missing-argument regression prevents the legacy path completing. `js()` has zero Clover executable lines despite behavioral string assertions; do not call 0/0 branch proof.

Parent raw graph: main extension 162/163 lines including procedural code, 129/144 recorded non-synthetic outcomes; autoloader 27/32 lines, 24/40 outcomes. Modern EE3/4/5 bridges are respectively 181/181, 180/180, 180/180 parent lines and 158/171 outcomes each. PersistentCache is 61/70 parent lines and 38/42 outcomes. Separate native child graphs retain the CP/legacy fieldtype, legacy settings/cache, themes-constant, OPcache and bootstrap layout paths; their incompatible compiled graphs are never combined into an aggregate percentage. Their separately shipped code has not been conflated. Every exact uncovered line and edge is available by running `branch-report.py` on each retained graph. From a different checkout, explicitly remap the recorded root:

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
- Independent healthy paths still require work, including malformed/nullable fieldtype settings, additional navigation combinations and archive-loader error conditions. Persistent-cache write failure and directory-creation failure paths remain unexecuted beyond their current external-helper/strict-diagnostic boundaries. They are gaps to investigate, not a blanket source blocker. No full-coverage milestone is claimed.

## Review and publication

Historical checkpoint `33395e3f9ed317f5d95a35ec741ed08880edf747` was independently approved by reviewer `tests_smart_switch` using exact baseline patch `77d6974fef6033f1c493ca4f18a20f53b97875ded0650cbf5347d6487e593361`, then published to origin/unit-tests-2026 with local/remote parity `0 0`. That checkpoint contained 144 cases and 20 raw graphs; its review and publication do not close the remaining campaign scope.

This follow-up adds 50 cases, corrects the facade registry contract, and refreshes all seven runtime results plus 41 independent raw graphs. The full seven-runtime matrix and fresh skill/Clover report were rerun after the final test changes. Random full-suite execution with seed `20261024` and all 18 configured test files individually under PHP 7.4/8.5 reproduced the same failure identities and discovery counts; local evidence is in `tests/reports/order-191-fup5hg2w/`. The first OPcache probe incorrectly assumed the installed PHP 7.4 supplied the extension; it was corrected to verify availability and the supported source path per runtime, then the full matrix was rerun. No test/harness diagnostic remains in the current logs. PHP 8.2 raw coverage is in ignored `tests/reports/coverage.X49LqR/`; fresh Clover/skill inputs are in `tests/reports/healthy-followup-clover/`. A directly preloaded request-cache vector now uses an actual SHA1 key with the processed-key flag; it tests direct cache interoperability rather than claiming normal PersistentCache set/get reuse. This inherited vector was strengthened during self-review before approval, and all seven suites plus raw/skill evidence were refreshed again. The raw child metadata identifies real-parent layout and OPcache contexts. Source and PHAR bytes remain unchanged.

Independent review found that the original DI fixture cloned the public legacy `db` property, so removing/replacing that name incorrectly affected query creation. The factory now retains its original connection fixture independently, and two new cases cover removal without replacement and replacement isolation. All seven complete suites and raw/skill evidence were refreshed after this correction. Review also required an explicit ordinary warm-cache regression: it writes a supported array through PersistentCache, removes only its disk record, and asserts the retained request-memory value. The unchanged raw-vs-hashed key defect keeps this test red. The separate passing processed-key interoperability vector is not used as proof that ordinary writes can be reused. All seven suites and raw/skill evidence were refreshed again. Independent exact-diff re-review is required before committing/publishing this follow-up. Full real-EE/database integration and complete actual branch coverage remain unproven. Continued healthy-source work remains authorized after this checkpoint.
