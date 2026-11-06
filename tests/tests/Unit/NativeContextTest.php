<?php

namespace Tests;

use Tests\Support\Process;

class NativeContextTest extends TestCase
{
    /**
     * Load with permission validation when this runtime provides OPcache.
     *
     * @return void
     */
    public function testAutoloaderSupportsOpcachePermissionValidation()
    {
        $code = <<<'CODE'
class_exists('Low\\Seg2Cat\\FluxCapacitor\\Base\\Ext');
$reflection = new \ReflectionClass('Low\\Seg2Cat\\FluxCapacitor\\Base\\Ext');
$available = function_exists('opcache_get_configuration');
$config = $available ? opcache_get_configuration() : array();
echo json_encode(array('file' => $reflection->getFileName(), 'available' => $available,
    'permission' => $available ? $config['directives']['opcache.validate_permission'] : false));
CODE;
        $result = Process::probe($code, array('opcache_permission' => true));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame(function_exists('opcache_get_configuration'), $data['available']);
        $this->assertSame($data['available'], $data['permission']);
        $this->assertStringEndsWith('/FluxCapacitor/Base/Ext.php', $data['file']);
    }

    /**
     * Reject direct access before extension setup or archive loading occurs.
     *
     * @return void
     */
    public function testExtensionDirectAccessTerminatesWithItsGuardMessage()
    {
        $result = Process::probe('include LOW_SEG2CAT_ROOT . "/low_seg2cat/ext.low_seg2cat.php"; echo "unexpected";',
            array('direct_access' => true));
        $this->assertSame(0, $result['status'], $result['stderr']);
        $this->assertSame('No direct script access allowed', $result['stdout']);
    }

    /**
     * Report the missing archive location when an installation is incomplete.
     *
     * @return void
     */
    public function testAutoloaderReportsAMissingArchive()
    {
        $code = <<<'CODE'
try {
    class_exists('Low\\Seg2Cat\\FluxCapacitor\\Base\\Ext');
} catch (\Exception $exception) {
    echo $exception->getMessage();
}
CODE;
        $result = Process::probe($code, array('missing_phar' => true));
        $this->assertSame(0, $result['status'], $result['stderr']);
        $this->assertStringStartsWith('Add-on PHAR file at location ', $result['stdout']);
        $this->assertStringContainsString('/missing-third/low_seg2cat/low_seg2cat.phar not found.', $result['stdout']);
    }

}
