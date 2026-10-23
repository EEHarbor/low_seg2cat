<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;
use Tests\Support\CallbackService;
use Tests\Support\Recorder;

class BridgeTest extends TestCase
{
    /**
     * Supply every independently shipped modern bridge implementation.
     *
     * @return array
     */
    public static function modernBridges()
    {
        return array(array('EE3'), array('EE4'), array('EE5'));
    }

    /**
     * Build control-panel routes and move path parameters out of query strings.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testModernBridgeUrlsAndPagination($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $this->assertSame('cp://addons/settings/low_seg2cat', (string) $bridge->getBaseURL());
        $this->assertSame('cp://addons/settings/low_seg2cat', (string) $bridge->getBaseURL('/'));
        $this->assertSame('cp://addons/settings/low_seg2cat/config?x=1', (string) $bridge->getBaseURL('config', '?x=1'));
        $this->assertSame('cp://publish', (string) $bridge->cpURL('listing'));
        $this->assertSame('cp://publish/create/4?filter=new', (string) $bridge->cpURL('publish', 'create', array('channel_id' => 4, 'filter' => 'new')));
        $this->assertSame('cp://publish/edit/entry/9', (string) $bridge->cpURL('publish', 'edit', array('entry_id' => 9)));
        $this->assertSame('cp://publish/edit', (string) $bridge->cpURL('publish', 'edit'));
        $this->assertSame('cp://members/roles?sort=name', (string) $bridge->cpURL('members', 'roles', array('sort' => 'name')));
        $this->assertSame('cp://addons/settings/low_seg2cat/license?id=1', (string) $bridge->moduleURL('license', array('id' => 1)));
        $this->assertSame(20, $bridge->getStartNum(array('current_page' => 3, 'per_page' => 10)));
        $this->app->input = new CallbackService(array('get' => function ($key) { return $key === 'page' ? '3' : false; }));
        $this->assertSame('3', $bridge->getCurrentPage());
        $this->app->input = new CallbackService(array('get' => function () { return false; }));
        $this->assertSame(1, $bridge->getCurrentPage());
        $this->app->input = new CallbackService(array('get' => function () { return '5'; }));
        $this->assertSame('5', $bridge->getCurrentPage());
        $pagination = new Recorder();
        $this->app->services['CP/Pagination'] = $pagination;
        $options = array('total_rows' => 42, 'per_page' => 10, 'current_page' => 2, 'base_url' => '/list');
        $this->assertSame($pagination, $bridge->pagination($options));
        $bridge->pagination($options + array('page_links' => 7, 'page_query_string' => 'p'));
        $this->assertContains(array('displayPageLinks', array(7)), $pagination->calls);
        $this->assertContains(array('queryStringVariable', array('p')), $pagination->calls);
    }

    /**
     * Exercise version comparisons, settings persistence, and cached configuration.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testModernBridgeSettingsAndVersionHelpers($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $this->assertSame('7', $bridge->getEEVersion());
        $this->assertSame('7.5.0', $bridge->getEEVersion(false));
        $this->assertTrue($bridge->ver_lt('8'));
        $this->assertTrue($bridge->ver_gt('6'));
        $this->assertTrue($bridge->ver_lte('7'));
        $this->assertTrue($bridge->ver_gte('7'));
        $this->assertFalse($bridge->is_ee2());
        $this->assertFalse($bridge->is_ee3());
        $this->assertFalse($bridge->is_ee4());
        $this->assertFalse($bridge->is_ee5());
        $this->app->db->data_cache['table_names'] = array('stale');
        $this->app->db->from('legacy_query');
        $legacyCalls = $this->app->db->calls;
        $this->app->builderCache['table_names'] = array('fresh-builder-stale');
        $this->assertSame('4.1.1', $bridge->getConfig('version'));
        $this->assertSame(array('stale'), $this->app->db->data_cache['table_names']);
        $this->assertNotSame($this->app->db, $this->app->builders[0]);
        $this->assertArrayNotHasKey('table_names', $this->app->builders[0]->data_cache);
        $this->assertSame('Low Seg2Cat', $bridge->getSettings(true)['name']);
        $this->assertSame('Low Seg2Cat', $bridge->getSettings()->name);
        $bridge->setConfig('custom', 'memory');
        $this->assertSame('memory', $bridge->getConfig('custom'));
        $this->app->db->tables = array('low_seg2cat_settings');
        $this->app->db->rowsByTable['low_seg2cat_settings'] = array();
        $bridge->setConfig('custom', 'insert');
        $insertBuilder = end($this->app->builders);
        $this->assertContains(array('insert', array('low_seg2cat_settings', array('site_id' => 1, 'custom' => 'insert'))), $insertBuilder->calls);
        $this->app->db->rowsByTable['low_seg2cat_settings'] = array(array('custom' => 'existing'));
        $this->app->db->data_cache['table_names'] = array('stale');
        $bridge->setConfig('custom', 'update');
        $updateBuilder = end($this->app->builders);
        $this->assertNotSame($insertBuilder, $updateBuilder);
        $this->assertContains(array('update', array('low_seg2cat_settings', array('site_id' => 1, 'custom' => 'update'))), $updateBuilder->calls);
        StaticCache::clear();
        $this->app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize(array('ft' => true)))));
        $this->assertTrue($bridge->getConfig('ft'));
        $this->assertSame('existing', $bridge->getConfig('custom'));
        StaticCache::clear();
        $this->app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize(false))));
        $this->assertSame('4.1.1', $bridge->getConfig('version'));
        $this->assertSame($legacyCalls, $this->app->db->calls);
    }

    /**
     * Dispatch persistent/session cache operations through their actual EE services.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testModernBridgeCacheRoutingAndServices($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $persistent = new Recorder();
        $session = new Recorder();
        $this->app->cache = $persistent;
        $this->app->session = $session;
        $this->app->legacy_api = new Recorder();
        $bridge->instantiate('channel_entries');
        $this->assertSame(array(array('instantiate', array('channel_entries'))), $this->app->legacy_api->calls);
        $this->assertFalse($bridge->cache('unknown'));
        $bridge->cache('get', 'one');
        $bridge->cache('set', 'two', array('id' => 2));
        $bridge->cache('delete', 'three');
        $bridge->cache('clear', 'four');
        $bridge->cache('get', 'five', false, false);
        $bridge->cache('set', 'six', 'value', false);
        $this->assertSame(array('get', array('/low_seg2cat/one')), $persistent->calls[0]);
        $this->assertSame(array('save', array('/low_seg2cat/two', array('id' => 2))), $persistent->calls[1]);
        $this->assertSame(array('cache', array('low_seg2cat', 'five', false)), $session->calls[0]);
        $this->assertSame(array('set_cache', array('low_seg2cat', 'six', 'value')), $session->calls[1]);
        $bridge->overwriteEEClass('typography', 'replacement');
        $bridge->removeEEClass('typography');
        $this->assertSame(array('typography' => 'replacement'), $this->app->assigned);
        $this->assertSame(array('typography'), $this->app->removed);
        $bridge->javascript_to_page('console.log(1);');
        $this->assertStringContainsString('console.log(1);', $this->app->foot[0]);
        $this->assertSame($this->app->foot, $bridge->getFooter());
        $this->assertSame(PATH_CACHE, $bridge->getCachePath());
        $this->app->config->items['cache_path'] = '/custom/cache/';
        $this->assertSame('/custom/cache/', $bridge->getCachePath());
    }

    /**
     * Render named/string views and preserve explicitly supplied view variables.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testModernBridgeViewsAndUrlInfo($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $viewService = new CallbackService(array('makeFromString' => function ($html) {
            return new CallbackService(array('render' => function ($vars) use ($html) { return $html . ':' . $vars['cp_page_title']; }));
        }, 'make' => function ($path) {
            return new CallbackService(array('render' => function ($vars) use ($path) { return $path . ':' . $vars['cp_page_title']; }));
        }));
        $this->app->services['View'] = $viewService;
        $result = $bridge->view('settings');
        $this->assertSame('low_seg2cat:settings:Low Seg2Cat', $result['body']);
        $this->assertSame(array('cp://addons/settings/low_seg2cat/' => 'Low Seg2Cat'), $result['breadcrumb']);
        $result = $bridge->view('ignored', array('base_url' => '/custom', 'cp_page_title' => 'Custom'), true, 'inline');
        $this->assertSame('inline:Custom', $result['body']);
        $this->app->uri = new \EE_URI();
        $this->app->uri->uri_string = 'cp/addons/settings/low_seg2cat/license';
        $this->assertSame('license', $bridge->getCurrentUrlInfo('method'));
        $this->assertSame('low_seg2cat', $bridge->getCurrentUrlInfo()['module']);
        $this->app->uri->uri_string = 'cp/addons/settings/low_seg2cat';
        $this->assertSame('index', $bridge->getCurrentUrlInfo('method'));
        $this->assertIsArray($bridge->getCurrentUrlInfo('unknown'));
    }

    /**
     * Build sidebar navigation and dispatch formatting/parser/security collaborators.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testModernBridgeNavigationAndHelperDelegation($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $this->app->uri = new \EE_URI();
        $this->app->uri->segments = array(1 => 'low_seg2cat');
        $sidebar = new Recorder();
        $this->app->services['CP/Sidebar'] = $sidebar;
        $this->app->cp = new Recorder();
        $bridge->getNav(array('/' => 'Home', 'config' => 'Settings', 'https://example.test/' => 'External'), array('config' => array('new' => 'New')));
        $this->assertSame('Home', $this->app->view->cp_page_title);
        $this->assertContains(array('withUrl', array('https://example.test/')), $sidebar->calls);
        $buttons = array_values(array_filter($sidebar->calls, function ($call) { return $call[0] === 'withButton'; }));
        $this->assertSame('New', $buttons[0][1][0]);
        $this->assertSame('cp://addons/settings/low_seg2cat/new', (string) $buttons[0][1][1]);
        $bridge->getNav();
        $bridge->flashData('message_error', 'Error', 'Body');
        $bridge->flashData('message_failure', 'Failure');
        $bridge->flashData('message_notice', 'Notice');
        $bridge->flashData('message_success', 'Success', '', array('cannotClose', 'nonexistent'));
        $this->assertContains(array('asIssue', array()), $this->app->alerts->calls);
        $this->assertContains(array('asWarning', array()), $this->app->alerts->calls);
        $this->assertContains(array('asSuccess', array()), $this->app->alerts->calls);
        $this->assertContains(array('cannotClose', array()), $this->app->alerts->calls);
        $this->app->services['Security/XSS'] = new CallbackService(array('clean' => function ($input) { return array('cleaned' => $input); }));
        $this->assertSame(array('cleaned' => '<script>'), $bridge->xss_clean('<script>'));
        $this->app->services['Variables/Parser'] = new CallbackService(array(
            'extractVariables' => function ($input) { return array('variables' => $input); },
            'assign_variables' => function ($input) { return array('variables' => $input); },
            'parseTagParameters' => function ($input, $defaults) { return array($input, $defaults); },
            'assign_parameters' => function ($input, $defaults) { return array($input, $defaults); },
        ));
        $this->assertSame(array('variables' => '{title}'), $bridge->assign_variables('{title}'));
        $this->assertSame(array('limit="2"', array('limit' => 1)), $bridge->assign_parameters('limit="2"', array('limit' => 1)));
        $this->app->services['Format'] = new CallbackService(array('make' => function ($type, $input) {
            return new CallbackService(array('urlSlug' => function () use ($input) {
                return new CallbackService(array('compile' => function () use ($input) { return str_replace(' ', '-', $input); }));
            }, 'url' => function () use ($input) { return 'https://' . $input; }));
        }));
        $this->assertSame('news-item', $bridge->url_title('  NEWS ITEM  '));
        $this->assertSame('https://example.test', $bridge->prep_url('example.test'));
        $this->app->addons_model = new CallbackService(array('module_installed' => function ($module) { return $module === 'Channel'; }));
        $this->assertTrue($bridge->module_installed('Channel'));
        $this->app->config->items['theme_folder_url'] = '/themes/';
        $this->assertSame('/themes/user/low_seg2cat/', $bridge->getAddonThemesDir());
        $this->assertSame('https://example.test/path', $bridge->reduce_double_slashes('https://example.test//path'));
    }
}
