<?php

namespace Tests;

use Tests\Support\Process;

class RunnerFixtureTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Reject an empty selection even when an earlier caller report exists.
     *
     * @return void
     */
    public function testLauncherRejectsEmptyFilteredSelection()
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        file_put_contents($report, 'stale-report');
        try {
            $result = $this->launch(array('--filter', 'definitely_no_case_matches', '--log-junit=' . $report));
            $this->assertSame(2, $result['status']);
            $this->assertStringContainsString('empty', $result['stderr']);
            $this->assertSame('stale-report', file_get_contents($report));
        } finally {
            unlink($report);
        }
    }

    /**
     * Treat an inspection-looking filter as a value, rather than a report bypass.
     *
     * @return void
     */
    public function testLauncherDoesNotTreatFilterValueAsInspection()
    {
        $result = $this->launch(array('--filter', '--help'));
        $this->assertSame(2, $result['status']);
        $this->assertStringContainsString('empty', $result['stderr']);
    }

    /**
     * Reject a bootstrap that exits before any fresh JUnit report is produced.
     *
     * @return void
     */
    public function testLauncherRejectsEarlySuccessfulBootstrapExit()
    {
        $bootstrap = tempnam(sys_get_temp_dir(), 'low-bootstrap-');
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        file_put_contents($bootstrap, '<?php exit(0);');
        file_put_contents($report, 'stale-report');
        try {
            $result = $this->launch(array('--bootstrap', $bootstrap, '--log-junit', $report));
            $this->assertSame(2, $result['status']);
            $this->assertStringContainsString('No fresh JUnit', $result['stderr']);
            $this->assertSame('stale-report', file_get_contents($report));
        } finally {
            unlink($bootstrap);
            unlink($report);
        }
    }

    /**
     * Reject suppressed and compile-time diagnostics before any case is selected.
     *
     * @dataProvider startupDiagnostics
     * @param string $control Startup position or compilation context.
     * @return void
     */
    public function testLauncherRejectsStartupDiagnostics($control)
    {
        $bootstrap = tempnam(sys_get_temp_dir(), 'low-startup-');
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        file_put_contents($bootstrap, $this->startupBootstrapCode($control));
        file_put_contents($report, 'stale-report');
        try {
            $result = $this->launch(array('--bootstrap', $bootstrap,
                '--filter', 'testProcessCapturesFullStderrBeforeStdout', '--log-junit', $report));
            $this->assertSame(2, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertStringContainsString($control === 'startup-compile' ? 'continue' : 'startup-diagnostic-sentinel',
                $result['stdout'] . $result['stderr']);
            $this->assertSame('stale-report', file_get_contents($report));
        } finally {
            unlink($bootstrap);
            unlink($report);
        }
    }

    /**
     * Cover diagnostics before/after the ordinary bootstrap and during compilation.
     *
     * @return array
     */
    public function startupDiagnostics()
    {
        return array('before bootstrap' => array('startup-before'),
            'after bootstrap' => array('startup-after'), 'bootstrap compilation' => array('startup-compile'));
    }

    /**
     * Protect the first loaded support statement without relying on a launcher.
     *
     * @dataProvider supportEntryPoints
     * @param string $entry Actual harness entry file copied to owned fixtures.
     * @return void
     */
    public function testDirectEntryRejectsEarlySupportDiagnostics($entry)
    {
        $directory = sys_get_temp_dir() . '/low-entry-guard-' . bin2hex(random_bytes(8));
        mkdir($directory . '/Support', 0700, true);
        copy(LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php', $directory . '/Support/diagnostics.php');
        $marker = '<?php @trigger_error("early-support-diagnostic", E_USER_DEPRECATED);';
        if ($entry === 'bootstrap') {
            copy(LOW_SEG2CAT_ROOT . '/tests/bootstrap.php', $directory . '/entry.php');
            file_put_contents($directory . '/Support/native.php', $marker);
        } else {
            copy(LOW_SEG2CAT_ROOT . '/tests/Support/native.php', $directory . '/Support/entry.php');
            file_put_contents($directory . '/Support/Environment.php', $marker);
        }
        try {
            $file = $directory . ($entry === 'bootstrap' ? '/entry.php' : '/Support/entry.php');
            $result = Process::execute(array(PHP_BINARY, '-d', 'display_errors=stderr', $file));
            $this->assertSame(255, $result['status']);
            $this->assertStringContainsString('early-support-diagnostic', $result['stdout'] . $result['stderr']);
            $this->assertStringContainsString('ErrorException', $result['stdout'] . $result['stderr']);
        } finally {
            foreach (glob($directory . '/Support/*') as $file) {
                unlink($file);
            }
            if (is_file($directory . '/entry.php')) {
                unlink($directory . '/entry.php');
            }
            rmdir($directory . '/Support');
            rmdir($directory);
        }
    }

    /**
     * Check both independently callable test-support entry files.
     *
     * @return array
     */
    public function supportEntryPoints()
    {
        return array('ordinary bootstrap' => array('bootstrap'), 'native bootstrap' => array('native'));
    }

    /**
     * Publish no source measurement when an inspected or premature run has no cases.
     *
     * @dataProvider nonExecutingCoverageRuns
     * @param string $control Non-executing runner shape.
     * @param int|null $status Exact status, or any nonzero startup rejection.
     * @return void
     */
    public function testCoveragePublishesOnlyAfterFreshExecutedCases($control, $status)
    {
        $directory = sys_get_temp_dir() . '/low-coverage-guard-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $raw = $directory . '/raw.json';
        $report = $directory . '/caller.xml';
        file_put_contents($report, 'stale-report');
        try {
            $arguments = array('--filter', 'definitely_no_case_matches');
            if ($control === 'filter-help') {
                $arguments = array('--filter', '--help');
            }
            if ($control === 'help') {
                $arguments = array('--help');
            }
            if ($control === 'minimum-version') {
                $arguments = array('--atleast-version=0');
            }
            if ($control === 'bootstrap' || $control === 'source-bootstrap') {
                $code = '<?php ';
                if ($control === 'source-bootstrap') {
                    $code .= 'require ' . var_export(LOW_SEG2CAT_ROOT . '/tests/Support/native.php', true) . '; ' .
                        'require ' . var_export(LOW_SEG2CAT_ROOT . '/low_seg2cat/addon.setup.php', true) . '; ';
                }
                file_put_contents($directory . '/bootstrap.php', $code . 'exit(0);');
                $arguments = array('--bootstrap', $directory . '/bootstrap.php');
            }
            if (strpos($control, 'startup-') === 0) {
                file_put_contents($directory . '/bootstrap.php', $this->startupBootstrapCode($control));
                $arguments = array('--bootstrap', $directory . '/bootstrap.php',
                    '--filter', 'testProcessCapturesFullStderrBeforeStdout');
            }
            $extension = '/Applications/Herd.app/Contents/Resources/xdebug/xdebug-' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '-arm64.so';
            $command = array_merge(array(PHP_BINARY, '-d', 'zend_extension=' . $extension,
                '-d', 'xdebug.mode=coverage', '-d', 'error_reporting=-1',
                LOW_SEG2CAT_ROOT . '/tests/scripts/coverage.php', '-c', LOW_SEG2CAT_ROOT . '/phpunit.xml',
                '--no-coverage', '--log-junit', $report), $arguments);
            $result = Process::execute($command, array_merge(getenv(), array('XDEBUG_MODE' => 'coverage',
                'LOW_SEG2CAT_COVERAGE' => $raw, 'LOW_SEG2CAT_XDEBUG_EXTENSION' => $extension)));
            if ($status === null) {
                $this->assertNotSame(0, $result['status'], $result['stdout'] . $result['stderr']);
                $this->assertStringContainsString('ErrorException', $result['stdout'] . $result['stderr']);
            } else {
                $this->assertSame($status, $result['status'], $result['stdout'] . $result['stderr']);
            }
            $this->assertFileDoesNotExist($raw);
            $this->assertFileDoesNotExist($raw . '.metadata.json');
            $this->assertSame('stale-report', file_get_contents($report));
            if ($control === 'minimum-version') {
                $this->assertSame('', $result['stderr']);
            } else {
                $this->assertNotSame('', $result['stdout'] . $result['stderr']);
            }
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,
                \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($directory);
        }
    }

    /**
     * Include a source-loading bootstrap so rejected runs cannot leave usable raw data.
     *
     * @return array
     */
    public function nonExecutingCoverageRuns()
    {
        return array('empty' => array('empty', 2), 'filter value' => array('filter-help', 2),
            'early bootstrap' => array('bootstrap', 2), 'source bootstrap' => array('source-bootstrap', 2),
            'inspection' => array('help', 0), 'zero minimum version' => array('minimum-version', 0),
            'startup before bootstrap' => array('startup-before', null),
            'startup after bootstrap' => array('startup-after', null),
            'startup compilation' => array('startup-compile', null));
    }

    /**
     * Keep both supported caller JUnit argument forms and the real exit status.
     *
     * @dataProvider junitArgumentForms
     * @param bool $equals
     * @return void
     */
    public function testLauncherPreservesCallerReport($equals)
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        try {
            $arguments = array('--filter', 'testProcessCapturesFullStderrBeforeStdout');
            $arguments = array_merge($arguments, $equals ? array('--log-junit=' . $report) : array('--log-junit', $report));
            $result = $this->launch($arguments);
            $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertSame('', $result['stderr']);
            $this->assertSame(1, low_seg2cat_junit_case_count($report));
        } finally {
            unlink($report);
        }
    }

    /**
     * Preserve positional selections after the parser's real end-of-options token.
     *
     * @return void
     */
    public function testLauncherPreservesEndOfOptionsBoundary()
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        try {
            $result = $this->launch(array('--log-junit', $report, '--',
                LOW_SEG2CAT_ROOT . '/tests/tests/Unit/FacadeFixtureTest.php'));
            $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertSame('', $result['stderr']);
            $this->assertGreaterThan(0, low_seg2cat_junit_case_count($report));
            $this->assertStringContainsString('FacadeFixtureTest', file_get_contents($report));
        } finally {
            unlink($report);
        }
    }

    /**
     * Recognize the valid string-zero version inspection without requesting cases.
     *
     * @return void
     */
    public function testLauncherAllowsZeroMinimumVersionInspection()
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        file_put_contents($report, 'stale-report');
        try {
            $result = $this->launch(array('--atleast-version=0', '--log-junit', $report));
            $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertSame('', $result['stderr']);
            $this->assertSame('stale-report', file_get_contents($report));
        } finally {
            unlink($report);
        }
    }

    /**
     * Preserve source failure and error statuses after validating nonempty cases.
     *
     * @dataProvider sourceRedSelections
     * @param string $filter
     * @param int $status
     * @param string $element
     * @return void
     */
    public function testLauncherPreservesSourceRedStatus($filter, $status, $element)
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        try {
            $result = $this->launch(array('--filter', $filter, '--log-junit', $report));
            $this->assertSame($status, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertSame(1, low_seg2cat_junit_case_count($report));
            $this->assertStringContainsString('<' . $element, file_get_contents($report));
        } finally {
            unlink($report);
        }
    }

    /**
     * Keep non-executing inspection commands usable without requiring test cases.
     *
     * @return void
     */
    public function testLauncherAllowsHelpAndListing()
    {
        foreach (array('--help', '--list-tests') as $option) {
            $result = $this->launch(array($option));
            $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertNotSame('', $result['stdout']);
            $this->assertSame('', $result['stderr']);
        }
    }

    /**
     * Reject absent, malformed and unusable reports without trusting old counts.
     *
     * @dataProvider invalidReports
     * @param string|null $xml
     * @return void
     */
    public function testJunitValidatorRejectsInvalidReports($xml)
    {
        $report = tempnam(sys_get_temp_dir(), 'low-junit-');
        try {
            if ($xml === null) {
                unlink($report);
            } else {
                file_put_contents($report, $xml);
            }
            $result = Process::execute(array(PHP_BINARY, LOW_SEG2CAT_ROOT . '/tests/.phpunit/check-junit.php', $report));
            $this->assertSame(2, $result['status']);
            $this->assertNotSame('', $result['stderr']);
        } finally {
            if (is_file($report)) {
                unlink($report);
            }
        }
    }

    /**
     * Supply independent unusable fresh report shapes.
     *
     * @return array
     */
    public function invalidReports()
    {
        return array('missing' => array(null), 'malformed' => array('<testsuites>'),
            'empty' => array('<testsuites><testsuite tests="0"/></testsuites>'),
            'wrong root' => array('<report><testcase/></report>'),
            'inconsistent' => array('<testsuites><testsuite tests="2"><testcase/></testsuite></testsuites>'),
            'document type' => array('<!DOCTYPE testsuites><testsuites/>'));
    }

    /**
     * Cover separated and equals-style JUnit options.
     *
     * @return array
     */
    public function junitArgumentForms()
    {
        return array('separated' => array(false), 'equals' => array(true));
    }

    /**
     * Distinguish PHPUnit's assertion-failure and native source-error statuses.
     *
     * @return array
     */
    public function sourceRedSelections()
    {
        return array('failure' => array('testStaticCacheDeletionReportsSuccess', 1, 'failure'),
            'error' => array('testStaticPluginInfoDoesNotReferenceThis', 2, 'error'));
    }

    /**
     * Prepare owned startup diagnostics without changing source or shared support.
     *
     * @param string $control Startup context.
     * @return string
     * @throws \InvalidArgumentException
     */
    private function startupBootstrapCode($control)
    {
        $bootstrap = 'require ' . var_export(LOW_SEG2CAT_ROOT . '/tests/bootstrap.php', true) . '; ';
        $diagnostic = '@trigger_error("startup-diagnostic-sentinel", E_USER_DEPRECATED); ';
        if ($control === 'startup-before') {
            return '<?php ' . $diagnostic . $bootstrap;
        }
        if ($control === 'startup-after') {
            return '<?php ' . $bootstrap . $diagnostic;
        }
        if ($control === 'startup-compile') {
            return '<?php switch (1) { case 1: continue; } ' . $bootstrap;
        }
        throw new \InvalidArgumentException('Unknown startup diagnostic control.');
    }

    /**
     * Invoke the checked launcher with the current explicit runtime.
     *
     * @param array $arguments
     * @return array
     */
    private function launch(array $arguments)
    {
        require_once LOW_SEG2CAT_ROOT . '/tests/.phpunit/check-junit.php';
        return Process::execute(array_merge(array(LOW_SEG2CAT_ROOT . '/tests/.phpunit/run-phpunit.sh',
            '-c', LOW_SEG2CAT_ROOT . '/phpunit.xml'), $arguments), array_merge(getenv(), array('PHP_BINARY' => PHP_BINARY)));
    }
}
