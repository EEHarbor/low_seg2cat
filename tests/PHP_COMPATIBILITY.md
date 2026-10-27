# Addon-only PHP compatibility validation

The 2026-10-26 scope correction excludes Foundation repairs and new Foundation-only regression/build checks. Earlier Foundation-inclusive candidates, green matrices and review reports are historical, preserved in Git and external evidence; they do not approve this corrected candidate.

The original tests baseline is `58fa1a9abf8b23faee93c5561c36e2172101480d`; the pinned develop target is `38a3d826df9b2ef2fbf96eca08efce5211386e68`. The bundled `low_seg2cat.phar` is restored byte-for-byte from that develop commit (SHA256 `b340d9b7922acf926a0ddb58e72be07953c5235519b3df90bb65fb170b81699d`). The original loader is unchanged. Campaign-added Foundation build/source and Foundation-only tests are omitted; every original baseline test remains.

The retained addon fixes preserve false/default settings, all sites during hook migrations, failed-insert retryability, database-update-before-success ordering, and empty values for unavailable URI segment keys. The mixed regression file retains only addon settings/migration cases.

The selected-runtime strict launcher, provider/test/child diagnostics, durable journal, native log, terminal status and complete process streams remain enforced. Finite control profiles cover deliberately authored tooling faults; no product diagnostic or dependency failure is allowed by this correction. The source inventory describes the restored dependency rather than a locally rebuilt archive.

The corrected full configured PHP 7.4–8.5 matrix is recorded externally under `scope-correction-20261026/slot07/low_seg2cat/cleanup`. Any stock Foundation errors are actual failed validation, attributed to the excluded dependency and left blocking. Addon-focused evidence is separate and cannot make a failed full suite green. Independent review and publication are pending; no commit, push or PR update is authorized by this document.
