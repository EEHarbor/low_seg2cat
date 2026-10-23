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
        $coverageDirectory = getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR');
        $extension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
        if ($coverageDirectory && (!$extension || !is_file($extension) || !is_dir($coverageDirectory))) {
            throw new \RuntimeException('Child coverage requires an explicit Xdebug extension and report directory.');
        }
        $temporary = sys_get_temp_dir() . '/low-seg2cat-probe-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700)) {
            throw new \RuntimeException('Cannot create isolated process directory.');
        }
        $bootstrap = var_export(__DIR__ . '/native.php', true);
        $prefix = 'require ' . $bootstrap . '; \Tests\Support\Environment::installDiagnostics(); ' .
            '$GLOBALS["low_seg2cat_app"] = new \Tests\Support\Environment(); ' .
            'require LOW_SEG2CAT_ROOT . "/low_seg2cat/autoload.php"; ';
        $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'sys_temp_dir=' . $temporary);
        if ($coverageDirectory) {
            $command = array_merge($command, array('-d', 'zend_extension=' . $extension, '-d', 'xdebug.mode=coverage'));
            $destination = var_export($coverageDirectory . '/child-' . bin2hex(random_bytes(8)) . '.json', true);
            $source = var_export(LOW_SEG2CAT_ROOT . '/low_seg2cat/', true);
            $metadata = var_export(array('probe_sha256' => hash('sha256', $code),
                'source_root' => LOW_SEG2CAT_ROOT,
                'ee_version' => isset($context['version']) ? $context['version'] : '7.5.0',
                'request' => isset($context['request']) ? $context['request'] : 'PAGE'), true);
            $prefix = 'xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK); ' .
                'register_shutdown_function(function () { $source = ' . $source . '; ' .
                '$coverage = array_filter(xdebug_get_code_coverage(), function ($path) use ($source) { return strpos($path, $source) === 0 || strpos($path, "phar://" . $source) === 0; }, ARRAY_FILTER_USE_KEY); ' .
                'foreach ($coverage as &$file) { foreach ($file["functions"] as &$function) { unset($function["paths"]); } unset($function); } unset($file); ' .
                'file_put_contents(' . $destination . ', json_encode($coverage)); ' .
                '$metadata = ' . $metadata . '; $metadata["php"] = PHP_VERSION; $metadata["xdebug"] = phpversion("xdebug"); ' .
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
        try {
            return self::execute(array_merge($command, array('-r', $prefix . $code)), $environment);
        } finally {
            self::removeDirectory($temporary);
        }
    }

    /**
     * Run a command without shell interpolation and retain both output streams.
     *
     * @param array $command
     * @param array|null $environment
     * @return array
     * @throws \RuntimeException
     */
    public static function execute(array $command, ?array $environment = null)
    {
        $output = tmpfile();
        $errors = tmpfile();
        if ($output === false || $errors === false) {
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_resource($errors)) {
                fclose($errors);
            }
            throw new \RuntimeException('Could not allocate process output streams.');
        }
        try {
            $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => $output, 2 => $errors), $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Could not start PHP process.');
            }
            fclose($pipes[0]);
            $status = proc_close($process);
            rewind($output);
            rewind($errors);
            return array('status' => $status, 'stdout' => stream_get_contents($output), 'stderr' => stream_get_contents($errors));
        } finally {
            fclose($output);
            fclose($errors);
        }
    }

    /**
     * Remove only the newly allocated directory, without following symlink targets.
     *
     * @param string $directory
     * @return void
     */
    private static function removeDirectory($directory)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }
}
