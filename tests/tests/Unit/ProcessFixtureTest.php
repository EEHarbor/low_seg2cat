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
