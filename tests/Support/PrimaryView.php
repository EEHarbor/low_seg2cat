<?php

namespace Tests\Support;

class PrimaryView
{
    /**
     * Execute an original license view as the primary script with verified bytes.
     *
     * @param string $layout
     * @param array $variables
     * @param string $prepare Owned diagnostic/early-exit control code.
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function render($layout, array $variables, $prepare = '')
    {
        if (!in_array($layout, array('loose', 'archive'), true)) {
            throw new \InvalidArgumentException('Unknown primary license-view layout.');
        }
        $unit = $layout === 'loose' ? 'low_seg2cat/views/license.php' : 'low_seg2cat/low_seg2cat.phar::Views/license.php';
        $original = $layout === 'loose' ? LOW_SEG2CAT_ROOT . '/' . $unit
            : 'phar://' . LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar/Views/license.php';
        $temporary = sys_get_temp_dir() . '/low-seg2cat-primary-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700)) {
            throw new \RuntimeException('Cannot create an owned primary-view directory.');
        }
        $canonical = realpath($temporary);
        if ($canonical === false) {
            rmdir($temporary);
            throw new \RuntimeException('Cannot resolve the owned primary-view directory.');
        }
        $temporary = $canonical;
        try {
            $measured = $layout === 'loose' ? $original : $temporary . '/license.php';
            if ($layout === 'archive' && !copy($original, $measured)) {
                throw new \RuntimeException('Cannot prepare identical archive measurement bytes.');
            }
            $sourceHash = hash_file('sha256', $original);
            if (hash_file('sha256', $measured) !== $sourceHash) {
                throw new \RuntimeException('Primary measurement bytes differ from shipped source.');
            }
            $prepend = $temporary . '/prepend.php';
            $gate = $temporary . '/gate.php';
            $append = $temporary . '/append.php';
            $completed = $temporary . '/completed';
            $provenance = array('inventory_name' => $unit, 'original_source' => $original,
                'original_sha256' => $sourceHash, 'measured_source' => $measured, 'measurement_sha256' => $sourceHash);
            $prefix = '<?php require ' . var_export(__DIR__ . '/native.php', true) . '; ' .
                'ini_set("display_errors", "stderr"); ' .
                '\Tests\Support\Environment::installDiagnostics(); ' .
                '$GLOBALS["low_seg2cat_app"] = new \Tests\Support\Environment(); ' .
                '$GLOBALS["low_seg2cat_app"]->services["CP/Alert"] = new \Tests\Support\CallbackService(array(' .
                '"getAllInlines" => function () { return "<aside>alerts</aside>"; })); ';
            $coverageDirectory = getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR');
            $extension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
            $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
                '-d', 'sys_temp_dir=' . $temporary, '-d', 'auto_prepend_file=' . $gate, '-d', 'auto_append_file=' . $append);
            if ($coverageDirectory) {
                if (!$extension || !is_file($extension) || !is_dir($coverageDirectory)) {
                    throw new \RuntimeException('Primary coverage needs an explicit matching Xdebug extension and owned report directory.');
                }
                $command = array_merge($command, array('-d', 'zend_extension=' . $extension, '-d', 'xdebug.mode=coverage'));
                $destination = $coverageDirectory . '/child-' . bin2hex(random_bytes(8)) . '.json';
                $metadata = array('probe_sha256' => hash('sha256', $layout . serialize($variables) . $prepare),
                    'source_root' => LOW_SEG2CAT_ROOT, 'primary_view' => $layout,
                    'inventory_sha256' => hash_file('sha256', LOW_SEG2CAT_ROOT . '/tests/SOURCE_INVENTORY.json'),
                    'flags' => 'UNUSED|DEAD_CODE|BRANCH_CHECK',
                    'template_ee_version' => isset($variables['ee_ver']) ? $variables['ee_ver'] : null, 'measurement_source' => $provenance);
                $prefix .= self::collector($measured, $completed, $destination, $metadata);
            }
            $prefix .= 'extract(' . var_export($variables, true) . '); ' . $prepare;
            if (file_put_contents($gate, '<?php require ' . var_export(__DIR__ . '/diagnostics.php', true) .
                '; require ' . var_export($prepend, true) . ';') === false ||
                file_put_contents($prepend, $prefix) === false ||
                file_put_contents($append, '<?php file_put_contents(' . var_export($completed, true) . ', "complete");') === false) {
                throw new \RuntimeException('Cannot prepare primary-view bootstrap files.');
            }
            $environment = getenv();
            foreach (array('TMPDIR', 'TMP', 'TEMP') as $key) {
                $environment[$key] = $temporary;
            }
            foreach (array('MODULE_NAV_THIRD_PATH', 'MODULE_NAV_PROVENANCE', 'DIRECT_ACCESS', 'MISSING_PHAR') as $key) {
                $environment['LOW_SEG2CAT_' . $key] = '';
            }
            $environment['LOW_SEG2CAT_REQUEST_TYPE'] = 'CP';
            if ($coverageDirectory) {
                $environment['XDEBUG_MODE'] = 'coverage';
            }
            $result = Process::execute(array_merge($command, array($measured)), $environment);
            if ($result['status'] === 0 && !is_file($completed)) {
                $result['status'] = 2;
                $result['stderr'] .= "Primary view did not complete.\n";
            }
            $result['measurement_source'] = $provenance;
            return $result;
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($temporary);
        }
    }

    /**
     * Publish one completed original-source graph without combining contexts.
     *
     * @param string $measured
     * @param string $completed
     * @param string $destination
     * @param array $metadata
     * @return string
     */
    private static function collector($measured, $completed, $destination, array $metadata)
    {
        return 'xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK); ' .
            'register_shutdown_function(function () { if (!is_file(' . var_export($completed, true) . ')) { return; } ' .
            '$measured = ' . var_export($measured, true) . '; $all = xdebug_get_code_coverage(); ' .
            'if (!isset($all[$measured])) { throw new \RuntimeException("Primary source graph is absent."); } ' .
            '$coverage = array($measured => $all[$measured]); foreach ($coverage[$measured]["functions"] as &$function) { ' .
            'unset($function["paths"]); } unset($function); $raw = json_encode($coverage, JSON_THROW_ON_ERROR); ' .
            '$metadata = ' . var_export($metadata, true) . '; $metadata["php"] = PHP_VERSION; $metadata["xdebug"] = phpversion("xdebug"); ' .
            '$metadata["application_version"] = APP_VER; $metadata["request"] = REQ; ' .
            '$metadata["raw_sha256"] = hash("sha256", $raw); ' .
            'file_put_contents(' . var_export($destination, true) . ', $raw); ' .
            'file_put_contents(' . var_export($destination . '.metadata.json', true) . ', json_encode($metadata, JSON_THROW_ON_ERROR)); }); ';
    }
}
