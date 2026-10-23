<?php

require_once __DIR__ . '/check-junit.php';

/**
 * Interpret options with the pinned runner's own parser, including option values.
 *
 * @param array $arguments
 * @return array
 * @throws \PHPUnit\TextUI\CliArguments\Exception
 */
function low_seg2cat_runner_arguments(array $arguments)
{
    $parsed = (new \PHPUnit\TextUI\CliArguments\Builder())->fromParameters($arguments, array());
    $inspection = false;
    foreach (array('Help', 'Version', 'AtLeastVersion', 'CheckVersion', 'ListGroups', 'ListSuites', 'ListTests', 'ListTestsXml') as $option) {
        $has = 'has' . $option;
        $get = lcfirst($option);
        if ($parsed->$has() && $parsed->$get()) {
            $inspection = true;
        }
    }
    return array('inspection' => $inspection,
        'caller_junit' => $parsed->hasJunitLogfile() ? $parsed->junitLogfile() : null);
}

/**
 * Validate fresh executed cases and preserve the caller's requested report.
 *
 * @param string $junit
 * @param array $arguments
 * @return void
 * @throws RuntimeException
 */
function low_seg2cat_validate_run($junit, array $arguments)
{
    if ($arguments['inspection']) {
        return;
    }
    low_seg2cat_junit_case_count($junit);
    if ($arguments['caller_junit'] === null) {
        return;
    }
    $parent = dirname($arguments['caller_junit']);
    if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
        throw new RuntimeException('Could not create the caller JUnit report directory.');
    }
    if (!copy($junit, $arguments['caller_junit'])) {
        throw new RuntimeException('Could not preserve the caller JUnit report.');
    }
}

if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {
    try {
        if (isset($argv[1]) && $argv[1] === 'prepare') {
            $phpunit = __DIR__ . '/phpunit-9.phar';
            if (hash_file('sha256', $phpunit) !== '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7') {
                throw new RuntimeException('Pinned PHPUnit 9.6.38 checksum mismatch.');
            }
            require $phpunit;
            $arguments = low_seg2cat_runner_arguments(array_merge(array($phpunit), array_slice($argv, 3)));
            if (file_put_contents($argv[2], json_encode($arguments)) === false) {
                throw new RuntimeException('Could not record fresh runner arguments.');
            }
        } else {
            $arguments = isset($argv[2]) && is_file($argv[2]) ? json_decode(file_get_contents($argv[2]), true) : null;
            if (!is_array($arguments) || !array_key_exists('inspection', $arguments) || !array_key_exists('caller_junit', $arguments)) {
                throw new RuntimeException('Missing fresh runner argument record.');
            }
            low_seg2cat_validate_run(isset($argv[3]) ? $argv[3] : '', $arguments);
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(2);
    }
}
