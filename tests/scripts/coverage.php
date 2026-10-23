<?php

if (!extension_loaded('xdebug') || !function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "Branch-capable Xdebug is required.\n");
    exit(2);
}
$destination = getenv('LOW_SEG2CAT_COVERAGE');
if (!$destination || file_exists($destination) || file_exists($destination . '.metadata.json') || file_exists($destination . '.junit.xml')) {
    fwrite(STDERR, "Set LOW_SEG2CAT_COVERAGE to a fresh report path.\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$phpunit = $root . '/tests/.phpunit/phpunit-9.phar';
if (hash_file('sha256', $phpunit) !== '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7') {
    fwrite(STDERR, "Pinned PHPUnit 9.6.38 checksum mismatch.\n");
    exit(2);
}
require $phpunit;
require $root . '/tests/.phpunit/runner.php';
$junit = $destination . '.junit.xml';
$arguments = low_seg2cat_runner_arguments($_SERVER['argv']);
if ($arguments['inspection']) {
    exit(\PHPUnit\TextUI\Command::main(false));
}
$_SERVER['argv'] = array_merge($_SERVER['argv'], array('--log-junit', $junit));
$source = $root . '/low_seg2cat/';
$childDirectory = $destination . '.children';
if (!mkdir($childDirectory, 0700)) {
    throw new RuntimeException('Cannot create fresh child coverage directory.');
}
putenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR=' . $childDirectory);
$xdebugFlags = XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK;
// PHPUnit owns line-coverage collection; this independent run retains raw branches.
xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, array($source, 'phar://' . $source));
xdebug_start_code_coverage($xdebugFlags);
register_shutdown_function(function () use ($destination, $root, $source, $junit, $arguments) {
    try {
        low_seg2cat_validate_run($junit, $arguments);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(2);
    }
    $coverage = xdebug_get_code_coverage();
    xdebug_stop_code_coverage(false);
    $coverage = array_filter($coverage, function ($path) use ($source) {
        return strpos($path, $source) === 0 || strpos($path, 'phar://' . $source) === 0;
    }, ARRAY_FILTER_USE_KEY);
    foreach ($coverage as &$file) {
        foreach ($file['functions'] as &$function) {
            unset($function['paths']);
        }
        unset($function);
    }
    unset($file);
    file_put_contents($destination, json_encode($coverage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($destination . '.metadata.json', json_encode(array('php' => PHP_VERSION,
        'source_root' => $root,
        'xdebug' => phpversion('xdebug'), 'inventory_sha256' => hash_file('sha256', $root . '/tests/SOURCE_INVENTORY.json'),
        'flags' => 'UNUSED|DEAD_CODE|BRANCH_CHECK', 'children' => $destination . '.children'), JSON_PRETTY_PRINT));
});
exit(\PHPUnit\TextUI\Command::main(false));
