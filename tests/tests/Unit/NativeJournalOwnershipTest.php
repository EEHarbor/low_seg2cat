<?php

namespace Tests;

class NativeJournalOwnershipTest extends TestCase
{
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
            foreach (array('-r', '--run') as $option) {
                $result = \Tests\Support\Process::execute(array(PHP_BINARY, $option, $strict), getenv(), 5.0);
                self::assertSame(0, $result['status'], $result['stderr']);
                self::assertSame('strict code', $result['stdout']);
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
            'stderr' => array(false, false, 'stderr'), 'timeout before diagnostic' => array(true, false, 'timeout'), 'untrusted prepend' => array(false, false, 'prepend'));
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
