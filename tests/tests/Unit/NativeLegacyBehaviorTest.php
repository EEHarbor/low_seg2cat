<?php

namespace Tests;

use Tests\Support\Process;

class NativeLegacyBehaviorTest extends TestCase
{
    /**
     * Delegate slash reduction on both sides of the EE2.6 helper boundary.
     *
     * @dataProvider legacyVersions
     * @param string $version
     * @return void
     */
    public function testLegacySlashReductionUsesItsVersionedHelper($version)
    {
        $code = <<<'CODE'
$app = ee();
$calls = array();
$app->functions = new \Tests\Support\CallbackService(array('remove_double_slashes' => function ($value) use (&$calls) {
    $calls[] = $value;
    return 'legacy:' . $value;
}));
$bridge = new \Low\Seg2Cat\FluxCapacitor\Bridge\EE2(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
$values = array();
foreach (array('https://example.test//path', 'relative///path', '//cdn.example.test//path', '') as $input) {
    $values[] = $bridge->reduce_double_slashes($input);
}
echo json_encode(array('values' => $values, 'calls' => $calls, 'loaded' => $app->loaded));
CODE;
        $result = Process::probe($code, array('version' => $version));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $data = json_decode($result['stdout'], true);
        if ($version === '2.5.5') {
            $this->assertSame(array('legacy:https://example.test//path', 'legacy:relative///path',
                'legacy://cdn.example.test//path', 'legacy:'), $data['values']);
            $this->assertSame(array('https://example.test//path', 'relative///path', '//cdn.example.test//path', ''), $data['calls']);
            $this->assertSame(array(), $data['loaded']);
            return;
        }
        $this->assertSame(array('https://example.test/path', 'relative/path', '//cdn.example.test/path', ''), $data['values']);
        $this->assertSame(array(), $data['calls']);
        $this->assertSame(array('string', 'string', 'string', 'string'), $data['loaded']);
    }

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
     * Follow configured and fallback cache paths within one original EE2 instance.
     *
     * @return void
     */
    public function testLegacyCachePathTracksConfigurationChanges()
    {
        $code = <<<'CODE'
$app = ee();
$bridge = new \Low\Seg2Cat\FluxCapacitor\Bridge\EE2(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
unset($app->config->items['cache_path']);
$paths = array($bridge->getCachePath());
$app->config->items['cache_path'] = '/custom/cache/';
$paths[] = $bridge->getCachePath();
$app->config->items['cache_path'] = '';
$paths[] = $bridge->getCachePath();
echo json_encode(array('paths' => $paths, 'fallback' => APPPATH . 'cache/',
    'source' => (new ReflectionMethod($bridge, 'getCachePath'))->getFileName(),
    'runtime' => array(PHP_VERSION, PHP_BINARY, error_reporting(), E_ALL)), JSON_THROW_ON_ERROR);
CODE;
        $result = Process::probe($code, array('version' => '2.5.5'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $value = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array($value['fallback'], '/custom/cache/', $value['fallback']), $value['paths']);
        $this->assertSame('phar://' . LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar/FluxCapacitor/Bridge/EE2.php', $value['source']);
        $this->assertSame(array(PHP_VERSION, PHP_BINARY, E_ALL, E_ALL), $value['runtime']);
    }

    /**
     * Replace a populated legacy menu with the supported default empty menu.
     *
     * @return void
     */
    public function testLegacyNavigationCanBeClearedAfterPopulation()
    {
        $code = <<<'CODE'
$app = ee();
$app->cp = new \Tests\Support\LegacyControlPanel();
$bridge = new \Low\Seg2Cat\FluxCapacitor\Bridge\EE2(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
$bridge->getNav(array('index' => 'Home', 'https://example.test/' => 'External'));
$bridge->getNav();
echo json_encode(array('calls' => $app->cp->calls,
    'expected_internal' => $bridge->getBaseURL('index'),
    'source' => (new ReflectionMethod($bridge, 'getNav'))->getFileName(),
    'runtime' => array(PHP_VERSION, PHP_BINARY, error_reporting(), E_ALL)), JSON_THROW_ON_ERROR);
CODE;
        $result = Process::probe($code, array('version' => '2.5.5'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $value = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array(array('set_right_nav', array(array('Home' => $value['expected_internal'],
            'External' => 'https://example.test/'))), array('set_right_nav', array(array()))), $value['calls']);
        $this->assertStringContainsString('method=index', $value['expected_internal']);
        $this->assertSame('phar://' . LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar/FluxCapacitor/Bridge/EE2.php', $value['source']);
        $this->assertSame(array(PHP_VERSION, PHP_BINARY, E_ALL, E_ALL), $value['runtime']);
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
