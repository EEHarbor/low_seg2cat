<?php

namespace Tests;

use Tests\Support\Process;

class NativeUpdaterCallbackTest extends TestCase
{
    /**
     * Update changed versions through each module's optional extension callback.
     *
     * @return void
     */
    public function testUpdaterResolvesOptionalCallbacksAndForwardsInstalledVersions()
    {
        $code = <<<'CODE'
class Low_seg2cat_campaign_forwarding_ext
{
    /**
     * Record the version supplied by the module updater.
     *
     * @param string $version
     * @return bool
     */
    public function updateExtension($version)
    {
        $GLOBALS['forwarded_versions'][] = $version;
        return true;
    }
}
$GLOBALS['forwarded_versions'] = array();
$withExtension = new \Low\Seg2Cat\FluxCapacitor\Base\Upd();
$withExtension->module_name = 'Low_seg2cat_campaign_forwarding';
$unspecified = $withExtension->update();
$current = $withExtension->update($withExtension->version);
$beforeChange = $GLOBALS['forwarded_versions'];
$forwarded = $withExtension->update('4.0.0');
$withoutExtension = new \Low\Seg2Cat\FluxCapacitor\Base\Upd();
$withoutExtension->module_name = 'Low_seg2cat_campaign_missing';
$missingBefore = class_exists('Low_seg2cat_campaign_missing_ext', false);
$without = $withoutExtension->update('3.0.0');
$source = (new ReflectionMethod($withExtension, 'update'))->getFileName();
echo json_encode(array('forwarded' => $forwarded, 'without' => $without,
    'unspecified' => $unspecified, 'current' => $current, 'before_change' => $beforeChange,
    'versions' => $GLOBALS['forwarded_versions'], 'missing_before' => $missingBefore,
    'missing_after' => class_exists('Low_seg2cat_campaign_missing_ext', false),
    'source' => $source, 'source_sha256' => hash_file('sha256', $source),
    'binary' => PHP_BINARY, 'php' => PHP_VERSION, 'mask' => error_reporting(), 'e_all' => E_ALL), JSON_THROW_ON_ERROR);
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $data = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['unspecified']);
        $this->assertFalse($data['current']);
        $this->assertSame(array(), $data['before_change']);
        $this->assertTrue($data['forwarded']);
        $this->assertTrue($data['without']);
        $this->assertSame(array('4.0.0'), $data['versions']);
        $this->assertFalse($data['missing_before']);
        $this->assertFalse($data['missing_after']);
        $unit = 'low_seg2cat/low_seg2cat.phar::FluxCapacitor/Base/Upd.php';
        $inventory = json_decode(file_get_contents(LOW_SEG2CAT_ROOT . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('phar://' . LOW_SEG2CAT_ROOT . '/' . str_replace('::', '/', $unit), $data['source']);
        $this->assertSame($inventory[$unit]['sha256'], $data['source_sha256']);
        $this->assertSame(PHP_BINARY, $data['binary']);
        $this->assertSame(PHP_VERSION, $data['php']);
        $this->assertSame($data['e_all'], $data['mask'] & $data['e_all']);
    }
}
