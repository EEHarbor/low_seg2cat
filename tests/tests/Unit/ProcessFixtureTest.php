<?php

namespace Tests;

use Tests\Support\Process;

class ProcessFixtureTest extends TestCase
{
    /**
     * Reject suppressed deprecations originating in generated native probe code.
     *
     * @return void
     */
    public function testNativeProbeDetectsSuppressedHarnessDeprecations()
    {
        $result = Process::probe('@trigger_error("native-harness sentinel", E_USER_DEPRECATED); echo "continued";',
            array('harness_only' => true));
        $this->assertSame(255, $result['status']);
        $this->assertStringNotContainsString('continued', $result['stdout']);
        $this->assertStringContainsString('native-harness sentinel', $result['stdout'] . $result['stderr']);
    }

    /**
     * Install diagnostics before PHP compiles the owned generated probe.
     *
     * @return void
     */
    public function testNativeProbeRejectsCompilationDiagnostics()
    {
        $result = Process::probe('switch (1) { case 1: continue; } echo "continued";',
            array('harness_only' => true));
        $this->assertSame(255, $result['status']);
        $this->assertStringNotContainsString('continued', $result['stdout']);
        $this->assertStringContainsString('continue', $result['stdout'] . $result['stderr']);
        $this->assertStringContainsString('ErrorException', $result['stdout'] . $result['stderr']);
    }

    /**
     * Keep nested generated diagnostics strict after support installs its handler.
     *
     * @return void
     */
    public function testNativeProbeRejectsSuppressedNestedDiagnostics()
    {
        $code = <<<'CODE'
$file = sys_get_temp_dir() . '/nested.php';
file_put_contents($file, '<?php @trigger_error("nested-generated-sentinel", E_USER_DEPRECATED); echo "continued";');
require $file;
CODE;
        $result = Process::probe($code, array('harness_only' => true));
        $this->assertSame(255, $result['status']);
        $this->assertStringNotContainsString('continued', $result['stdout']);
        $this->assertStringContainsString('nested-generated-sentinel', $result['stdout'] . $result['stderr']);
        $this->assertStringContainsString('ErrorException', $result['stdout'] . $result['stderr']);
    }

    /**
     * Preserve a nonzero ordinary exit after polling and reaping the child.
     *
     * @return void
     */
    public function testProcessPreservesNonzeroExitStatus()
    {
        $result = Process::execute(array(PHP_BINARY, '-r', 'echo "prefix"; fwrite(STDERR, "error"); exit(7);'));
        $this->assertSame(7, $result['status']);
        $this->assertFalse($result['timed_out']);
        $this->assertSame('prefix', $result['stdout']);
        $this->assertSame('error', $result['stderr']);
    }

    /**
     * Terminate and reap an owned stalled child while preserving its attribution.
     *
     * @dataProvider stalledChildren
     * @param bool $ignoreTermination
     * @return void
     */
    public function testNativeProbeDeadlineRestoresOwnedTemporaryDirectory($ignoreTermination)
    {
        $code = $ignoreTermination ? 'pcntl_async_signals(true); pcntl_signal(SIGTERM, SIG_IGN); ' : '';
        $code .= 'echo json_encode(array("pid" => getmypid(), "temporary" => sys_get_temp_dir())); ' .
            'fwrite(STDERR, "stall-prefix"); usleep(5000000);';
        $started = hrtime(true);
        $result = Process::probe($code, array('harness_only' => true, 'timeout_seconds' => 0.4));
        $elapsed = (hrtime(true) - $started) / 1000000000;
        $child = json_decode($result['stdout'], true);
        $this->assertSame(124, $result['status']);
        $this->assertTrue($result['timed_out']);
        $this->assertSame('stall-prefix', $result['stderr']);
        $this->assertIsArray($child);
        $this->assertLessThan(3.0, $elapsed);
        $this->assertDirectoryDoesNotExist($child['temporary']);
        $this->assertFalse(posix_kill($child['pid'], 0));
    }

    /**
     * Exercise graceful termination and the direct-child SIGKILL fallback.
     *
     * @return array
     */
    public function stalledChildren()
    {
        return array('TERM' => array(false), 'KILL' => array(true));
    }

    /**
     * Clean restricted owned directories after normal exit or deadline termination.
     *
     * @dataProvider restrictedChildren
     * @param bool $stall Wait for the owned child deadline.
     * @return void
     */
    public function testRestrictedProbeCleanupPreservesExternalSymlinkTargets($stall)
    {
        $external = tempnam(sys_get_temp_dir(), 'low-seg2cat-cleanup-');
        file_put_contents($external, 'external sentinel');
        $code = '$nested = sys_get_temp_dir() . "/restricted/nested"; mkdir($nested, 0700, true); ' .
            'file_put_contents($nested . "/owned", "owned sentinel"); symlink(' . var_export($external, true) . ', $nested . "/outside"); ' .
            'chmod($nested, 0000); chmod(dirname($nested), 0000); chmod(sys_get_temp_dir(), 0500); ' .
            'echo json_encode(array("pid" => getmypid(), "temporary" => sys_get_temp_dir())); fwrite(STDERR, "restricted-prefix");';
        if ($stall) {
            $code .= 'usleep(5000000);';
        }
        try {
            $result = Process::probe($code, array('harness_only' => true, 'timeout_seconds' => 0.4));
            $child = json_decode($result['stdout'], true);
            $this->assertSame($stall ? 124 : 0, $result['status']);
            $this->assertSame($stall, $result['timed_out']);
            $this->assertSame('restricted-prefix', $result['stderr']);
            $this->assertIsArray($child);
            $this->assertDirectoryDoesNotExist($child['temporary']);
            $this->assertFalse(posix_kill($child['pid'], 0));
            $this->assertSame('external sentinel', file_get_contents($external));
        } finally {
            unlink($external);
        }
    }

    /**
     * Cover cleanup after ordinary completion and timeout interruption.
     *
     * @return array
     */
    public function restrictedChildren()
    {
        return array('normal exit' => array(false), 'deadline' => array(true));
    }

    /**
     * Capture large stderr output without blocking behind an unread stdout pipe.
     *
     * @return void
     */
    public function testProcessCapturesFullStderrBeforeStdout()
    {
        $result = Process::execute(array(PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("E", 200000)); echo "ok";'));
        $this->assertSame(0, $result['status']);
        $this->assertSame('ok', $result['stdout']);
        $this->assertSame(200000, strlen($result['stderr']));
    }
}
