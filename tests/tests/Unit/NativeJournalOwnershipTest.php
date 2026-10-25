<?php

namespace Tests;

class NativeJournalOwnershipTest extends TestCase
{
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
        $expected = array(array(E_USER_DEPRECATED, '/\\Aowned bare diagnostic\\z/'));
        try {
            file_put_contents($directory . '/entry.php', '<?php ' . $code);
            foreach (array(array(PHP_BINARY, '-r', $code), array(PHP_BINARY, '-r' . $code),
                array(PHP_BINARY, '--run', $code), array(PHP_BINARY, '--run=' . $code),
                array(PHP_BINARY, '-B', $code, '-R', ';'), array(PHP_BINARY, '--process-begin=' . $code, '-R', ';'),
                array(PHP_BINARY, '-R', ';', '-E', $code), array(PHP_BINARY, '-R', ';', '--process-end=' . $code),
                array(PHP_BINARY, $directory . '/entry.php')) as $command) {
                $result = \Tests\Support\Process::execute($command, getenv(), 5.0, $expected);
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
            $expected = array();
            $result = \Tests\Support\Process::execute($command, getenv(), 5.0, $expected);
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
        $result = \Tests\Support\Process::probe($code, array('harness_only' => true,
            'expected_diagnostics' => array(array(E_USER_DEPRECATED, '/\Aowned shutdown diagnostic\z/'))));
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
        return array('ordinary' => array(false, false), 'deadline' => array(true, false), 'long-inline' => array(false, true));
    }

    /**
     * Reject an actual checked parent even when its native result is ignored.
     *
     * @dataProvider childLifecycles
     * @param bool $timeout Terminate the child after it records its diagnostic.
     * @param bool $inline Exercise the native long inline option.
     * @return void
     */
    public function testIgnoredChildDiagnosticCannotPass($timeout, $inline): void
    {
        $root = dirname(__DIR__, 3);
        $directory = sys_get_temp_dir() . '/low_seg2cat-owned-journal-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $code = 'try { @trigger_error("owned ignored child diagnostic", E_USER_DEPRECATED); } catch (\ErrorException $error) {}' . ($timeout ? ' usleep(2000000);' : '');
        $body = $inline
            ? '\Tests\Support\Process::execute(array(PHP_BINARY, "--run", ' . var_export($code, true) . '), getenv(), 0.4); self::assertTrue(true);'
            : '\Tests\Support\Process::probe(' . var_export($code, true) . ', array("harness_only" => true, "timeout_seconds" => 0.4)); self::assertTrue(true);';
        try {
            file_put_contents($directory . '/OwnedJournalTest.php', '<?php final class OwnedJournalTest extends \Tests\TestCase { public function testOwned(): void { ' . $body . ' } }');
            $arguments = array($root . '/tests/.phpunit/run-phpunit.sh', '--no-configuration', '--bootstrap', $root . '/tests/bootstrap.php', '--do-not-cache-result', '--log-junit', $directory . '/junit.xml', $directory . '/OwnedJournalTest.php');
            $result = \Tests\Support\Process::execute($arguments, array_merge(getenv(), array('PHP_BINARY' => PHP_BINARY)), 15.0, array(array(E_USER_DEPRECATED, '/\Aowned ignored child diagnostic\z/')));
            $status = $result['status']; $stdout = $result['stdout']; $stderr = $result['stderr'];
            self::assertNotSame(0, $status, $stdout);
            self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $stderr);
            self::assertStringContainsString('owned ignored child diagnostic', $stderr);
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
