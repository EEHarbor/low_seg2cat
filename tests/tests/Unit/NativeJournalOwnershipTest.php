<?php

namespace Tests;

class NativeJournalOwnershipTest extends TestCase
{
    /**
     * Retain a caught first-statement diagnostic through the worker's native exec.
     *
     * @return void
     */
    public function testWorkerPreludeCannotLoseCaughtDiagnostics(): void
    {
        $directory = sys_get_temp_dir() . '/owned-worker-prelude-' . bin2hex(random_bytes(8));
        mkdir($directory . '/tests/scripts', 0700, true);
        mkdir($directory . '/tests/Support', 0700);
        try {
            copy(LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php', $directory . '/tests/Support/diagnostics.php');
            $source = file_get_contents(LOW_SEG2CAT_ROOT . '/tests/scripts/process-worker.php');
            $source = substr_replace($source, '
try { @trigger_error("owned worker startup diagnostic", E_USER_DEPRECATED); } catch (\ErrorException $error) {}
', 5, 0);
            file_put_contents($directory . '/tests/scripts/process-worker.php', $source);
            $command = array(PHP_BINARY, $directory . '/tests/scripts/process-worker.php', $directory . '/group.pid', PHP_BINARY,
                '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1',
                '-d', 'auto_prepend_file=' . LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php', '-r', 'echo "healthy";');
            $result = \Tests\Support\Process::execute($command, getenv(), 4.0, array('work' => $directory));
            self::assertSame(0, $result['status']);
            self::assertSame('healthy', $result['stdout']);
            self::assertStringContainsString('owned worker startup diagnostic', $result['stderr']);
        } finally {
            $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) {
                if ($entry->isDir()) {
                    rmdir($entry->getPathname());
                    continue;
                }
                unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * Reap the checked wrapper's PHP descendant before joining its deadline.
     *
     * @return void
     */
    public function testDeadlineReapsCheckedWrapperDescendants(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/owned-wrapper-deadline-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $body = 'file_put_contents(' . var_export($directory . '/marker', true) . ', "started"); '
                . 'file_put_contents(' . var_export($directory . '/pid', true) . ', (string) getmypid()); usleep(1200000); '
                . 'file_put_contents(' . var_export($directory . '/marker', true) . ', "completed"); $this->assertTrue(true);';
            file_put_contents($directory . '/OwnedWrapperSleepTest.php', '<?php final class OwnedWrapperSleepTest extends \PHPUnit\Framework\TestCase { public function testSleeps(): void { ' . $body . ' } }');
            $command = array($root . '/tests/.phpunit/run-phpunit.sh', '--no-configuration',
                '--bootstrap', $root . '/tests/bootstrap.php', '--do-not-cache-result', '--log-junit', $directory . '/late.xml',
                $directory . '/OwnedWrapperSleepTest.php');
            $result = \Tests\Support\Process::execute($command, array_merge(getenv(), array('PHP_BINARY' => PHP_BINARY)), 0.6, array('work' => $directory));
            self::assertSame(124, $result['status']);
            self::assertTrue($result['timed_out']);
            self::assertSame('started', file_get_contents($directory . '/marker'));
            usleep(1300000);
            self::assertSame('started', file_get_contents($directory . '/marker'));
            self::assertSame('', is_file($directory . '/late.xml') ? file_get_contents($directory . '/late.xml') : '');
            self::assertFalse(posix_kill((int) file_get_contents($directory . '/pid'), 0));
        } finally {
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
                unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * Retain healthy guarded primary preludes through every native directive spelling.
     *
     * @return void
     */
    public function testDeclaredPrimaryGateRetainsHealthyNativePrepend(): void
    {
        $directory = sys_get_temp_dir() . '/owned-primary-gate-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $guard = LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php';
            file_put_contents($directory . '/gate.php', '<?php require ' . var_export($guard, true)
                . '; require ' . var_export($directory . '/prepend.php', true) . ';');
            file_put_contents($directory . '/prepend.php', '<?php echo "prelude|";');
            file_put_contents($directory . '/entry.php', '<?php define("PATH_THIRD", ' . var_export(LOW_SEG2CAT_ROOT . '/', true)
                . '); require ' . var_export(LOW_SEG2CAT_ROOT . '/low_seg2cat/autoload.php', true)
                . '; echo "body|" . (int) class_exists("Low\\Seg2Cat\\FluxCapacitor\\Conduit\\StaticCache") . "|" . (int) class_exists("LowSeg2CatDiagnostics", false);');
            $setting = 'auto_prepend_file=' . $directory . '/gate.php';
            foreach (array(array('-d', $setting), array('--define', $setting), array('-d' . $setting), array('--define=' . $setting)) as $options) {
                $result = \Tests\Support\Process::execute(array_merge(array(PHP_BINARY), $options, array($directory . '/entry.php')), null, 4.0, array('work' => $directory));
                self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
                self::assertSame('', $result['stderr']);
                self::assertSame('prelude|body|1|1', $result['stdout']);
            }
        } finally {
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
                unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * Guard inline and file entrypoints while retaining literal application arguments.
     *
     * @return void
     */
    public function testNativeEntrypointsAndArgumentsAreRetained(): void
    {
        $directory = sys_get_temp_dir() . '/owned-entry-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $code = 'try { @trigger_error("owned bare diagnostic", E_USER_DEPRECATED); } catch (\\ErrorException $error) {}';
        try {
            file_put_contents($directory . '/entry.php', '<?php ' . $code);
            foreach (array(array(PHP_BINARY, '-r', $code), array(PHP_BINARY, '-r' . $code),
                array(PHP_BINARY, '--run', $code), array(PHP_BINARY, '--run=' . $code),
                array(PHP_BINARY, '-B', $code, '-R', ';'), array(PHP_BINARY, '--process-begin=' . $code, '-R', ';'),
                array(PHP_BINARY, '-R', ';', '-E', $code), array(PHP_BINARY, '-R', ';', '--process-end=' . $code),
                array(PHP_BINARY, $directory . '/entry.php')) as $command) {
                $result = \Tests\Support\Process::execute($command, getenv(), 5.0, array('work' => $directory));
                self::assertSame(2, $result['status']);
                self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $result['stderr']);
            }
            $strict = 'declare(strict_types=1); echo "strict code";';
            foreach (array($strict, 'declare(ticks=1){} namespace OwnedInline; echo "strict code";', 'declare(ticks=1):enddeclare; namespace OwnedInline; echo "strict code";') as $inlineCode) {
                foreach (array('-r', '--run') as $option) {
                    $result = \Tests\Support\Process::execute(array(PHP_BINARY, $option, $inlineCode), getenv(), 5.0);
                    self::assertSame(0, $result['status'], $result['stderr']);
                    self::assertSame('strict code', $result['stdout']);
                }
            }
            file_put_contents($directory . '/arguments.php', '<?php echo json_encode(array_slice($argv, 1));');
            $command = array(PHP_BINARY, $directory . '/arguments.php', '-r', 'literal value');
            $result = \Tests\Support\Process::execute($command, getenv(), 5.0, array('work' => $directory));
            self::assertSame(0, $result['status'], $result['stderr']);
            self::assertSame(array('-r', 'literal value'), json_decode($result['stdout'], true));
        } finally {
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
                unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    /**
     * Preserve application stdout independently of the native diagnostic log.
     *
     * @return void
     */
    public function testWarningShapedLiteralOutputRemainsHealthy(): void
    {
        $text = "Warning: owned literal in /owned/literal.php on line 7\n<br /><b>Warning</b>: owned literal in <b>/owned/literal.php</b> on line <b>7</b><br />";
        $result = \Tests\Support\Process::probe('echo ' . var_export($text, true) . ';', array('harness_only' => true));
        self::assertSame(0, $result['status']);
        self::assertSame($text, $result['stdout']);
        self::assertSame('', $result['stderr']);
        self::assertSame('', $result['native_errors']);
    }

    /**
     * Accept owned probes beneath the native temporary root's physical alias.
     *
     * @return void
     */
    public function testProbeAcceptsTemporaryDirectoryAlias(): void
    {
        $directory = sys_get_temp_dir() . '/owned-temp-alias-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        mkdir($directory . '/target', 0700);
        symlink($directory . '/target', $directory . '/alias');
        try {
            $code = 'require ' . var_export(LOW_SEG2CAT_ROOT . '/tests/Support/native.php', true)
                . '; require ' . var_export(LOW_SEG2CAT_ROOT . '/tests/Support/Process.php', true)
                . '; $result = \Tests\Support\Process::probe("echo \"kept\";", array("harness_only" => true)); '
                . 'echo json_encode(array($result["status"], $result["stdout"], $result["stderr"]));';
            $environment = array_merge(getenv(), array('TMPDIR' => $directory . '/alias'));
            $result = \Tests\Support\Process::execute(array(PHP_BINARY, '-r', $code), $environment, 5.0);
            self::assertSame(0, $result['status'], $result['stderr']);
            self::assertSame(array(0, 'kept', ''), json_decode($result['stdout'], true));
            self::assertSame('', $result['native_errors']);
        } finally {
            unlink($directory . '/alias');
            rmdir($directory . '/target');
            rmdir($directory);
        }
    }

    /**
     * Reject a caught diagnostic in a shutdown callback registered after the guard.
     *
     * @return void
     */
    public function testLateShutdownDiagnosticCannotPass(): void
    {
        $code = 'register_shutdown_function(static function () { try { @trigger_error("owned shutdown diagnostic", E_USER_DEPRECATED); } catch (ErrorException $error) {} }); echo "completed";';
        $result = \Tests\Support\Process::probe($code, array('harness_only' => true));
        self::assertSame(2, $result['status']);
        self::assertSame('completed', $result['stdout']);
        self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $result['stderr']);
    }

    /**
     * Cover ordinary ignored results and diagnostic children terminated by a deadline.
     *
     * @return array
     */
    public static function childLifecycles(): array
    {
        return array('ordinary' => array(false, false, 'diagnostic'), 'deadline' => array(true, false, 'diagnostic'),
            'long-inline' => array(false, true, 'diagnostic'), 'nonzero' => array(false, false, 'nonzero'),
            'stderr' => array(false, false, 'stderr'), 'timeout before diagnostic' => array(true, false, 'timeout'), 'untrusted prepend' => array(false, false, 'prepend'),
            'native stdout plain' => array(false, false, 'native-plain'), 'native stdout HTML' => array(false, false, 'native-html'),
            'late native stdout' => array(false, false, 'native-late'));
    }

    /**
     * Reject an actual checked parent even when its native result is ignored.
     *
     * @dataProvider childLifecycles
     * @param bool $timeout Terminate the child after it records its diagnostic.
     * @param bool $inline Exercise the native long inline option.
     * @param string $fault Explicit authored native lifecycle fault.
     * @return void
     */
    public function testIgnoredChildDiagnosticCannotPass($timeout, $inline, $fault): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/low_seg2cat-owned-journal-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $code = 'try { @trigger_error("owned ignored child diagnostic", E_USER_DEPRECATED); } catch (\ErrorException $error) {}' . ($timeout ? ' usleep(2000000);' : '');
        if ($fault === 'nonzero') {
            $code = 'exit(23);';
        }
        if ($fault === 'stderr') {
            $code = 'fwrite(STDERR, "owned unexpected native stderr");';
        }
        if ($fault === 'timeout') {
            $code = 'usleep(2000000);';
        }
        if (strpos($fault, 'native-') === 0) {
            $warning = 'set_error_handler(static function () { return false; }); $display = ini_set("display_errors", "1"); '
                . '$html = ini_set("html_errors", ' . ($fault === 'native-html' ? '"1"' : '"0"') . '); '
                . 'trigger_error("owned native stdout diagnostic", E_USER_WARNING); restore_error_handler(); '
                . 'ini_set("display_errors", $display); ini_set("html_errors", $html);';
            $code = $fault === 'native-late' ? 'register_shutdown_function(static function () { ' . $warning . ' exit(0); });' : $warning;
        }
        if ($inline) {
            $code = 'require ' . var_export($root . '/low_seg2cat/autoload.php', true) . '; ' . $code;
        }
        $body = $inline
            ? '\Tests\Support\Process::execute(array(PHP_BINARY, "--run", ' . var_export($code, true) . '), getenv(), 0.4); self::assertTrue(true);'
            : '\Tests\Support\Process::execute(array(PHP_BINARY, ' . var_export($directory . '/native.php', true) . '), getenv(), 0.4); self::assertTrue(true);';
        if ($fault === 'prepend') {
            $body = 'try { \Tests\Support\Process::execute(array(PHP_BINARY, "-d", '
                . var_export('auto_prepend_file=' . $directory . '/empty.php', true) . ', '
                . var_export($directory . '/native.php', true) . '), getenv(), 0.4); } catch (\RuntimeException $error) {} self::assertTrue(true);';
        }
        try {
            if ($fault === 'prepend') {
                file_put_contents($directory . '/empty.php', '<?php ');
            }
            file_put_contents($directory . '/native.php', '<?php require ' . var_export($root . '/tests/Support/native.php', true)
                . '; require ' . var_export($root . '/low_seg2cat/autoload.php', true)
                . '; class_exists("Low\\Seg2Cat\\FluxCapacitor\\Conduit\\StaticCache"); ' . $code);
            file_put_contents($directory . '/OwnedJournalTest.php', '<?php final class OwnedJournalTest extends \Tests\TestCase { public function testOwned(): void { ' . $body . ' } }');
            $arguments = array($root . '/tests/.phpunit/run-phpunit.sh', '--no-configuration', '--bootstrap', $root . '/tests/bootstrap.php', '--do-not-cache-result', '--log-junit', $directory . '/junit.xml', $directory . '/OwnedJournalTest.php');
            $result = \Tests\Support\Process::execute($arguments, array_merge(getenv(), array('PHP_BINARY' => PHP_BINARY)), 15.0, array('work' => $directory));
            $status = $result['status']; $stdout = $result['stdout']; $stderr = $result['stderr'];
            self::assertNotSame(0, $status, $stdout);
            self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $stderr);
            self::assertStringContainsString($fault === 'diagnostic' ? 'owned ignored child diagnostic' : 'Rejected native lifecycle', $stderr);
            $report = simplexml_load_file($directory . '/junit.xml');
            self::assertNotFalse($report);
            self::assertCount(1, $report->xpath('//testcase'));
        } finally {
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
                unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
