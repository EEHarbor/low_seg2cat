# Low Seg2Cat PHP compatibility validation

The compatibility branch starts from fetched `origin/unit-tests-2026` at `58fa1a9abf8b23faee93c5561c36e2172101480d`; the develop comparison is `38a3d826df9b2ef2fbf96eca08efce5211386e68`. All changes use the isolated compatibility worktree. The primary checkout's pre-existing modified and untracked experiments remain untouched, with status and byte manifests retained outside the repository.

The definitive initial matrix, `initial-v2`, ran all 311 configured cases across PHP 7.4.33, 8.0.30, 8.1.33, 8.2.30, 8.3.28, 8.4.15 and 8.5.0. All eight original production files matched the pinned baseline before and after it. Initial assertions/errors/failures were 2041/16/25 on 7.4, 1967/16/27 on 8.0, and 1964/19/27 on 8.1 through 8.5, with no other issue categories. Every version recorded 170 valid startup contexts. `initial-v1` retains a test-control output-shape setup mistake and remains preflight evidence.

The final strict matrix, `final-v2`, passes 342 tests and 2428 assertions on each of those seven runtimes, with zero errors, failures, warnings, risky, skipped or incomplete cases, exit zero and empty stderr. Explicit launcher/child flags use error_reporting=-1, display_errors=stderr and display_startup_errors=1. Actual startup receipts include interpreter identities and loaded/scanned INIs. Native fieldtype tests load unchanged actual EE parents/traits through the supported system, legacy and installer layouts using `/Users/tomjaeger/Sites/ee_repo_2/system/`.

The durable handler records diagnostics before throwing and rejects them at per-test teardown and process shutdown even when source catches the exception. Original caught test/provider/native controls falsely passed on PHP 7.4 and 8.5; the strengthened controls finish red with retained receipts. Permanent synthetic controls exercise checked test execution and provider discovery. A throw-only handler is insufficient. No unexpected source warning/deprecation is permitted to continue.

Loose-source changes retain documented false/null settings, leave last-segment placeholders empty when a URI exceeds the configured cap, and announce a settings save only after a successful database update. Legacy upgrades preserve original flat and multisite settings, including other sites and unknown/zero values, while adding the new hook. They no longer call the form-save routine without its required argument or read unrelated POST data. A failed required hook insertion leaves the old version/hook records retryable instead of advancing the database version. Actual EE code passes the same persisted settings to the constructor and settings_form; the Extension model's getter returns the native unserialized value. New regressions cover both that false initial value and real legacy migration data.

Scoped Foundation 4.10 changes fix registration results, forwarded legacy hook arguments, static plugin metadata, missing channel models/settings, loaded service replacement, navigation labels/routes, and static/persistent cache retrieval/deletion and key identity. Ambiguous delimiter-bearing compound keys use a separate nonhex namespace; ordinary scalar/single/joined keys retain their identities. Old ambiguous disposable cache entries may miss, without deletion by lookup. Producer-based tests cover either insertion order and disk reloads. The parser preserves valid native types, references, opaque custom payloads, uppercase escaped strings and accepted trailing-byte values. Missing-class callback warnings, wakeup diagnostics and ordinary callback exceptions retain their original strict behavior and restore the prior handler. A further 2000 value comparisons on PHP 7.4/8.5 cover 500 binary vectors in ordinary/prefix forms, with PHP 7.4 native equivalence and matching digests.

The original archive contains 22 members; the candidate adds one namespaced decoder. Original raw permission bits, compression, metadata, alias, original SHA-256 signature algorithm, license flag=true, complete Version member/JavaScript and license view remain checked. The scoped builder and actual owned-copy rebuild test follow the established tools process. New PHAR timestamps are normal. Actual distributed extractor tests cover normal/root/container links, stale hidden children, outside sentinels, corrupt-size validation and HTTP sibling containment. The shared container is checked before resolving a managed root; outside targets are not cleaned or written.

Two harness fixtures were corrected without weakening source regressions. PersistentCache's logical public key now refers to the processed memory entry actually produced by its writers; a separate real-writer digest-key collision regression checks hashing exactly once. The runner's expected failure/error status controls now use authored failing/throwing test cases in owned children, because the original source bugs they selected are fixed. The ordinary source cases remain selected and must pass. Native expected runtime masks now assert -1 rather than E_ALL, matching the required startup flags. No coverage collection was performed.

Exploratory source checks and self-review failures remain retained: an early check omitted EE_SYSTEM_PATH and therefore had four environmental errors; another exposed the original 4.10 missing-module null contract; a focused test caught other false-like toolbar defaults changing during the zero-label fix. Each was corrected before the final matrix. Self-review also removed an owned synthetic control's result-cache side effect by disabling its cache. A previous green final-v1 matrix is superseded: self-review then proved that an inherited public MCP data declaration broke existing protected subclass properties. The complete original MCP member and unrelated content-type matching behavior were restored before final-v2; an actual protected-subclass pagination regression preserves that contract.

Full commands, streams, JUnit, strict startup identities, unchanged-production proofs, raw archive manifests, source/stub diffs, native comparisons and exact review packet are in `/private/tmp/php-compat-2026-01a16ed8/low_seg2cat/`. This is configured-suite and actual EE declaration/load proof, not a live site upgrade, database migration, browser flow, authenticated service check or exhaustive coverage claim. Different-agent review and separate coordinator publication authorization remain required.


## Native contract correction after ownership transfer

The clean836ffd5 candidate is preserved and remains unpublished. Slot08 took writing ownership after slot07 stopped. New original-archive probes demonstrate the remaining bare generic MCP dynamic-data deprecation, skipped native hydration on malformed later S/custom tokens, truncated PHP7.4 diagnostic context and lost saved ArrayAccess roots/computed defaults. A narrow AllowDynamicProperties attribute retains the original undeclared data slot without narrowing public/protected/private/magic consumers; original version/nav/flux declarations remain.

The decoder adaptation from Fieldpack c5e2d33 accepts signed O/C counts, guards negative C lengths, and returns normalized-prefix plus untouched malformed suffix to native parsing. Exact known parser diagnostics at its own native file/line return without throwing so deferred native callbacks finish. Numerical overflow preserves native signed clamp values and successful parse flags; the same warning from a user callback delegates and remains durable-red. Unexpected diagnostics receive all original arguments, ordinary callback exceptions propagate, and the preceding handler always restores. Native valid-prefix tolerance and opaque/reference values are retained. Global settings restore native root values and direct ArrayAccess getters/setters; EE2–5 settings hydrate before the original merge behavior.

Actual decoder/global/persistent controls cover side effects, exceptions, caught diagnostics, numeric boundaries, saved object type and computed defaults. Native controller controls cover bare/public/protected/private/magic/ArrayObject consumers against original74. The retained original private-access Error remains explicit. All earlier evidence/packets remain historical; owner08 contains the refreshed exact final matrix and replacement review packet. Current writer08 authored the adaptation, informed by read-only01/05 lexical corrections and03 native MCP fixtures; a different reviewer must approve before publication. No shared tools, primary dirty files, original licensing/JS, raw modes or signature algorithm are changed.


## Corrective review batch

The earlier frozen packets and matrices are retained as superseded evidence. The current correction compares serialized decimal lengths as text against the remaining bytes before casting; oversized ordinary/escaped strings and positive/negative custom payloads reach native parsing without an extra lexer warning or lost deferred hydration. Native leading-zero lengths, clamped integer values, accepted prefixes, callbacks, exceptions, prior-handler arguments and restoration retain their verified behavior. Actual settings setters also preserve stored string byte writes and their exact database serialization.

Cache creation passes the original configured path to native mkdir, retaining successful native trailing-separator alias chains. Only six proven filesystem E_WARNING messages at that exact own callsite become the documented false/log outcome. Missing targets, cycles, missing/read-only/non-searchable parents and existing non-searchable cache/module directories remain covered. POSIX directory search checks are conditional on the native platform; no Windows filesystem execution is claimed. Direct extraction cleanup trims only native separators before identifying an existing or dangling root link, retains its target, removes hidden stale members, and preserves valid POSIX backslash names. Concurrent adversarial filesystem replacement remains outside this proof.

The actual EE3/4/5 navigation repair uses a header-only index, canonical root active-map targets and a null check that retains zero titles. Existing per-version URL sentinel behavior remains unchanged. This is a scoped adaptation by slot08 with actual artifact regressions; prior approvals do not approve this replacement.

The authoritative replacement counts, exact commands, sealed input hashes, full streams, fresh JUnit and effective startup identities are in the new external review packet. Preflight fixture/launcher failures remain retained separately. Tests execute actual loose source and rebuilt distributed members under all diagnostic startup flags; durable caught-diagnostic controls and native hydration checks remain mandatory. No publication approval is inferred from a local green matrix.


## Blocked checkpoint limitation and corrected native supervision

This is useful partial compatibility work, not a publication-ready result. Native serialized-object and stream-wrapper mkdir callbacks may install/pop persistent error handlers, and a previous handler may have a narrower severity mask. The temporary parser and directory-creation boundaries cannot preserve those full native contracts through the available callable-only API. Actual original/current witnesses are bound to the external blocker packet. No approximate stack reconstruction, balanced-callback restriction or publication is authorized. Green configured suites do not close this blocker.

The selected original malformed-cache regression explicitly requires a quiet false miss; the unchanged named oracle and all seven initial source-red reports are retained externally. Known parser diagnostics remain locally scoped; unexpected callback diagnostics remain checked. This intended fix is distinct from repositories whose public malformed-cache warnings were not selected for repair.

Native children share an invocation-owned durable diagnostic journal. Every unexpected receipt is written before throwing; ignored results and killed children cannot authorize a green checked parent. Explicit negative harness controls use an isolated journal with exact severities and anchored messages, then join every unrecognized receipt into the parent. Native startup/compilation output is retained and joined separately; complete stdout/stderr/status remain available. Late shutdown diagnostics exit after persistence. Root journals survive shutdown so later callbacks cannot lose their evidence; per-run temporary evidence owns cleanup. Application arguments after the selected script are preserved.

This corrective batch preserves native ordinary-array float key conversion and PHP7.4 first-byte string assignments, including one Stringable conversion and actual saved bytes. Fieldpack additionally restores the public zero-argument Version callback and distinct cleanup working-directory restoration. Fieldpack and Low Seg2Cat reuse converted compound key parts so a Stringable callback runs once and the original ordinary key identity remains usable. No peer implementation was copied for these slot08 corrections; peer native observations are disclosed in the review packet.

### Current reviewed-boundary correction

Raw disk cache now forwards its original numerical-range warning, including overflowing
custom-payload lengths. The selected quiet malformed-cache oracle remains separate;
stored-settings callers keep their original clamped values and newer PHP retains clean
PHP7.4 prefix/S-token behavior. The superseded raw-cache clamp assertions were corrected
against actual original74 strict execution: oversized C tails stop before hydration and
remain red in explicitly supervised native children. No dataset was removed or broadly
filtered. Ordinary callback diagnostics retain every argument and native handler state/
severity-mask preservation remains the concrete B1 blocker, now also proven for stream
wrapper mkdir callbacks. No approximate handler reconstruction is applied.

Ordinary array offsets reduce finite floating keys to a native signed word before casting;
nonfinite keys retain native zero. Native byte writes and ArrayAccess dispatch stay covered.
Unreadable optional navigation/extension files are skipped while readable customizations
still execute. The narrow corrections and permanent controls are authored by slot08 from
native observations; no peer implementation was copied in this batch. Slot07's external
mkdir witness is reused only as evidence, with attribution in the external packet.

Owned inline commands guard short/long run, begin, per-line and end-code spellings before
evaluating the original source. Healthy first-statement strict declarations and literal file
arguments remain valid. Ignored long-inline native results are rejected by the actual
checked parent through its invocation-owned diagnostic journal.


## Parent-owned native PHP log correction

The current tooling checkpoint keeps a distinct native PHP error log for every owned child. Complete stdout/stderr, the sticky prethrow journal and the native log are retained and hashed before the outcome joins its checked parent. An ignored exit-zero child with a real plain/HTML native warning on stdout now rejects the parent; exact warning-shaped application text remains healthy output. No stdout diagnostic pattern or product-warning allowance is introduced. Late native warnings remain visible after PHP shutdown through a small Python checked parent. The existing pinned-tool and fresh executed-JUnit checks remain in place.

Strict logging/startup options follow caller PHP options before the selected script. Exact finite tooling controls include native-log bytes and the Python runner's input hash; raw timestamped logs remain retained. Fixture copies include the new runtime dependency, with the healthy pre-fault invocation verified before fault injection. The complete refreshed matrix, original-case retention and updated independent review are bound in the external checkpoint packet. Production sources and distributed archive bytes are unchanged in this tooling-only correction. Prior matrices/reviews remain historical. B1 handler stack/mask and B2 opaque legacy-wire limitations still block publication.

Native temporary-root validation now recognizes its actual canonical filesystem alias while retaining declared original argument/environment bytes. An owned symlink-alias positive probe covers the macOS raw-versus-physical path boundary; finite input and output equality remain required.


The checked-wrapper deadline correction now runs native commands in a confirmed owned process group. Termination reaps the wrapper and stops its PHP descendants before terminal stream/log hashes are joined. Actual delayed marker, child-PID and incomplete-JUnit checks cover the public checked wrapper; an early-created empty report is retained as incomplete, never accepted as executed tests. Worker startup loads the canonical diagnostic guard before source execution, and caught first-statement diagnostics remain durable across exec. Previous wrapper-only timeout attempts are preserved as failures.

The same narrowly grounded declaration-header correction preserves native-valid empty braced/alternate declare bodies before a namespace on PHP7.4; actual direct/current before-and-after cases remain bound in the packet. No other CLI grammar expansion is included.

Owned work bindings normalize both raw macOS temporary aliases and their verified physical paths inside the same temporary root. Input capture and symlink restrictions remain unchanged. Deadline cleanup kills the confirmed process group before the shell can produce a later termination report or a descendant can finish its JUnit output.

Finite native profiles retain both lexical/physical default temporary paths. Native cropped source arguments are normalized only when backed by full same-event file/line and declared owned input roles. Registered nested, generated-prelude and bootstrap sentinels additionally bind exact caller/line and include/require operations. Only those already registered cases normalize their cropped owned source argument, including pinned PHPUnit loader frames on stdout; retained fixture and caller bytes remain required. All raw streams, full source/caller frames, message/severity, inputs and outcomes remain retained; no frame or rendering option is removed.
