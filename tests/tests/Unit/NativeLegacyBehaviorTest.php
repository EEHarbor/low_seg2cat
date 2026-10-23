<?php

namespace Tests;

use Tests\Support\Process;

class NativeLegacyBehaviorTest extends TestCase
{
    /**
     * Exercise settings independently of the defective legacy URL constructor.
     *
     * @dataProvider legacyVersions
     * @param string $version
     * @return void
     */
    public function testLegacySettingsUseHistoricalRowsAndIsolatedDatabaseClones($version)
    {
        $code = <<<'CODE'
$app = ee();
$app->db->tables = array('structure_settings');
$app->db->rowsByTable['structure_settings'] = array(array('var' => 'custom', 'var_value' => 'database'));
$app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize(array('custom' => 'fieldtype', 'nullable' => null)))));
$app->db->data_cache['table_names'] = array('original');
$app->db->where('legacy_query', 'keep');
$calls = $app->db->calls;
$bridge = new \Low\Seg2Cat\FluxCapacitor\Bridge\EE2(array('module' => 'structure', 'module_name' => 'Structure'));
$settings = $bridge->getSettings(true);
$bridge->setConfig('custom', 'updated');
$updated = $bridge->getConfig('custom');
\Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
$app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize(false))));
$app->db->rowsByTable['structure_settings'] = array();
$empty = $bridge->getSettings(true);
$app->config->items['cache_path'] = '/custom/cache/';
$_SERVER['QUERY_STRING'] = '/cp/addons/settings/';
$_GET = array('module' => 'structure', 'method' => 'license');
$info = $bridge->getCurrentUrlInfo();
unset($_GET['method']);
echo json_encode(array('settings' => $settings, 'updated' => $updated, 'empty' => $empty,
    'unchanged_calls' => $calls === $app->db->calls, 'cache' => $app->db->data_cache,
    'path' => $bridge->getCachePath(), 'info' => $info, 'method' => $bridge->getCurrentUrlInfo('method'),
    'fallback' => $bridge->getCurrentUrlInfo('unknown')));
CODE;
        $result = Process::probe($code, array('version' => $version));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame('database', $data['settings']['custom']);
        $this->assertNull($data['settings']['nullable']);
        $this->assertSame('Low Seg2Cat', $data['settings']['name']);
        $this->assertSame('updated', $data['updated']);
        $this->assertArrayNotHasKey('custom', $data['empty']);
        $this->assertTrue($data['unchanged_calls']);
        $this->assertSame(array('table_names' => array('original')), $data['cache']);
        $this->assertSame('/custom/cache/', $data['path']);
        $this->assertTrue($data['info']['cp']);
        $this->assertSame('license', $data['info']['method']);
        $this->assertSame('index', $data['method']);
        $this->assertSame('structure', $data['fallback']['module']);
    }

    /**
     * Route EE2.8+ persistent cache calls while preserving session-only values.
     *
     * @return void
     */
    public function testLegacyPersistentCacheAndSessionCacheRemainSeparate()
    {
        $code = <<<'CODE'
$app = ee();
$app->session = new \Tests\Support\LegacySession();
$calls = array();
$app->cache = new \Tests\Support\CallbackService(array('get' => function ($key) use (&$calls) {
    $calls[] = array('get', $key); return array('cached' => $key);
}, 'save' => function ($key, $data) use (&$calls) {
    $calls[] = array('save', $key, $data); return false;
}));
$bridge = new \Low\Seg2Cat\FluxCapacitor\FluxCapacitor();
$persistent = $bridge->cache('get', 'record');
$saved = $bridge->cache('set', 'record', array('id' => 1));
$session = $bridge->cache('set', 'zero', 0, false);
$read = $bridge->cache('get', 'zero', false, false);
$bridge->cache('delete', 'zero', false, false);
$missing = $bridge->cache('get', 'zero', false, false);
$bridge->cache('set', 'other', 'value', false);
$bridge->cache('clear', false, false, false);
echo json_encode(array('persistent' => $persistent, 'saved' => $saved, 'session' => $session,
    'read' => $read, 'missing' => $missing, 'calls' => $calls, 'cache' => $app->session->cache));
CODE;
        $result = Process::probe($code, array('version' => '2.11.0'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame(array('cached' => '/low_seg2cat/record'), $data['persistent']);
        $this->assertFalse($data['saved']);
        $this->assertSame(0, $data['session']);
        $this->assertSame(0, $data['read']);
        $this->assertFalse($data['missing']);
        $this->assertSame(array(array('get', '/low_seg2cat/record'), array('save', '/low_seg2cat/record', array('id' => 1))), $data['calls']);
        $this->assertSame(array(), $data['cache']);
    }

    /**
     * Prefer the configured third-party themes constant across legacy and modern bridges.
     *
     * @dataProvider themeBridges
     * @param string $bridge
     * @return void
     */
    public function testThemesConstantTakesPrecedenceOverConfig($bridge)
    {
        $code = 'define("URL_THIRD_THEMES", "/shared/themes/"); ' .
            '$class = "Low\\\\Seg2Cat\\\\FluxCapacitor\\\\Bridge\\\\' . $bridge . '"; ' .
            '$bridge = new $class(array("module" => "low_seg2cat", "module_name" => "Low Seg2Cat")); ' .
            'echo $bridge->getAddonThemesDir();';
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('/shared/themes/low_seg2cat/', $result['stdout']);
    }

    /**
     * Supply both sides of the EE2 persistent-cache version boundary.
     *
     * @return array
     */
    public static function legacyVersions()
    {
        return array(array('2.5.5'), array('2.11.0'));
    }

    /**
     * Supply each separately shipped implementation of the theme helper.
     *
     * @return array
     */
    public static function themeBridges()
    {
        return array(array('EE2'), array('EE3'), array('EE4'), array('EE5'));
    }
}
