<?php

/**
 * Join every native outcome to the checked owner before the caller receives it.
 */
final class LowSeg2CatNativeInvocation
{
    private static $root;
    private static $case;
    private static $caseObject;
    private static $ordinal = 0;
    private static $plans = array();
    private static $used = array();

    /**
     * Bind actual running PHPUnit identity and original inputs before native startup.
     *
     * @param array $original Original argument vector before guarding.
     * @param array $command Guarded native argument vector.
     * @param array $environment Original selected child environment.
     * @param float $timeout Native deadline excluding lease waiting.
     * @param array $bindings Explicit owned fixture paths, never diagnostic authority.
     * @return array Retained directory and child environment.
     * @throws RuntimeException When owned inputs cannot be recorded safely.
     */
    public static function begin(array $original, array $command, array $environment, $timeout, array $bindings): array
    {
        $test = null;
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['class'], $frame['object']) && $frame['class'] === 'PHPUnit\\Framework\\TestCase'
                && $frame['function'] === 'runBare') {
                $test = $frame['object'];
                break;
            }
        }
        if ($test !== self::$caseObject) {
            self::endCase();
            self::$caseObject = $test;
            if ($test !== null) {
                $reflection = new ReflectionClass($test);
                self::$case = array('class' => get_class($test), 'name' => $test->getName(),
                    'file' => $reflection->getFileName(), 'sha256' => hash_file('sha256', $reflection->getFileName()));
            }
        }
        if (self::$root === null) {
            self::$root = getenv('LOW_SEG2CAT_NATIVE_RECEIPTS');
            if (!self::$root) {
                self::$root = tempnam(sys_get_temp_dir(), 'low-seg2cat-native-');
                unlink(self::$root);
                mkdir(self::$root, 0700);
                putenv('LOW_SEG2CAT_NATIVE_RECEIPTS=' . self::$root);
            }
        }
        $identity = self::$case === null ? 'entry' : sha1(self::$case['class'] . '::' . self::$case['name']);
        $prefix = getenv('LOW_SEG2CAT_NATIVE_PARENT') ?: 'main';
        $directory = self::$root . '/' . $prefix . '/' . $identity . '-' . ++self::$ordinal;
        if (!is_dir(dirname($directory))) {
            mkdir(dirname($directory), 0700, true);
        }
        mkdir($directory, 0700);
        $environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'] = $directory . '/diagnostics.jsonl';
        $environment['LOW_SEG2CAT_NATIVE_RECEIPTS'] = self::$root;
        $environment['LOW_SEG2CAT_NATIVE_PARENT'] = substr($directory, strlen(self::$root) + 1);
        file_put_contents($environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'], '');
        $temporary = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $paths = array(self::$root => '<NATIVE>', dirname(__DIR__, 2) => '<REPO>', PHP_BINARY => '<PHP>');
        if ($temporary !== '') {
            $paths[$temporary] = '<TMP>';
        }
        foreach ($bindings as $role => $path) {
            if (!in_array($role, array('work', 'fixture', 'probe', 'report', 'completion'), true) || !is_string($path)
                || strpos($path, $temporary . DIRECTORY_SEPARATOR) !== 0) {
                throw new RuntimeException('Only explicit owned native fixture bindings are supported.');
            }
            $paths[$path] = '<' . strtoupper($role) . '>';
        }
        uksort($paths, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        $normalize = static function ($value) use ($paths) {
            foreach ($paths as $path => $role) {
                $value = str_replace(array($path, substr(json_encode($path), 1, -1)), $role, $value);
            }
            do {
                $previous = $value;
                $value = str_replace(array('<TMP>//', '<TMP>\\/\\/'), array('<TMP>/', '<TMP>\\/'), $value);
            } while ($value !== $previous);
            return $value;
        };
        $fixtures = array();
        foreach ($bindings as $role => $path) {
            if (!file_exists($path) && !is_link($path)) {
                $fixtures[$role . '/'] = array('absent' => true);
                continue;
            }
            $entries = is_dir($path) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) : array(new SplFileInfo($path));
            foreach ($entries as $entry) {
                $relative = is_dir($path) ? substr($entry->getPathname(), strlen($path) + 1) : '';
                if (!$entry->isFile()) {
                    throw new RuntimeException('Native inputs must be files or declared file symlinks.');
                }
                $bytes = file_get_contents($entry->getPathname());
                $inputDirectory = self::$root . '/_inputs';
                if (!is_dir($inputDirectory)) {
                    mkdir($inputDirectory, 0700);
                }
                $retained = $inputDirectory . '/' . hash('sha256', $bytes);
                if (!is_file($retained)) {
                    file_put_contents($retained, $bytes);
                }
                if (hash_file('sha256', $retained) !== hash('sha256', $bytes)) {
                    throw new RuntimeException('Retained native input bytes changed.');
                }
                $canonical = $normalize($bytes);
                if ($relative === 'tests/NATIVE_CONTROLS.json' && hash('sha256', $bytes) === hash_file('sha256', dirname(__DIR__) . '/NATIVE_CONTROLS.json')) {
                    $canonical = '<CONTROL_MANIFEST>';
                }
                $fixtures[$role . '/' . $relative] = array('sha256' => hash('sha256', $canonical), 'mode' => $entry->getPerms() & 0777,
                    'symlink' => $entry->isLink() ? $normalize(readlink($entry->getPathname())) : null);
                self::write($directory . '/input-' . sha1($role . '/' . $relative) . '.json', array('source' => $entry->getPathname(), 'retained' => $retained,
                    'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes), 'canonical_sha256' => hash('sha256', $canonical)));
            }
        }
        ksort($fixtures);
        $selected = array_intersect_key($environment, array_flip(array('PHP_BINARY', 'TMPDIR', 'TMP', 'TEMP', 'XDEBUG_MODE',
            'LOW_SEG2CAT_EE_VERSION', 'LOW_SEG2CAT_REQUEST_TYPE', 'LOW_SEG2CAT_DIRECT_ACCESS', 'LOW_SEG2CAT_MISSING_PHAR',
            'LOW_SEG2CAT_XDEBUG_EXTENSION', 'LOW_SEG2CAT_COVERAGE', 'LOW_SEG2CAT_MODULE_NAV_THIRD_PATH', 'LOW_SEG2CAT_REAL_FIELDTYPE')));
        if (isset($selected['TMPDIR']) && rtrim($selected['TMPDIR'], DIRECTORY_SEPARATOR) === $temporary) {
            $selected['TMPDIR'] = $temporary;
        }
        ksort($selected);
        self::write($directory . '/planned.json', array('owner_pid' => getmypid(), 'case' => self::$case, 'ordinal' => self::$ordinal,
            'command' => $original, 'guarded_command' => $command, 'environment' => $selected, 'fixtures' => $fixtures,
            'cwd' => getcwd(), 'timeout' => (float) $timeout, 'code' => null, 'bindings' => $bindings));
        self::$plans[$directory] = false;

        return array($directory, $environment);
    }

    /**
     * Persist the actual PID and deadline immediately after native creation.
     *
     * @param string $directory Invocation receipt directory.
     * @param array $status Native process status.
     * @param float $timeout Bounded native duration.
     * @return void
     */
    public static function started($directory, array $status, $timeout): void
    {
        self::write($directory . '/started.json', array('pid' => $status['pid'], 'deadline' => microtime(true) + $timeout));
    }

    /**
     * Retain and join complete native bytes even when the returned result is ignored.
     *
     * @param string $directory Invocation receipt directory.
     * @param array $result Terminal status/deadline/output record.
     * @return void
     */
    public static function complete($directory, array $result): void
    {
        $plan = json_decode(file_get_contents($directory . '/planned.json'), true);
        $journal = file_get_contents($directory . '/diagnostics.jsonl');
        $semanticCompletion = !isset($plan['bindings']['completion'])
            || (is_file($plan['bindings']['completion']) && file_get_contents($plan['bindings']['completion']) === 'complete');
        $completion = array('exit' => $result['status'], 'timed_out' => $result['timed_out'], 'semantic_completion' => $semanticCompletion,
            'stdout_sha256' => hash('sha256', $result['stdout']), 'stderr_sha256' => hash('sha256', $result['stderr']),
            'diagnostics_sha256' => hash('sha256', $journal));
        self::write($directory . '/completed.json', $completion);
        $profile = self::profile($plan, $completion, $result['stdout'], $result['stderr'], $journal, $directory);
        self::write($directory . '/profile.json', $profile);
        $accepted = $result['status'] === 0 && !$result['timed_out'] && $result['stderr'] === '' && $journal === '' && $semanticCompletion;
        if (!$accepted) {
            $identity = $plan['case'] === null ? null : $plan['case']['class'] . '::' . $plan['case']['name'] . '#' . $plan['ordinal'];
            $accepted = $identity !== null && !isset(self::$used[$identity]) && self::ownedControl($profile, $directory);
            if ($accepted) {
                self::$used[$identity] = true;
            }
        }
        self::write($directory . '/joined.json', array('accepted' => $accepted));
        self::$plans[$directory] = true;
        if (!$accepted) {
            foreach (explode("\n", rtrim($journal, "\n")) as $encoded) {
                $event = json_decode($encoded, true);
                if (is_array($event) && count($event) === 4) {
                    LowSeg2CatDiagnostics::nativeFailure($event);
                }
            }
            LowSeg2CatDiagnostics::nativeFailure(array(E_WARNING, 'Rejected native lifecycle: exit=' . var_export($result['status'], true)
                . ', timeout=' . (int) $result['timed_out'] . ', receipts=' . $directory, __FILE__, __LINE__));
        }
    }

    /**
     * Reject plans whose native outcome never reached the checked owner.
     *
     * @return void
     */
    public static function finish(): void
    {
        self::endCase();
        foreach (self::$plans as $directory => $joined) {
            if (!$joined) {
                LowSeg2CatDiagnostics::nativeFailure(array(E_WARNING, 'Unjoined native invocation: ' . $directory, __FILE__, __LINE__));
            }
        }
    }

    /**
     * Require each registered control ordinal once for every observed original case.
     *
     * @return void
     */
    public static function endCase(): void
    {
        $path = dirname(__DIR__) . '/NATIVE_CONTROLS.json';
        if (self::$case !== null && is_file($path)) {
            $controls = json_decode(file_get_contents($path), true);
            $prefix = self::$case['class'] . '::' . self::$case['name'] . '#';
            foreach ($controls['cases'] as $identity => $versions) {
                if (strpos($identity, $prefix) === 0 && !isset(self::$used[$identity])) {
                    LowSeg2CatDiagnostics::nativeFailure(array(E_WARNING, 'Missing finite owned control: ' . $identity, __FILE__, __LINE__));
                }
            }
        }
        self::$case = null;
        self::$ordinal = 0;
    }

    /**
     * Retain complete records with atomic replacement inside the owned directory.
     *
     * @param string $path Record destination.
     * @param array $value Complete record payload.
     * @return void
     */
    private static function write($path, array $value): void
    {
        file_put_contents($path . '.new', json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        rename($path . '.new', $path);
    }

    /**
     * Canonicalize only explicitly owned paths and PHPUnit's elapsed-time footer.
     *
     * @param array $plan Exact planned input.
     * @param array $completion Complete terminal outcome.
     * @param string $stdout Full output bytes.
     * @param string $stderr Full diagnostic bytes.
     * @param string $journal Durable prethrow events.
     * @param string $directory Current retained invocation directory.
     * @return array Exact finite support-control profile.
     */
    private static function profile(array $plan, array $completion, $stdout, $stderr, $journal, $directory): array
    {
        $temporary = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $paths = array(self::$root => '<NATIVE>', dirname(__DIR__, 2) => '<REPO>', PHP_BINARY => '<PHP>');
        if ($temporary !== '') {
            $paths[$temporary] = '<TMP>';
        }
        foreach ($plan['bindings'] as $name => $path) {
            if (!in_array($name, array('work', 'fixture', 'probe', 'report', 'completion'), true) || !is_string($path) || strpos($path, $temporary . DIRECTORY_SEPARATOR) !== 0) {
                return array('invalid_bindings' => true);
            }
            $paths[$path] = '<' . strtoupper($name) . '>';
        }
        uksort($paths, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        $normalize = static function ($value) use ($paths) {
            foreach ($paths as $path => $role) {
                $value = str_replace(array($path, substr(json_encode($path), 1, -1)), $role, $value);
            }
            do {
                $previous = $value;
                $value = str_replace(array('<TMP>//', '<TMP>\\/\\/'), array('<TMP>/', '<TMP>\\/'), $value);
            } while ($value !== $previous);
            return $value;
        };
        if ($plan['case'] !== null && $plan['case']['class'] === 'Tests\\ProcessFixtureTest'
            && (strpos($plan['case']['name'], 'testNativeProbeDeadlineRestoresOwnedTemporaryDirectory ') === 0
                || strpos($plan['case']['name'], 'testRestrictedProbeCleanupPreservesExternalSymlinkTargets ') === 0)) {
            $identity = json_decode($stdout, true);
            $started = json_decode(file_get_contents($directory . '/started.json'), true);
            if (!is_array($identity) || array_keys($identity) !== array('pid', 'temporary')
                || !isset($plan['bindings']['probe']) || $identity['pid'] !== $started['pid']
                || $identity['temporary'] !== $plan['bindings']['probe'] || json_encode($identity) !== $stdout) {
                return array('invalid_native_identity' => true);
            }
            $stdout = str_replace('"pid":' . $started['pid'] . ',', '"pid":"<NATIVE_PID>",', $stdout);
        }
        $stdout = preg_replace('/^Time: [0-9:.]+, Memory: [0-9.]+ MB$/m', 'Time: <duration>, Memory: <memory> MB', $stdout);
        $case = $plan['case'];
        if ($case !== null) {
            $case['file'] = $normalize($case['file']);
        }

        if (isset($plan['bindings']['work']) && is_file($plan['bindings']['work'] . '/junit.xml')) {
            copy($plan['bindings']['work'] . '/junit.xml', $directory . '/junit.xml');
        }

        return array('case' => $case, 'ordinal' => $plan['ordinal'], 'command' => array_map($normalize, $plan['command']),
            'environment' => array_map($normalize, $plan['environment']), 'fixtures' => $plan['fixtures'],
            'cwd' => $normalize($plan['cwd']), 'timeout' => $plan['timeout'], 'code' => $plan['code'] === null ? null : $normalize($plan['code']),
            'exit' => $completion['exit'], 'timed_out' => $completion['timed_out'], 'semantic_completion' => $completion['semantic_completion'], 'stdout' => $normalize($stdout),
            'stderr' => $normalize($stderr), 'journal' => $normalize($journal));
    }

    /**
     * Recognize only sealed finite harness faults, never caller-supplied patterns.
     *
     * @param array $profile Exact case, input bytes, frames, streams and outcome.
     * @param string $directory Retained native invocation records.
     * @return bool Whether this exact authored support control is registered.
     */
    private static function ownedControl(array $profile, $directory): bool
    {
        $path = dirname(__DIR__) . '/NATIVE_CONTROLS.json';
        if (!is_file($path) || !isset($profile['case'])) {
            return false;
        }
        $controls = json_decode(file_get_contents($path), true);
        if (!is_array($controls) || $controls['kernel_sha256'] !== hash_file('sha256', __FILE__)) {
            return false;
        }
        foreach ($controls['inputs'] as $relative => $digest) {
            if (!is_file(dirname(__DIR__, 2) . '/' . $relative) || hash_file('sha256', dirname(__DIR__, 2) . '/' . $relative) !== $digest) {
                return false;
            }
        }
        $identity = $profile['case']['class'] . '::' . $profile['case']['name'] . '#' . $profile['ordinal'];
        $key = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        if (!isset($controls['cases'][$identity][$key])) {
            return false;
        }
        if ($profile['case']['class'] === 'Tests\\NativeJournalOwnershipTest'
            && strpos($profile['case']['name'], 'testIgnoredChildDiagnosticCannotPass ') === 0) {
            if (!is_file($directory . '/junit.xml')) {
                return false;
            }
            $xml = simplexml_load_file($directory . '/junit.xml', 'SimpleXMLElement', LIBXML_NONET);
            $cases = $xml->xpath('//testcase');
            if (count($cases) !== 1 || count($cases[0]->failure) + count($cases[0]->error) !== 1) {
                return false;
            }
        }
        $digest = $controls['cases'][$identity][$key];

        return isset($controls['profiles'][$digest]) && json_encode($controls['profiles'][$digest]) === json_encode(self::controlProfile($profile));
    }
    /**
     * Keep finite control authorization compact without weakening byte equality.
     *
     * @param array $profile Full retained native input and terminal streams.
     * @return array Case identity, exact frames, input and complete stream digests.
     */
    private static function controlProfile(array $profile): array
    {
        unset($profile['environment']['XDEBUG_MODE']);
        $events = array();
        foreach (explode("\n", rtrim($profile['journal'], "\n")) as $encoded) {
            if ($encoded !== '') {
                $events[] = json_decode($encoded, true);
            }
        }
        $profile['events'] = $events;
        $profile['fixtures_count'] = count($profile['fixtures']);
        $profile['fixtures_sha256'] = hash('sha256', json_encode($profile['fixtures'], JSON_UNESCAPED_SLASHES));
        unset($profile['fixtures']);
        foreach (array('code', 'stdout', 'stderr', 'journal') as $name) {
            $profile[$name . '_sha256'] = $profile[$name] === null ? null : hash('sha256', $profile[$name]);
            unset($profile[$name]);
        }

        return $profile;
    }

}
