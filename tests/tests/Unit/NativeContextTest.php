<?php

namespace Tests;

use Tests\Support\Process;

class NativeContextTest extends TestCase
{
    /**
     * Reject direct access before extension setup or archive loading occurs.
     *
     * @return void
     */
    public function testExtensionDirectAccessTerminatesWithItsGuardMessage()
    {
        $result = Process::execute(array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1',
            '-d', 'display_startup_errors=1', LOW_SEG2CAT_ROOT . '/low_seg2cat/ext.low_seg2cat.php'), getenv());
        $this->assertSame(0, $result['status'], $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $this->assertSame('No direct script access allowed', $result['stdout']);
    }

}
