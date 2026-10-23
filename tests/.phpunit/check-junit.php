<?php

/**
 * Reject missing, malformed, inconsistent or empty fresh PHPUnit reports.
 *
 * @param string $path
 * @return int
 * @throws RuntimeException
 */
function low_seg2cat_junit_case_count($path)
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('No fresh JUnit report was produced.');
    }
    $xml = file_get_contents($path);
    if ($xml === '') {
        throw new RuntimeException('Empty fresh JUnit report.');
    }
    if (stripos($xml, '<!DOCTYPE') !== false) {
        throw new RuntimeException('JUnit document types are not accepted.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Malformed fresh JUnit report.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $root = $document->documentElement;
    if (!$root || !in_array($root->tagName, array('testsuites', 'testsuite'), true)) {
        throw new RuntimeException('Unexpected JUnit document root.');
    }
    $cases = $root->getElementsByTagName('testcase')->length;
    $declared = 0;
    $suites = $root->tagName === 'testsuite' ? array($root) : $root->childNodes;
    foreach ($suites as $suite) {
        if (!$suite instanceof DOMElement) {
            continue;
        }
        if ($suite->tagName !== 'testsuite' || !preg_match('/^\d+$/D', $suite->getAttribute('tests'))) {
            throw new RuntimeException('Invalid JUnit suite count.');
        }
        $declared += (int) $suite->getAttribute('tests');
    }
    if ($cases === 0 || $declared !== $cases) {
        throw new RuntimeException('JUnit selection is empty or its case count is inconsistent.');
    }
    return $cases;
}

if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {
    try {
        low_seg2cat_junit_case_count(isset($argv[1]) ? $argv[1] : '');
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(2);
    }
}
