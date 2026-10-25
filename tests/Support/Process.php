<?php

namespace Tests\Support;

class Process
{
    /**
     * Execute a fresh source probe with this exact PHP binary and isolated temp path.
     *
     * @param string $code
     * @param array $context
     * @return array
     * @throws \RuntimeException
     */
    public static function probe($code, array $context = array())
    {
        $coverageDirectory = empty($context['harness_only']) ? getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR') : false;
        $extension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
        if ($coverageDirectory && (!$extension || !is_file($extension) || !is_dir($coverageDirectory))) {
            throw new \RuntimeException('Child coverage requires an explicit Xdebug extension and report directory.');
        }
        $temporary = sys_get_temp_dir() . '/low-seg2cat-probe-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700)) {
            throw new \RuntimeException('Cannot create isolated process directory.');
        }
        $canonical = realpath($temporary);
        if ($canonical === false) {
            rmdir($temporary);
            throw new \RuntimeException('Cannot resolve the owned native probe directory.');
        }
        $temporary = $canonical;
        $probe = $temporary . '/probe.php';
        $bootstrap = var_export(__DIR__ . '/native.php', true);
        $prefix = 'require ' . $bootstrap . '; \Tests\Support\Environment::installDiagnostics(); ' .
            '$GLOBALS["low_seg2cat_app"] = new \Tests\Support\Environment(); ';
        if (empty($context['harness_only'])) {
            $prefix .= 'require LOW_SEG2CAT_ROOT . "/low_seg2cat/autoload.php"; ';
        }
        $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr',
            '-d', 'auto_prepend_file=' . __DIR__ . '/diagnostics.php', '-d', 'sys_temp_dir=' . $temporary);
        if (!empty($context['opcache_permission'])) {
            $command = array_merge($command, array('-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_permission=1'));
        }
        if ($coverageDirectory) {
            $command = array_merge($command, array('-d', 'zend_extension=' . $extension, '-d', 'xdebug.mode=coverage'));
            $destination = var_export($coverageDirectory . '/child-' . bin2hex(random_bytes(8)) . '.json', true);
            $source = var_export(LOW_SEG2CAT_ROOT . '/low_seg2cat/', true);
            $metadata = var_export(array('probe_sha256' => hash('sha256', $code),
                'source_root' => LOW_SEG2CAT_ROOT,
                'ee_version' => isset($context['version']) ? $context['version'] : '7.5.0',
                'request' => isset($context['request']) ? $context['request'] : 'PAGE',
                'direct_access' => !empty($context['direct_access']), 'missing_phar' => !empty($context['missing_phar']),
                'opcache_permission' => !empty($context['opcache_permission']),
                'module_nav_layout' => isset($context['module_nav_layout']) ? $context['module_nav_layout'] : null,
                'fieldtype_layout' => isset($context['fieldtype_layout']) ? $context['fieldtype_layout'] : null), true);
            $prefix = 'xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK); ' .
                'register_shutdown_function(function () { $source = ' . $source . '; ' .
                '$coverage = array_filter(xdebug_get_code_coverage(), function ($path) use ($source) { return strpos($path, $source) === 0 || strpos($path, "phar://" . $source) === 0; }, ARRAY_FILTER_USE_KEY); ' .
                'foreach ($coverage as &$file) { foreach ($file["functions"] as &$function) { unset($function["paths"]); } unset($function); } unset($file); ' .
                'file_put_contents(' . $destination . ', json_encode($coverage)); ' .
                '$metadata = ' . $metadata . '; $metadata["php"] = PHP_VERSION; $metadata["xdebug"] = phpversion("xdebug"); ' .
                '$metadata["fieldtype_parent_sha256"] = getenv("LOW_SEG2CAT_FT_PARENT_SHA256") ?: null; ' .
                '$metadata["fieldtype_trait_sha256"] = getenv("LOW_SEG2CAT_FT_TRAIT_SHA256") ?: null; ' .
                '$metadata["module_nav_provenance"] = json_decode(getenv("LOW_SEG2CAT_MODULE_NAV_PROVENANCE") ?: "null", true); ' .
                'file_put_contents(' . $destination . ' . ".metadata.json", json_encode($metadata)); }); ' . $prefix;
        }
        $environment = getenv();
        $environment['TMPDIR'] = $temporary;
        $environment['TMP'] = $temporary;
        $environment['TEMP'] = $temporary;
        if ($coverageDirectory) {
            $environment['XDEBUG_MODE'] = 'coverage';
        }
        $environment['LOW_SEG2CAT_EE_VERSION'] = isset($context['version']) ? $context['version'] : '7.5.0';
        $environment['LOW_SEG2CAT_REQUEST_TYPE'] = isset($context['request']) ? $context['request'] : 'PAGE';
        $environment['LOW_SEG2CAT_DIRECT_ACCESS'] = !empty($context['direct_access']) ? '1' : '0';
        $environment['LOW_SEG2CAT_MISSING_PHAR'] = !empty($context['missing_phar']) ? '1' : '0';
        foreach (array('REAL_FIELDTYPE', 'FT_SYSTEM_PATH', 'FT_APP_PATH', 'FT_TRAIT_FILE', 'FT_PARENT_SHA256', 'FT_TRAIT_SHA256',
            'MODULE_NAV_THIRD_PATH', 'MODULE_NAV_PROVENANCE') as $key) {
            $environment['LOW_SEG2CAT_' . $key] = '';
        }
        try {
            if (!empty($context['module_nav_layout'])) {
                $environment = array_merge($environment, self::moduleNavEnvironment($context['module_nav_layout'], $temporary));
            }
            if (!empty($context['fieldtype_layout'])) {
                $environment = array_merge($environment, self::fieldtypeEnvironment($context['fieldtype_layout'], $temporary));
            }
            if (file_put_contents($probe, '<?php ' . $prefix . $code) === false) {
                throw new \RuntimeException('Cannot prepare the owned native probe.');
            }
            $timeout = isset($context['timeout_seconds']) ? $context['timeout_seconds'] : 15.0;
            $bindings = isset($context['bindings']) ? $context['bindings'] : array();
            $bindings['probe'] = $temporary;
            return self::execute(array_merge($command, array($probe)), $environment, $timeout, $bindings);
        } finally {
            self::removeDirectory($temporary);
        }
    }

    /**
     * Supply a real module-extension layout while executing the original archive.
     *
     * @param string $layout
     * @param string $temporary
     * @return array
     * @throws \RuntimeException
     */
    private static function moduleNavEnvironment($layout, $temporary)
    {
        if (!in_array($layout, array('defaults', 'configured'), true)) {
            throw new \RuntimeException('Unknown module navigation fixture layout.');
        }
        $third = $temporary . '/third/';
        $addon = $third . 'low_seg2cat/';
        mkdir($addon . 'Conduit', 0700, true);
        $provenance = array();
        foreach (array('low_seg2cat.phar', 'addon.setup.php', 'addon.json', 'autoload.php') as $name) {
            $source = LOW_SEG2CAT_ROOT . '/low_seg2cat/' . $name;
            if (!symlink($source, $addon . $name)) {
                throw new \RuntimeException('Could not prepare an owned module navigation layout.');
            }
            $provenance[$name] = array('source' => $source, 'sha256' => hash_file('sha256', $source));
        }
        $fixture = LOW_SEG2CAT_ROOT . '/tests/fixtures/module-nav/' . $layout . '.php';
        if (!symlink($fixture, $addon . 'Conduit/McpNav.php')) {
            throw new \RuntimeException('Could not register the module navigation fixture.');
        }
        $provenance['module_fixture'] = array('source' => $fixture, 'sha256' => hash_file('sha256', $fixture));
        return array('LOW_SEG2CAT_MODULE_NAV_THIRD_PATH' => $third,
            'LOW_SEG2CAT_MODULE_NAV_PROVENANCE' => json_encode($provenance));
    }

    /**
     * Prepare native loading layouts using an unchanged EE parent and trait.
     *
     * @param string $layout
     * @param string $temporary
     * @return array
     * @throws \RuntimeException
     */
    private static function fieldtypeEnvironment($layout, $temporary)
    {
        if (!in_array($layout, array('system', 'legacy', 'installer'), true)) {
            throw new \RuntimeException('Unknown native fieldtype layout.');
        }
        $system = rtrim(getenv('EE_SYSTEM_PATH') ?: dirname(LOW_SEG2CAT_ROOT, 2) . '/ee_repo_2/system', '/') . '/';
        $parent = $system . 'ee/legacy/fieldtypes/EE_Fieldtype.php';
        $trait = $system . 'ee/ExpressionEngine/Service/Template/Variables/ModifiableTrait.php';
        if (!is_file($parent) || !is_file($trait)) {
            throw new \RuntimeException('Set EE_SYSTEM_PATH to an ExpressionEngine system directory for native fieldtype loading.');
        }
        $environment = array('LOW_SEG2CAT_REAL_FIELDTYPE' => '1', 'LOW_SEG2CAT_FT_SYSTEM_PATH' => $system,
            'LOW_SEG2CAT_FT_APP_PATH' => $system . 'ee/legacy/', 'LOW_SEG2CAT_FT_TRAIT_FILE' => $trait,
            'LOW_SEG2CAT_FT_PARENT_SHA256' => hash_file('sha256', $parent),
            'LOW_SEG2CAT_FT_TRAIT_SHA256' => hash_file('sha256', $trait));
        if ($layout === 'system') {
            return $environment;
        }
        $environment['LOW_SEG2CAT_FT_SYSTEM_PATH'] = $temporary . '/missing-system/';
        if ($layout === 'legacy') {
            return $environment;
        }
        $environment['LOW_SEG2CAT_FT_APP_PATH'] = $temporary . '/installer/';
        $target = $temporary . '/expressionengine/fieldtypes/EE_Fieldtype.php';
        mkdir(dirname($target), 0700, true);
        copy($parent, $target);
        if (hash_file('sha256', $target) !== $environment['LOW_SEG2CAT_FT_PARENT_SHA256']) {
            throw new \RuntimeException('Native EE parent copy differs from its read-only source.');
        }
        return $environment;
    }

    /**
     * Run a command without shell interpolation and retain both output streams.
     *
     * @param array $command
     * @param array|null $environment
     * @param float $timeoutSeconds
     * @param array $bindings Explicit owned input paths, never diagnostic authority.
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function execute(array $command, ?array $environment = null, $timeoutSeconds = 15.0, array $bindings = array())
    {
        $original = $command;
        $prepend = null;
        if (isset($command[0]) && $command[0] === PHP_BINARY) {
            $command = self::guardPhpCommand($command, $prepend);
        }
        if (!is_numeric($timeoutSeconds) || !is_finite((float) $timeoutSeconds) || $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Process deadline must be positive and finite.');
        }
        $environment = $environment === null ? getenv() : $environment;
        list($directory, $environment) = \LowSeg2CatNativeInvocation::begin($original, $command, $environment, $timeoutSeconds, $bindings);
        $output = fopen($directory . '/stdout', 'w+');
        $errors = fopen($directory . '/stderr', 'w+');
        if ($output === false || $errors === false) {
            throw new \RuntimeException('Could not allocate retained process streams.');
        }
        $process = null;
        $result = array('status' => null, 'timed_out' => false, 'stdout' => '', 'stderr' => '');
        try {
            if ($prepend !== null) {
                self::validatePrepend($prepend, $bindings);
            }
            $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => $output, 2 => $errors), $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Could not start PHP process.');
            }
            fclose($pipes[0]);
            \LowSeg2CatNativeInvocation::started($directory, proc_get_status($process), $timeoutSeconds);
            $deadline = hrtime(true) + (int) ($timeoutSeconds * 1000000000);
            $state = self::waitForExit($process, $deadline);
            $result['timed_out'] = $state['running'];
            if ($result['timed_out']) {
                proc_terminate($process);
                $state = self::waitForExit($process, hrtime(true) + 200000000);
            }
            if ($state['running']) {
                proc_terminate($process, 9);
                $state = self::waitForExit($process, hrtime(true) + 1000000000);
            }
            if ($state['running']) {
                throw new \RuntimeException('Owned PHP child did not terminate after SIGKILL.');
            }
            // Older PHP loses the collected exit code when proc_close reaps it.
            $exitCode = $state['exitcode'];
            $closed = proc_close($process);
            $process = null;
            $status = $exitCode >= 0 ? $exitCode : $closed;
            if ($status < 0 && $state['signaled']) {
                $status = 128 + $state['termsig'];
            }
            $result['status'] = $result['timed_out'] ? 124 : $status;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            rewind($output);
            rewind($errors);
            $result['stdout'] = stream_get_contents($output);
            $result['stderr'] = stream_get_contents($errors);
            $journal = $environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'];
            \LowSeg2CatDiagnostics::observeNativeOutput($result['stderr'], $journal, 0);
            \LowSeg2CatNativeInvocation::complete($directory, $result);
            fclose($output);
            fclose($errors);
        }

        return $result;
    }

    /**
     * Poll one owned child until it exits or the monotonic deadline arrives.
     *
     * @param resource $process
     * @param int $deadline
     * @return array
     */
    private static function waitForExit($process, $deadline)
    {
        do {
            $state = proc_get_status($process);
            if (!$state['running'] || hrtime(true) >= $deadline) {
                return $state;
            }
            usleep(10000);
        } while (true);
    }

    /**
     * Restore owned directory permissions and remove entries without following links.
     *
     * @param string $directory
     * @return void
     * @throws \ErrorException
     */
    private static function removeDirectory($directory)
    {
        if (is_link($directory) || is_file($directory)) {
            unlink($directory);
            return;
        }
        if (!is_dir($directory)) {
            return;
        }
        chmod($directory, 0700);
        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isLink() || !$file->isDir()) {
                unlink($file->getPathname());
                continue;
            }
            self::removeDirectory($file->getPathname());
        }
        rmdir($directory);
    }
    /**
     * Guard owned PHP file and inline entrypoints before compiling their source.
     *
     * @param array $command Selected PHP command and arguments.
     * @param string|null $prepend Effective declared native prepend path.
     * @return array Strict command with application arguments unchanged.
     */
    private static function guardPhpCommand(array $command, &$prepend): array
    {
        $guard = __DIR__ . '/diagnostics.php';
        $hasPrepend = false;
        $inlineOptions = array('-r', '--run', '-B', '--process-begin', '-R', '--process-code', '-E', '--process-end');
        $valueOptions = array('-d', '--define', '-c', '--php-ini', '-z', '--zend-extension',
            '-t', '--docroot', '-S', '--server', '-f', '--file', '-F', '--process-file',
            '--rf', '--rc', '--re', '--rz', '--ri');
        for ($index = 1; isset($command[$index]); $index++) {
            $argument = $command[$index];
            if ($argument === '--' || $argument === '' || $argument[0] !== '-') {
                break;
            }
            if (in_array($argument, $valueOptions, true)) {
                if (($argument === '-d' || $argument === '--define') && isset($command[$index + 1])
                    && strpos($command[$index + 1], 'auto_prepend_file=') === 0) {
                    $hasPrepend = true;
                    $prepend = substr($command[$index + 1], strlen('auto_prepend_file='));
                }
                $index++;
                continue;
            }
            if (strpos($argument, '-dauto_prepend_file=') === 0 || strpos($argument, '--define=auto_prepend_file=') === 0) {
                $hasPrepend = true;
                $prepend = substr($argument, strpos($argument, 'auto_prepend_file=') + strlen('auto_prepend_file='));
                continue;
            }
            if (in_array($argument, $inlineOptions, true) && isset($command[$index + 1])) {
                $code = $command[++$index];
                $command[$index] = self::guardInlineCode($code, $guard);
                continue;
            }
            foreach ($inlineOptions as $option) {
                $prefix = strlen($option) === 2 ? $option : $option . '=';
                if (strpos($argument, $prefix) !== 0 || strlen($argument) <= strlen($prefix)) {
                    continue;
                }
                $code = substr($argument, strlen($prefix));
                $command[$index] = $prefix . self::guardInlineCode($code, $guard);
                break;
            }
        }
        $flags = array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1', '-d', 'log_errors=0');
        if (!$hasPrepend) {
            $prepend = $guard;
            $flags[] = '-d';
            $flags[] = 'auto_prepend_file=' . $guard;
        }
        array_splice($command, 1, 0, $flags);
        return $command;
    }

    /**
     * Require the canonical guard before any declared native primary prelude.
     *
     * @param string $prepend Effective original PHP prepend path.
     * @param array $bindings Declared owned input paths retained before validation.
     * @return void
     * @throws \RuntimeException When a prelude could execute without strict diagnostics.
     */
    private static function validatePrepend($prepend, array $bindings): void
    {
        $guard = __DIR__ . '/diagnostics.php';
        if (is_file($prepend) && hash_file('sha256', $prepend) === hash_file('sha256', $guard)) {
            return;
        }
        if (isset($bindings['work']) && $prepend === $bindings['work'] . '/gate.php') {
            $expected = '<?php require ' . var_export($guard, true) . '; require '
                . var_export($bindings['work'] . '/prepend.php', true) . ';';
            if (is_file($prepend) && file_get_contents($prepend) === $expected) {
                return;
            }
        }
        throw new \RuntimeException('Native PHP prepend must use the canonical guard or an exact declared primary gate.');
    }

    /**
     * Install the native guard after required declarations without relocating inline code.
     *
     * @param string $code Original CLI code without an opening tag.
     * @param string $guard Canonical diagnostic bootstrap.
     * @return string Original inline source with a same-line guard statement.
     */
    private static function guardInlineCode($code, $guard): string
    {
        $tokens = \token_get_all('<?php ' . $code);
        $offset = \strlen($tokens[0][1]);
        $insertion = 0;
        $count = \count($tokens);
        for ($index = 1; $index < $count;) {
            $token = $tokens[$index];
            if (\is_array($token) && \in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                $offset += \strlen($token[1]);
                $index++;
                continue;
            }
            if (!\is_array($token) || !\in_array($token[0], array(T_DECLARE, T_NAMESPACE), true)) {
                break;
            }

            $declaration = $token[0];
            $namespaceName = false;
            $depth = 0;
            $complete = false;
            for (; $index < $count; $index++) {
                $part = $tokens[$index];
                $offset += \strlen(\is_array($part) ? $part[1] : $part);
                if (\is_array($part)) {
                    if ($declaration === T_NAMESPACE) {
                        if ($part[0] === T_NS_SEPARATOR && !$namespaceName) {
                            break 2;
                        }
                        if (!\in_array($part[0], array(T_NAMESPACE, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                            $namespaceName = true;
                        }
                    }
                    continue;
                }
                if ($part === '(') {
                    $depth++;
                }
                if ($part === ')') {
                    $depth--;
                }
                if ($depth !== 0 || !\in_array($part, array(';', '{', ':'), true)) {
                    continue;
                }
                $insertion = $offset - 6;
                $complete = true;
                $index++;
                if ($part !== ';') {
                    break 2;
                }
                break;
            }
            if (!$complete) {
                break;
            }
        }

        return \substr($code, 0, $insertion) . 'require_once ' . \var_export($guard, true) . ';'
            . \substr($code, $insertion);
    }

}
