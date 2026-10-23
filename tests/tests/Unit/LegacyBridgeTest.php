<?php

namespace Tests;

use Tests\Support\Process;

class LegacyBridgeTest extends TestCase
{
    /**
     * Exercise legacy routes, sessions, metadata, and helpers in real EE2 contexts.
     *
     * @dataProvider legacyVersions
     * @param string $version
     * @return void
     */
    public function testLegacyBridgePreservesItsRuntimeContracts($version)
    {
        $code = <<<'CODE'
$app = ee();
$app->session = new \Tests\Support\LegacySession();
$app->cp = new \Tests\Support\LegacyControlPanel();
$app->api = new \Tests\Support\Recorder();
$app->security = new \Tests\Support\CallbackService(array('xss_clean' => function ($input) { return array('clean' => $input); }));
$app->pagination = new \Tests\Support\CallbackService(array('initialize' => function ($options) {}, 'create_links' => function () { return 'links'; }));
$app->input = new \Tests\Support\CallbackService(array('get' => function ($key) { return $key === 'per_page' ? 20 : false; }));
$app->config->items['theme_folder_url'] = '/themes/';
$bridge = new \Low\Seg2Cat\FluxCapacitor\FluxCapacitor();
$out = array();
$out['base'] = $bridge->getBaseURL('settings');
$out['slash'] = $bridge->getBaseURL('/');
$app->config->items['cp_session_type'] = 's';
$app->config->items['admin_session_type'] = 's';
$out['session'] = $bridge->getBaseURL();
$app->config->items['cp_session_type'] = 'cs';
$app->config->items['admin_session_type'] = 'cs';
$out['fingerprint'] = $bridge->getBaseURL();
$bridge->getNav(array('index' => 'Home', 'https://example.test/' => 'External'));
$out['listing'] = $bridge->cpURL('listing', 'ignored', array('filter_by_channel' => 4));
$out['publish'] = $bridge->cpURL('publish', 'create', array('channel_id' => 4));
$out['groups'] = $bridge->cpURL('members', 'groups');
$out['channels'] = $bridge->cpURL('channels', 'create');
$out['addons'] = $bridge->cpURL('addons');
$out['default'] = $bridge->cpURL('custom', 'mode');
$out['module'] = $bridge->moduleURL('license', null);
$out['view'] = $bridge->view('settings', array(), true);
$out['page'] = $bridge->getCurrentPage(array('per_page' => 10));
$app->input = new \Tests\Support\CallbackService(array('get' => function ($key) { return $key === 'page' ? '3' : false; }));
$out['page_query'] = $bridge->getCurrentPage(array('per_page' => 10));
$app->input = new \Tests\Support\CallbackService(array('get' => function () { return false; }));
$out['page_default'] = $bridge->getCurrentPage(array('per_page' => 10));
$out['start'] = $bridge->getStartNum(array('per_page' => 10, 'current_page' => 3));
$out['pagination'] = $bridge->pagination(array('current_page' => 2, 'per_page' => 10));
$bridge->instantiate('channel_entries');
$out['xss'] = $bridge->xss_clean('<script>');
$out['slug'] = $bridge->url_title(' NEWS ITEM ');
$out['variables'] = $bridge->assign_variables('{title}');
$out['parameters'] = $bridge->assign_parameters('limit="2"', array('limit' => 1));
$out['cache_missing'] = $bridge->cache('get', 'missing', false, false);
$out['cache_set'] = $bridge->cache('set', 'key', array('id' => 1), false);
$out['cache_get'] = $bridge->cache('get', 'key', false, false);
$bridge->cache('delete', 'key');
$out['cache_deleted'] = $bridge->cache('get', 'key', false, false);
$bridge->cache('clear');
$out['cache_unknown'] = $bridge->cache('unknown');
$bridge->flashData('message_success', 'Saved', 'Body');
$out['themes'] = $bridge->getAddonThemesDir();
$out['cache_path'] = $bridge->getCachePath();
$bridge->overwriteEEClass('typography', 'replacement');
$out['overwritten'] = $app->typography;
$bridge->removeEEClass('typography');
$out['removed'] = $app->typography;
$bridge->javascript_to_page('example();');
$out['footer'] = $bridge->getFooter();
$out['name'] = $bridge->getSettings(true)['name'];
$out['version'] = $bridge->getSettings()->version;
$bridge->setConfig('custom', 'value');
$out['custom'] = $bridge->getConfig('custom');
$app->db->rowsByTable['modules'] = array(array('module_name' => 'Channel'));
$out['installed'] = $bridge->module_installed('Channel');
$app->db->rowsByTable['modules'] = array();
$out['not_installed'] = $bridge->module_installed('Missing');
echo json_encode($out);
CODE;
        $result = Process::probe($code, array('version' => $version));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertStringContainsString('method=settings', $data['base']);
        $this->assertStringNotContainsString('method=', $data['slash']);
        $this->assertStringContainsString('S=session-id', $data['session']);
        $this->assertStringContainsString('S=fingerprint', $data['fingerprint']);
        $this->assertStringContainsString('C=content_edit', $data['listing']);
        $this->assertStringContainsString('channel_id=4', $data['listing']);
        $this->assertStringContainsString('M=entry_form', $data['publish']);
        $this->assertStringContainsString('M=member_group_manager', $data['groups']);
        $this->assertStringContainsString('M=channel_add', $data['channels']);
        $this->assertStringContainsString('C=addons_modules', $data['addons']);
        $this->assertStringContainsString('C=custom', $data['default']);
        $this->assertStringContainsString('method=license', $data['module']);
        $this->assertSame(array('settings', array('ee_ver' => '2'), true), $data['view']);
        $this->assertEquals(3, $data['page']);
        $this->assertSame(3, $data['page_query']);
        $this->assertSame(1, $data['page_default']);
        $this->assertSame(20, $data['start']);
        $this->assertSame('links', $data['pagination']);
        $this->assertSame(array('clean' => '<script>'), $data['xss']);
        $this->assertSame('news-item', $data['slug']);
        $this->assertSame(array('variables' => '{title}'), $data['variables']);
        $this->assertSame(array('limit="2"', array('limit' => 1)), $data['parameters']);
        $this->assertFalse($data['cache_missing']);
        $this->assertSame(array('id' => 1), $data['cache_set']);
        $this->assertSame(array('id' => 1), $data['cache_get']);
        $this->assertFalse($data['cache_deleted']);
        $this->assertFalse($data['cache_unknown']);
        $this->assertSame('/themes/third_party/low_seg2cat/', $data['themes']);
        $this->assertStringEndsWith('/tests/fixtures/cache/', $data['cache_path']);
        $this->assertSame('replacement', $data['overwritten']);
        $this->assertSame('', $data['removed']);
        $this->assertStringContainsString('example();', $data['footer'][0]);
        $this->assertSame('Low Seg2Cat', $data['name']);
        $this->assertSame('4.1.1', $data['version']);
        $this->assertSame('value', $data['custom']);
        $this->assertTrue($data['installed']);
        $this->assertFalse($data['not_installed']);
    }

    /**
     * Cover the special EE2.6 admin-session window and both surrounding versions.
     *
     * @return array
     */
    public static function legacyVersions()
    {
        return array(array('2.5.5'), array('2.6.0'), array('2.11.0'));
    }
}
