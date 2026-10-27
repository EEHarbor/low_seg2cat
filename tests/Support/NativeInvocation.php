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
        $environment['LOW_SEG2CAT_NATIVE_ERROR_LOG'] = $directory . '/native-errors.log';
        file_put_contents($environment['LOW_SEG2CAT_NATIVE_ERROR_LOG'], '');
        $environment['LOW_SEG2CAT_NATIVE_RECEIPTS'] = self::$root; self::caseEnvironment($environment);
        $environment['LOW_SEG2CAT_NATIVE_PARENT'] = substr($directory, strlen(self::$root) + 1);
        file_put_contents($environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'], '');
        $temporaryAlias = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $temporary = realpath($temporaryAlias) ?: $temporaryAlias;
        $paths = array(self::$root => '<NATIVE>', dirname(__DIR__, 2) => '<REPO>', PHP_BINARY => '<PHP>');
        if ($temporary !== '') {
            $paths[$temporary] = '<TMP>';
            $paths[$temporaryAlias] = '<TMP>';
        }
        foreach ($bindings as $role => $path) {
            if (!in_array($role, array('work', 'fixture', 'probe', 'report', 'completion'), true) || !is_string($path)
                || (strpos($path, $temporary . DIRECTORY_SEPARATOR) !== 0 && strpos($path, $temporaryAlias . DIRECTORY_SEPARATOR) !== 0)) {
                throw new RuntimeException('Only explicit owned native fixture bindings are supported.');
            }
            $paths[$path] = '<' . strtoupper($role) . '>';
            $physical = realpath($path);
            if ($physical !== false && strpos($physical, $temporary . DIRECTORY_SEPARATOR) === 0) {
                $paths[$physical] = '<' . strtoupper($role) . '>';
            }
        }
        uksort($paths, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        self::caseToolPaths($paths); $normalize = static function ($value) use ($paths) {
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
        $originalSelected = $selected;
        if (isset($selected['TMPDIR']) && (realpath($selected['TMPDIR']) ?: rtrim($selected['TMPDIR'], DIRECTORY_SEPARATOR)) === $temporary) {
            $selected['TMPDIR'] = $temporary;
        }
        ksort($selected);
        self::write($directory . '/planned.json', array('owner_pid' => getmypid(), 'case' => self::$case, 'ordinal' => self::$ordinal,
            'command' => $original, 'guarded_command' => $command, 'original_environment' => $originalSelected, 'environment' => $selected, 'fixtures' => $fixtures,
            'cwd' => getcwd(), 'timeout' => (float) $timeout, 'code' => null, 'bindings' => $bindings,
            'native_error_log' => $environment['LOW_SEG2CAT_NATIVE_ERROR_LOG']));
        self::$plans[$directory] = false;

        return array($directory, $environment);
    }

    /**
     * Bind the actual guarded arguments before native startup.
     *
     * @param string $directory Invocation receipt directory.
     * @param array $command Complete guarded argument bytes.
     * @return void
     */
    public static function guardedCommand($directory, array &$command): void
    {
        self::guardCaseTool($command); $plan = json_decode(file_get_contents($directory . '/planned.json'), true);
        $plan['guarded_command'] = $command;
        self::write($directory . '/planned.json', $plan);
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
        $journal = self::nativeOutputJournal($directory, $result, $plan, $frames);
        $semanticCompletion = !isset($plan['bindings']['completion'])
            || (is_file($plan['bindings']['completion']) && file_get_contents($plan['bindings']['completion']) === 'complete');
        $completion = array('exit' => $result['status'], 'timed_out' => $result['timed_out'], 'semantic_completion' => $semanticCompletion,
            'stdout_sha256' => hash('sha256', $result['stdout']), 'stderr_sha256' => hash('sha256', $result['stderr']),
            'diagnostics_sha256' => hash('sha256', $journal), 'native_errors_sha256' => hash('sha256', $result['native_errors']), 'native_stdout_frames' => $frames);
        self::write($directory . '/completed.json', $completion);
        $profile = self::profile($plan, $completion, $result['stdout'], $result['stderr'], $journal, $result['native_errors'], $directory);
        self::write($directory . '/profile.json', $profile);
        $accepted = $result['status'] === 0 && !$result['timed_out'] && $result['stderr'] === '' && $journal === '' && $result['native_errors'] === '' && $semanticCompletion;
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
                . ', timeout=' . (int) $result['timed_out'] . ', native_log=' . (int) ($result['native_errors'] !== '') . ', receipts=' . $directory, __FILE__, __LINE__));
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
     * @param string $nativeErrors Complete native PHP log bytes.
     * @param string $directory Current retained invocation directory.
     * @return array Exact finite support-control profile.
     */
    private static function profile(array $plan, array $completion, $stdout, $stderr, $journal, $nativeErrors, $directory): array
    {
        $temporaryAlias = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $temporary = realpath($temporaryAlias) ?: $temporaryAlias;
        $paths = array(self::$root => '<NATIVE>', dirname(__DIR__, 2) => '<REPO>', PHP_BINARY => '<PHP>');
        if ($temporary !== '') {
            $paths[$temporary] = '<TMP>';
            $paths[$temporaryAlias] = '<TMP>';
        }
        foreach ($plan['bindings'] as $name => $path) {
            if (!in_array($name, array('work', 'fixture', 'probe', 'report', 'completion'), true) || !is_string($path) || (strpos($path, $temporary . DIRECTORY_SEPARATOR) !== 0 && strpos($path, $temporaryAlias . DIRECTORY_SEPARATOR) !== 0)) {
                return array('invalid_bindings' => true);
            }
            $paths[$path] = '<' . strtoupper($name) . '>';
            $physical = realpath($path);
            if ($physical !== false && strpos($physical, $temporary . DIRECTORY_SEPARATOR) === 0) {
                $paths[$physical] = '<' . strtoupper($name) . '>';
            }
        }
        uksort($paths, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        self::caseToolPaths($paths); $normalize = static function ($value) use ($paths) {
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
        $tracePaths = array();
        $argumentLimit = PHP_VERSION_ID < 80000 ? 15 : (int) ini_get('zend.exception_string_param_max_len');
        if ($argumentLimit > 0) {
            foreach (explode("\n", $journal) as $encoded) {
                $event = json_decode($encoded, true);
                if (!is_array($event) || count($event) !== 4 || !is_int($event[0])
                    || !is_string($event[2]) || !is_int($event[3]) || strlen($event[2]) <= $argumentLimit) {
                    continue;
                }
                $source = $normalize($event[2]);
                $role = null;
                foreach (array('work', 'probe', 'fixture') as $name) {
                    if (strpos($source, '<' . strtoupper($name) . '>/') === 0
                        || ($source === '<' . strtoupper($name) . '>' && isset($plan['fixtures'][$name . '/']['sha256']))) {
                        $role = strtoupper($name);
                        break;
                    }
                }
                if ($role === null) {
                    continue;
                }
                $calls = array(array('#0 [internal function]: LowSeg2CatDiagnostics::handle(' . $event[0] . ', ', $source, true));
                if (isset($plan['case']['class'], $plan['case']['name'], $plan['bindings']['probe'], $plan['fixtures']['probe/probe.php'])
                    && $plan['case']['class'] === 'Tests\\ProcessFixtureTest'
                    && $plan['case']['name'] === 'testNativeProbeRejectsSuppressedNestedDiagnostics'
                    && $event[2] === $plan['bindings']['probe'] . '/nested.php') {
                    $calls[] = array('#2 ' . $plan['bindings']['probe'] . '/probe.php(3): require(', $source, false);
                }
                $class = isset($plan['case']['class']) ? $plan['case']['class'] : null;
                $name = isset($plan['case']['name']) ? $plan['case']['name'] : null;
                $work = isset($plan['bindings']['work']) ? $plan['bindings']['work'] : null;
                $generated = ($class === 'Tests\\NativeAccessorFixtureTest'
                        && in_array($name, array('testRejectedAccessorRunPublishesNoGraphAndRemovesItsCopy with data set "hidden diagnostic"',
                            'testRejectedAccessorRunPublishesNoGraphAndRemovesItsCopy with data set "compile diagnostic"'), true))
                    || ($class === 'Tests\\PrimaryViewFixtureTest'
                        && in_array($name, array('testPreparationFailurePublishesNoSourceGraph with data set "suppressed generated diagnostic"',
                            'testPreparationFailurePublishesNoSourceGraph with data set "generated compile diagnostic"',
                            'testPreparationFailurePublishesNoSourceGraph with data set "nested generated diagnostic"'), true));
                if ($generated && $work !== null && isset($plan['fixtures']['work/gate.php'], $plan['fixtures']['work/prepend.php'])) {
                    if ($source === '<WORK>/prepend.php' && $event[3] === 17) {
                        $calls[] = array('#0 ' . $work . '/gate.php(1): LowSeg2CatDiagnostics::handle(' . $event[0] . ', ', $source, true);
                        $calls[] = array('#2 ' . $work . '/gate.php(1): require(', $source, false);
                    }
                    if ($class === 'Tests\\PrimaryViewFixtureTest' && $source === '<WORK>/nested.php' && $event[3] === 1) {
                        $calls[] = array('#2 ' . $work . '/prepend.php(17): require(', $source, false);
                        $calls[] = array('#3 ' . $work . '/gate.php(1): require(', '<WORK>/prepend.php', false);
                    }
                }
                if ($class === 'Tests\\RunnerFixtureTest' && $event[3] === 1) {
                    if ($name === 'testDirectEntryRejectsEarlySupportDiagnostics with data set "ordinary bootstrap"'
                        && $source === '<WORK>/Support/native.php' && isset($plan['fixtures']['work/entry.php'])) {
                        $calls[] = array('#2 ' . $work . '/entry.php(4): require_once(', $source, false);
                    }
                    if ($name === 'testDirectEntryRejectsEarlySupportDiagnostics with data set "native bootstrap"'
                        && $source === '<WORK>/Support/Environment.php' && isset($plan['fixtures']['work/Support/entry.php'])) {
                        $calls[] = array('#2 ' . $work . '/Support/entry.php(20): require_once(', $source, false);
                    }
                    $startup = in_array($name, array('testLauncherRejectsStartupDiagnostics with data set "before bootstrap"',
                        'testLauncherRejectsStartupDiagnostics with data set "after bootstrap"',
                        'testLauncherRejectsStartupDiagnostics with data set "bootstrap compilation"',
                        'testCoveragePublishesOnlyAfterFreshExecutedCases with data set "startup before bootstrap"',
                        'testCoveragePublishesOnlyAfterFreshExecutedCases with data set "startup after bootstrap"',
                        'testCoveragePublishesOnlyAfterFreshExecutedCases with data set "startup compilation"'), true);
                    if ($startup && ($source === '<FIXTURE>' || $source === '<WORK>/bootstrap.php')) {
                        $loader = 'phar://' . dirname(__DIR__, 2) . '/tests/.phpunit/phpunit-9.phar/phpunit/';
                        $calls[] = array('#0 ' . $loader . 'Util/FileLoader.php(57): LowSeg2CatDiagnostics::handle(' . $event[0] . ', ', $source, true);
                        foreach (array(2, 3) as $frame) {
                            $calls[] = array('#' . $frame . ' ' . $loader . 'Util/FileLoader.php(43): PHPUnit\\Util\\FileLoader::load(', $source, false);
                        }
                        $calls[] = array('#2 ' . $loader . 'Util/FileLoader.php(57): include_once(', $source, false);
                        foreach (array(3, 4) as $frame) {
                            $calls[] = array('#' . $frame . ' ' . $loader . 'TextUI/Command.php(419): PHPUnit\\Util\\FileLoader::checkAndLoad(', $source, false);
                        }
                        foreach (array(4, 5) as $frame) {
                            $calls[] = array('#' . $frame . ' ' . $loader . 'TextUI/Command.php(278): PHPUnit\\TextUI\\Command->handleBootstrap(', $source, false);
                        }
                    }
                }
                foreach ($calls as $call) {
                    foreach ($paths as $path => $pathRole) {
                        if ($pathRole !== '<' . $role . '>' || strpos($call[1], $pathRole) !== 0) {
                            continue;
                        }
                        $argument = $path . substr($call[1], strlen($pathRole));
                        if (strlen($argument) <= $argumentLimit) {
                            continue;
                        }
                        $token = "'" . substr($argument, 0, $argumentLimit) . "...'";
                        $replacement = "'<" . $role . "_SOURCE_PREFIX>'";
                        $tracePaths[] = $call[2]
                            ? array($normalize($call[0]), ', ' . $token . ', ' . $event[3], ', ' . $replacement . ', ' . $event[3])
                            : array($normalize($call[0]), '(' . $token . ')', '(' . $replacement . ')');
                    }
                }
            }
        }
        // Full source/caller inputs remain bound; only their native cropped path argument varies.
        $normalizeTrace = static function ($bytes) use ($tracePaths, $normalize) {
            $lines = explode("\n", $bytes);
            foreach ($lines as &$line) {
                $line = $normalize($line);
                foreach ($tracePaths as $path) {
                    if (strpos($line, $path[0]) === 0) {
                        $line = str_replace($path[1], $path[2], $line);
                    }
                }
            }
            unset($line);
            return implode("\n", $lines);
        };
        $stdout = $normalizeTrace($stdout);
        $stderr = $normalizeTrace($stderr);
        $nativeErrors = $normalizeTrace($nativeErrors);

        $stdout = preg_replace('/^Time: [0-9:.]+, Memory: [0-9.]+ MB$/m', 'Time: <duration>, Memory: <memory> MB', $stdout);
        $nativeErrors = preg_replace('/^\[[0-9]{2}-[A-Za-z]{3}-[0-9]{4} [^\r\n]*\] /m', '', $nativeErrors);
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
            'stderr' => $normalize($stderr), 'journal' => $normalize($journal), 'native_errors' => $normalize($nativeErrors));
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
        foreach (array('code', 'stdout', 'stderr', 'journal', 'native_errors') as $name) {
            $profile[$name . '_sha256'] = $profile[$name] === null ? null : hash('sha256', $profile[$name]);
            unset($profile[$name]);
        }

        return $profile;
    }

    /**
     * Fill only a missing diagnostic channel while retaining every detected frame.
     *
     * @param string $directory Owned child receipt directory.
     * @param array $result Complete unchanged terminal streams and status.
     * @param array $plan Original checked inputs.
     * @param array|null $frames Detected source-bound stdout frames.
     * @return string Complete child diagnostic journal.
     */
    private static function nativeOutputJournal($directory, array $result, array $plan, &$frames): string
    {
        $frames = self::nativeOutputFrames($result['stdout'], $plan);
        $journal = file_get_contents($directory . '/diagnostics.jsonl');
        if ($journal === '' && $result['stderr'] === '' && $result['native_errors'] === '' && $result['status'] === 0) {
            foreach ($frames as $frame) {
                $event = array(E_WARNING, 'Native stdout diagnostic: ' . $frame['severity'] . ': ' . $frame['message'], $frame['file'], $frame['line']);
                $journal .= json_encode($event, JSON_UNESCAPED_SLASHES) . "\n";
            }
            if ($journal !== '') {
                file_put_contents($directory . '/diagnostics.jsonl', $journal, FILE_APPEND | LOCK_EX);
            }
        }

        return $journal;
    }

    /**
     * Recognize complete native PHP stdout frames from retained owned source.
     *
     * @param string $output Complete unchanged child stdout.
     * @param array $plan Original checked command, case and owned bindings.
     * @return array Verified native diagnostic frames and source identities.
     */
    private static function nativeOutputFrames($output, array $plan): array
    {
        $kinds = '(Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error|Strict Standards)';
        $labels = substr($kinds, 1, -1);
        $plain = '((?:(?!\r?\n(?:PHP )?(?:' . $labels . '): )[\s\S])*?)';
        $html = '((?:(?!<b>(?:' . $labels . ')</b>:)[\s\S])*?)';
        $patterns = array('~^(?:PHP )?' . $kinds . ': ' . $plain . ' in ([^\r\n]+) on line ([1-9][0-9]*)\r?$~m',
            '~<b>' . $kinds . '</b>:\s*' . $html . ' in <b>([^<\r\n]+)</b> on line <b>([1-9][0-9]*)</b><br\s*/?>~');
        $frames = array();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $output, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $file = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
                $line = (int) $match[4];
                $inline = false;
                foreach ($plan['guarded_command'] as $argument) {
                    $inline = $inline || in_array($argument, array('-r', '--run'), true)
                        || (strpos($argument, '-r') === 0 && strlen($argument) > 2) || strpos($argument, '--run=') === 0;
                }
                $verified = $file === 'Command line code' && $inline;
                $digest = null;
                if (!$verified && is_file($file)) {
                    $owned = strpos($file, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR) === 0
                        || (isset($plan['case']['file']) && $file === $plan['case']['file'])
                        || in_array($file, $plan['guarded_command'], true);
                    foreach ($plan['bindings'] as $path) {
                        $owned = $owned || $file === $path || strpos($file, rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
                    }
                    if ($owned) {
                        $source = file_get_contents($file);
                        $verified = $line <= count(explode("\n", $source));
                        $digest = hash('sha256', $source);
                    }
                }
                if ($verified) {
                    $frames[] = array('severity' => $match[1], 'message' => html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'),
                        'file' => $file, 'line' => $line, 'source_sha256' => $digest, 'raw_base64' => base64_encode($match[0]));
                }
            }
        }

        return $frames;
    }


    private static object $caseOutput;

    /**
     * Bind the checked parent's append-only original-case output channel before tests run.
     *
     * @return void
     * @throws RuntimeException When the declared channel or derived tool is invalid.
     */
    public static function bindCaseOutput(): void
    {
        if (isset(self::$caseOutput)) {
            return;
        }
        $path = getenv('LOW_SEG2CAT_CASE_OUTPUT');
        if (!$path) {
            return;
        }
        $tool = dirname(__DIR__) . '/.phpunit/phpunit-9-output.phar';
        $record = json_decode(file_get_contents($tool . '.json'), true, 512, JSON_THROW_ON_ERROR);
        if (is_link($path) || !is_file($path) || is_link($tool)
            || $record['artifact_sha256'] !== hash_file('sha256', $tool)
            || $record['recipe_sha256'] !== hash_file('sha256', dirname(__DIR__) . '/.phpunit/prepare-output.php')) {
            throw new RuntimeException('Checked original-output ownership is invalid.');
        }
        $state = (object) array('path' => $path, 'nonce' => getenv('LOW_SEG2CAT_CASE_NONCE'),
            'tool' => $tool, 'artifact' => $record['artifact_sha256'], 'handle' => fopen($path, 'ab'));
        if (!is_resource($state->handle) || !$state->nonce) {
            throw new RuntimeException('Cannot bind the checked original-output channel.');
        }
        self::$caseOutput = $state;
        self::appendCaseOutput(array('kind' => 'bind', 'pid' => getmypid(), 'artifact_sha256' => $state->artifact));
    }

    /**
     * Retain the original buffer before assertions and after a successful output callback.
     *
     * @param object $owner Actual original TestCase instance, including direct subclasses.
     * @param string $bytes Unmodified original output-buffer bytes.
     * @param string $stage Original body or successful callback boundary.
     * @return void
     * @throws RuntimeException When no checked owner or complete retained channel exists.
     */
    public static function caseOutput($owner, string $bytes, string $stage): void
    {
        if (!isset(self::$caseOutput)) {
            throw new RuntimeException('Derived PHPUnit has no original-output owner.');
        }
        $reflection = new ReflectionClass($owner);
        $source = $reflection->getFileName();
        $contents = file_get_contents($source);
        $retained = dirname(self::$caseOutput->path) . '/case-inputs/' . hash('sha256', $contents);
        if (!is_file($retained) && file_put_contents($retained, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Cannot retain original-case source bytes.');
        }
        if (is_link($retained) || hash_file('sha256', $retained) !== hash('sha256', $contents)) {
            throw new RuntimeException('Original-case source bytes changed.');
        }
        self::appendCaseOutput(array('kind' => 'case', 'pid' => getmypid(),
            'class' => get_class($owner), 'name' => $owner->getName(), 'file' => $source, 'stage' => $stage,
            'source_sha256' => hash('sha256', $contents),
            'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'body_base64' => base64_encode($bytes)));
    }

    /**
     * Append one complete immutable-owner record independently of the current environment.
     *
     * @param array $record Native bind identity or complete original case output.
     * @return void
     * @throws RuntimeException When any byte cannot be retained.
     */
    private static function appendCaseOutput(array $record): void
    {
        $record['nonce'] = self::$caseOutput->nonce;
        $bytes = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $handle = self::$caseOutput->handle;
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock the original-output channel.');
        }
        try {
            $written = 0;
            while ($written < strlen($bytes)) {
                $count = fwrite($handle, substr($bytes, $written));
                if ($count === false || $count === 0) {
                    throw new RuntimeException('Cannot retain complete original-output bytes.');
                }
                $written += $count;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Cannot flush original-output bytes.');
            }
        } finally {
            flock($handle, LOCK_UN);
        }
    }

    /**
     * Pass the immutable checked output owner into each existing native environment.
     *
     * @param array $environment Original selected child environment.
     * @return void
     */
    private static function caseEnvironment(array &$environment): void
    {
        if (isset(self::$caseOutput)) {
            $environment['LOW_SEG2CAT_CASE_OUTPUT'] = self::$caseOutput->path;
            $environment['LOW_SEG2CAT_CASE_NONCE'] = self::$caseOutput->nonce;
        }
    }

    /**
     * Select only the verified pinned PHPUnit tool after original argv were retained.
     *
     * @param array $command Guarded executable arguments before native launch.
     * @return void
     * @throws RuntimeException When the derived artifact changed.
     */
    private static function guardCaseTool(array &$command): void
    {
        if (($command[0] ?? null) !== PHP_BINARY || !isset(self::$caseOutput)) {
            return;
        }
        if (hash_file('sha256', self::$caseOutput->tool) !== self::$caseOutput->artifact) {
            throw new RuntimeException('Derived PHPUnit changed before native launch.');
        }
        $values = array('-d', '--define', '-c', '--php-ini', '-z', '--zend-extension',
            '-t', '--docroot', '-S', '--server', '--rf', '--rc', '--re', '--rz', '--ri');
        $selected = null;
        $prefix = '';
        for ($index = 1; isset($command[$index]); $index++) {
            $argument = $command[$index];
            if (in_array($argument, array('-r', '--run', '-B', '--process-begin', '-R', '--process-code',
                '-E', '--process-end', '-F', '--process-file'), true)) {
                return;
            }
            foreach (array('-r', '--run=', '-B', '--process-begin=', '-R', '--process-code=', '-E', '--process-end=') as $inline) {
                if (strpos($argument, $inline) === 0 && strlen($argument) > strlen($inline)) {
                    return;
                }
            }
            if ($argument === '--' || $argument === '-f' || $argument === '--file') {
                $selected = $index + 1;
                break;
            }
            if (strpos($argument, '--file=') === 0 || (strpos($argument, '-f') === 0 && strlen($argument) > 2)) {
                $selected = $index;
                $prefix = strpos($argument, '--file=') === 0 ? '--file=' : '-f';
                break;
            }
            if (in_array($argument, $values, true)) {
                $index++;
                continue;
            }
            if ($argument !== '' && $argument[0] === '-') {
                continue;
            }
            $selected = $index;
            break;
        }
        if ($selected === null || !isset($command[$selected])) {
            return;
        }
        $source = substr($command[$selected], strlen($prefix));
        if (basename($source) === 'phpunit-9.phar' && is_file($source)
            && hash_file('sha256', $source) === '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7') {
            $command[$selected] = $prefix . self::$caseOutput->tool;
        }
    }

    /**
     * Keep the declared derived-tool path in the original finite tool role.
     *
     * @param array $paths Already owned path-to-role bindings.
     * @return void
     */
    private static function caseToolPaths(array &$paths): void
    {
        if (isset(self::$caseOutput)) {
            $paths[self::$caseOutput->tool] = dirname(__DIR__) . '/.phpunit/phpunit-9.phar';
            uksort($paths, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        }
    }
}
