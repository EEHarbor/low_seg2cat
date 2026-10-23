<?php

namespace Tests\Support;

class PrimaryLegacy
{
    /**
     * Compile the shipped accessor without the ordinary test facade defining ee().
     *
     * @param bool $existingAccessor Whether the legacy bootstrap already owns ee().
     * @param bool $definedVersion Whether APP_VER is defined before source loading.
     * @param string $prepare Owned startup diagnostic or lifetime control.
     * @return array
     * @throws \RuntimeException
     */
    public static function accessor($existingAccessor, $definedVersion, $prepare = '')
    {
        $unit = 'low_seg2cat/low_seg2cat.phar::FluxCapacitor/Bridge/EE.php';
        $original = 'phar://' . LOW_SEG2CAT_ROOT . '/' . str_replace('::', '/', $unit);
        $inventory = json_decode(file_get_contents(LOW_SEG2CAT_ROOT . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
        $hash = hash_file('sha256', $original);
        if ($inventory[$unit]['sha256'] !== $hash) {
            throw new \RuntimeException('Legacy accessor source differs from the immutable inventory.');
        }
        $temporary = sys_get_temp_dir() . '/low-seg2cat-accessor-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0700)) {
            throw new \RuntimeException('Cannot allocate the owned legacy accessor directory.');
        }
        $canonical = realpath($temporary);
        if ($canonical === false) {
            rmdir($temporary);
            throw new \RuntimeException('Cannot resolve the owned legacy accessor directory.');
        }
        $temporary = $canonical;
        try {
            $measured = $temporary . '/EE.php';
            if (!copy($original, $measured) || hash_file('sha256', $measured) !== $hash) {
                throw new \RuntimeException('Legacy primary source must remain byte-identical.');
            }
            $gate = $temporary . '/gate.php';
            $prepend = $temporary . '/prepend.php';
            $append = $temporary . '/append.php';
            $completed = $temporary . '/completed';
            $provenance = array('inventory_name' => $unit, 'original_source' => $original,
                'original_sha256' => $hash, 'measured_source' => $measured, 'measurement_sha256' => $hash);
            $prefix = '<?php define("BASEPATH", true); define("REQ", "PAGE"); ' .
                '$GLOBALS["accessor_calls"] = 0; $GLOBALS["accessor_config_calls"] = array(); ' .
                '$GLOBALS["accessor_app"] = (object) array("marker" => "first", "config" => new class {' .
                'public function item($key) { $GLOBALS["accessor_config_calls"][] = $key; return "2.5.5"; }}); ' .
                'function get_instance() { $GLOBALS["accessor_calls"]++; return $GLOBALS["accessor_app"]; } ';
            if ($definedVersion) {
                $prefix .= 'define("APP_VER", "2.5.5"); ';
            }
            if ($existingAccessor) {
                $prefix .= 'function ee() { return get_instance(); } ';
            }
            $bridge = dirname($original) . '/';
            $prefix .= 'require ' . var_export($bridge . 'AbstractBridge.php', true) . '; require ' .
                var_export($bridge . 'EE2.php', true) . '; $GLOBALS["accessor_before"] = function_exists("ee"); ';
            $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
                '-d', 'sys_temp_dir=' . $temporary, '-d', 'auto_prepend_file=' . $gate, '-d', 'auto_append_file=' . $append);
            $coverageDirectory = getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR');
            $extension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
            if ($coverageDirectory) {
                if (!$extension || !is_file($extension) || !is_dir($coverageDirectory)) {
                    throw new \RuntimeException('Legacy primary coverage needs an explicit extension and report directory.');
                }
                $command = array_merge($command, array('-d', 'zend_extension=' . $extension, '-d', 'xdebug.mode=coverage'));
                $destination = $coverageDirectory . '/child-' . bin2hex(random_bytes(8)) . '.json';
                $metadata = array('probe_sha256' => hash('sha256', serialize(array($existingAccessor, $definedVersion, $prepare))),
                    'source_root' => LOW_SEG2CAT_ROOT, 'primary_legacy_accessor' => true,
                    'existing_accessor' => $existingAccessor, 'predefined_application_version' => $definedVersion,
                    'inventory_sha256' => hash_file('sha256', LOW_SEG2CAT_ROOT . '/tests/SOURCE_INVENTORY.json'),
                    'flags' => 'UNUSED|DEAD_CODE|BRANCH_CHECK', 'measurement_source' => $provenance);
                $prefix .= self::collector($measured, $completed, $destination, $metadata);
            }
            $prefix .= $prepare;
            $suffix = '<?php $first = ee(); $firstSame = $first === $GLOBALS["accessor_app"]; ' .
                '$GLOBALS["accessor_app"] = (object) array("marker" => "second"); $second = ee(); ' .
                'echo json_encode(array("before" => $GLOBALS["accessor_before"], "after" => function_exists("ee"), ' .
                '"first_same" => $firstSame, "second_same" => $second === $GLOBALS["accessor_app"], ' .
                '"different" => $first !== $second, "calls" => $GLOBALS["accessor_calls"], ' .
                '"config_calls" => $GLOBALS["accessor_config_calls"], "version" => APP_VER, ' .
                '"function_file" => (new ReflectionFunction("ee"))->getFileName(), "binary" => PHP_BINARY, ' .
                '"php" => PHP_VERSION, "mask" => error_reporting(), "e_all" => E_ALL), JSON_THROW_ON_ERROR); ' .
                'file_put_contents(' . var_export($completed, true) . ', "complete");';
            if (file_put_contents($gate, '<?php require ' . var_export(__DIR__ . '/diagnostics.php', true) .
                    '; require ' . var_export($prepend, true) . ';') === false ||
                file_put_contents($prepend, $prefix) === false || file_put_contents($append, $suffix) === false) {
                throw new \RuntimeException('Cannot prepare the owned legacy bootstrap.');
            }
            $environment = getenv();
            foreach (array('TMPDIR', 'TMP', 'TEMP') as $name) {
                $environment[$name] = $temporary;
            }
            if ($coverageDirectory) {
                $environment['XDEBUG_MODE'] = 'coverage';
            }
            $result = Process::execute(array_merge($command, array($measured)), $environment);
            if ($result['status'] === 0 && (!is_file($completed) || file_get_contents($completed) !== 'complete')) {
                $result['status'] = 2;
                $result['stderr'] .= "Legacy accessor did not complete.\n";
            }
            $result['measurement_source'] = $provenance;
            $result['existing_accessor_file'] = $prepend;
            return $result;
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
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
     * Retain only the completed primary source representation and its provenance.
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
            'register_shutdown_function(function () { $completed = ' . var_export($completed, true) . '; ' .
            'if (!is_file($completed) || file_get_contents($completed) !== "complete") { return; } ' .
            '$measured = ' . var_export($measured, true) . '; $all = xdebug_get_code_coverage(); ' .
            'if (!isset($all[$measured])) { throw new RuntimeException("Legacy primary graph is absent."); } ' .
            '$coverage = array($measured => $all[$measured]); foreach ($coverage[$measured]["functions"] as &$function) {' .
            'unset($function["paths"]); } unset($function); $raw = json_encode($coverage, JSON_THROW_ON_ERROR); ' .
            '$metadata = ' . var_export($metadata, true) . '; $metadata["php"] = PHP_VERSION; $metadata["xdebug"] = phpversion("xdebug"); ' .
            '$metadata["php_binary"] = PHP_BINARY; $metadata["application_version"] = APP_VER; $metadata["request"] = REQ; ' .
            '$metadata["error_reporting"] = error_reporting(); $metadata["e_all"] = E_ALL; ' .
            '$metadata["raw_sha256"] = hash("sha256", $raw); ' .
            'file_put_contents(' . var_export($destination, true) . ', $raw); ' .
            'file_put_contents(' . var_export($destination . '.metadata.json', true) . ', json_encode($metadata, JSON_THROW_ON_ERROR)); }); ';
    }
}
