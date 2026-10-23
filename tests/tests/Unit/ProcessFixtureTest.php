<?php

namespace Tests;

use Tests\Support\Process;

class ProcessFixtureTest extends TestCase
{
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
