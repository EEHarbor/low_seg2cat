<?php

require_once dirname(__DIR__) . '/Support/diagnostics.php';
require_once __DIR__ . '/check-junit.php';

/**
 * Interpret options with the pinned runner's own parser, including option values.
 *
 * @param array $arguments
 * @param string $junit Fresh owned report path.
 * @return array
 * @throws \Throwable
 */
function low_seg2cat_runner_arguments(array $arguments, $junit)
{
    $parsed = (new \PHPUnit\TextUI\CliArguments\Builder())->fromParameters($arguments, array());
    $inspection = false;
    foreach (array('Help', 'Version', 'AtLeastVersion', 'CheckVersion', 'ListGroups', 'ListSuites', 'ListTests', 'ListTestsXml') as $option) {
        $has = 'has' . $option;
        $get = lcfirst($option);
        if (!$parsed->$has()) {
            continue;
        }
        $value = $parsed->$get();
        if ($value === true || (is_string($value) && $value !== '')) {
            $inspection = true;
        }
    }
    // The checksum pin also fixes the parser's option definitions.
    $definitions = new ReflectionClass(\PHPUnit\TextUI\CliArguments\Builder::class);
    $options = (new \PHPUnitPHAR\SebastianBergmann\CliParser\Parser())->parse($arguments,
        $definitions->getConstant('SHORT_OPTIONS'), $definitions->getConstant('LONG_OPTIONS'));
    $forwarded = array();
    foreach ($options[0] as $option) {
        if ($option[0] === '--log-junit') {
            continue;
        }
        $name = strpos($option[0], '--') === 0 ? $option[0] : '-' . $option[0];
        if ($option[1] === null) {
            $forwarded[] = $name;
            continue;
        }
        if (strpos($name, '--') === 0) {
            $forwarded[] = $name . '=' . $option[1];
            continue;
        }
        $forwarded[] = $name;
        $forwarded[] = $option[1];
    }
    $forwarded[] = '--log-junit=' . $junit;
    if ($options[1]) {
        $forwarded[] = '--';
        $forwarded = array_merge($forwarded, $options[1]);
    }
    return array('inspection' => $inspection,
        'caller_junit' => $parsed->hasJunitLogfile() ? $parsed->junitLogfile() : null,
        'forwarded' => $forwarded);
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
            $arguments = low_seg2cat_runner_arguments(array_merge(array($phpunit), array_slice($argv, 3)),
                dirname($argv[2]) . '/junit.xml');
            if (file_put_contents($argv[2] . '.nul', implode("\0", $arguments['forwarded']) . "\0") === false) {
                throw new RuntimeException('Could not record fresh runner options.');
            }
            unset($arguments['forwarded']);
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
