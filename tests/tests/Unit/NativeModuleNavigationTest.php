<?php

namespace Tests;

use Tests\Support\Process;

class NativeModuleNavigationTest extends TestCase
{
    /**
     * Run the original base defaults through a supported empty module subclass.
     *
     * @return void
     */
    public function testDefaultModuleNavigationUsesOriginalBaseDefaults()
    {
        $result = $this->navigationProbe('$app->uri->segments = array(1 => "license"); ' .
            '$nav = new \Low\Seg2Cat\Conduit\McpNav(); $nav->setToolbarIcon(); ' .
            '$summary = nav_summary($nav, $headers); ' .
            '$summary["root_url"] = (string) $nav->getUrl("/"); ' .
            '$summary["toolbar"] = array($app->view->header["title"], ' .
            '(string) $app->view->header["toolbar_items"]["settings"]["href"]); echo json_encode($summary);', 'defaults');
        $this->assertSame(array('license'), $result['keys']);
        $this->assertSame('License', $result['title']);
        $this->assertSame('license', $result['active']);
        $this->assertSame(array('withUrl', array('cp://addons/settings/low_seg2cat/license')), $result['headers']['License'][0]);
        $this->assertSame('cp://addons/settings/low_seg2cat/index', $result['root_url']);
        $this->assertSame(array('Low Seg2Cat', 'cp://addons/settings/low_seg2cat/settings'), $result['toolbar']);
    }

    /**
     * Supply concrete module layouts and their independently expected activation.
     *
     * @return array
     */
    public static function moduleLayouts()
    {
        $items = array('' => 'Home', 'settings' => 'Settings', 'HEADINGtools' => 'Tools', 'https://example.test' => 'External');
        return array(
            'empty root and mapped empty alias' => array($items, array('edit' => ''), 'edit', 'Home', 'index'),
            'slash root and mapped slash alias' => array(array('/' => 'Home', 'settings' => 'Settings'), array('edit' => '/'), 'edit', 'Home', 'index'),
            'direct settings' => array($items, array(), 'settings', 'Settings', 'settings'),
            'named alias' => array($items, array('edit' => 'settings'), 'edit', 'Settings', 'settings'),
            'automatic module alias' => array($items, array(), 'low_seg2cat', 'Home', 'index'),
            'explicit module alias' => array($items, array('low_seg2cat' => 'settings'), 'low_seg2cat', 'Settings', 'settings'),
            'missing index' => array(array('settings' => 'Settings'), array(), 'low_seg2cat', null, null),
            'provided license' => array(array('license' => 'My License'), array(), 'license', 'My License', 'license'),
            'false defaults' => array(false, array(), 'license', 'License', 'license'),
            'unmatched route' => array($items, array(), 'other', null, null),
        );
    }

    /**
     * Normalize module entries, preserve explicit aliases and apply real nav effects.
     *
     * @dataProvider moduleLayouts
     * @param array|false $items
     * @param array $map
     * @param string $segment
     * @param string|null $title
     * @param string|null $active
     * @return void
     */
    public function testConfiguredModuleNavigation($items, array $map, $segment, $title, $active)
    {
        $options = array('items' => $items, 'active_map' => $map,
            'buttons' => $items && (isset($items['']) || isset($items['/'])) ? array('index' => array('create' => 'Create')) : false);
        $code = '$GLOBALS["low_seg2cat_nav_options"] = ' . var_export($options, true) . '; ' .
            '$app->uri->segments = array(1 => ' . var_export($segment, true) . '); ' .
            '$nav = new \Low\Seg2Cat\Conduit\McpNav(); echo json_encode(nav_summary($nav, $headers));';
        $result = $this->navigationProbe($code);
        $this->assertSame($title, $result['title']);
        $this->assertSame($active, $result['active']);
        $this->assertSame(array('pre', 'post'), $result['hooks']);
        $this->assertContains('license', $result['keys']);
        if ($title === null) {
            $this->assertSame(array(), $result['breadcrumbs']);
        } else {
            $this->assertSame(array('set_breadcrumb', array('cp://addons/settings/low_seg2cat/index', 'Low Seg2Cat')), $result['breadcrumbs'][0]);
        }
        if (isset($items['HEADINGtools'])) {
            $this->assertSame(array(), $result['headers']['Tools']);
            $this->assertSame(array(array('withUrl', array('https://example.test'))), $result['headers']['External']);
        }
        if ($options['buttons']) {
            $this->assertContains(array('withButton', array('Create', 'cp://addons/settings/low_seg2cat/create')), $result['headers']['Home']);
        }
    }

    /**
     * Let a module defer rendering and generate it after its own preparation.
     *
     * @return void
     */
    public function testDeferredModuleNavigationPreservesHookOrdering()
    {
        $options = array('items' => array('settings' => 'Settings'), 'buttons' => false, 'active_map' => false, 'defer' => true);
        $result = $this->navigationProbe('$GLOBALS["low_seg2cat_nav_options"] = ' . var_export($options, true) . '; ' .
            '$app->uri->segments = array(1 => "settings"); $nav = new \Low\Seg2Cat\Conduit\McpNav(); ' .
            '$before = array($nav->sidebar, $nav->getActiveItem(), $headers, $GLOBALS["low_seg2cat_nav_hooks"]); ' .
            '$nav->generateNav(); echo json_encode(array("before" => $before, "after" => nav_summary($nav, $headers)));');
        $this->assertSame(array(null, null, array(), array('pre')), $result['before']);
        $this->assertSame('Settings', $result['after']['title']);
        $this->assertSame('settings', $result['after']['active']);
        $this->assertSame(array('pre'), $result['after']['hooks']);
    }

    /**
     * Keep a supplied zero string as the toolbar title rather than use its default.
     *
     * @return void
     */
    public function testToolbarPreservesZeroStringTitle()
    {
        $result = $this->navigationProbe('$nav = new \Low\Seg2Cat\Conduit\McpNav(); ' .
            '$nav->setToolbarIcon("0", "https://example.test/settings", "custom"); ' .
            'echo json_encode(array("title" => $app->view->header["title"], ' .
            '"item" => $app->view->header["toolbar_items"]["custom"]));', 'defaults');
        $this->assertSame('0', $result['title']);
        $this->assertSame(array('href' => 'https://example.test/settings', 'title' => '0'), $result['item']);
    }

    /**
     * Preserve an explicit toolbar label, icon and external destination.
     *
     * @return void
     */
    public function testCustomToolbarUsesItsSuppliedLabelAndExternalUrl()
    {
        $result = $this->navigationProbe('$nav = new \Low\Seg2Cat\Conduit\McpNav(); ' .
            '$nav->setToolbarIcon("Tools", "https://example.test/settings", "custom"); ' .
            'echo json_encode($app->view->header);', 'defaults');
        $this->assertSame('Tools', $result['title']);
        $this->assertSame(array('custom' => array('href' => 'https://example.test/settings', 'title' => 'Tools')), $result['toolbar_items']);
    }

    /**
     * Use independent header objects and original source in a real module layout.
     *
     * @param string $code
     * @param string $layout
     * @return array
     */
    private function navigationProbe($code, $layout = 'configured')
    {
        $prefix = <<<'PHP'
$app = $GLOBALS['low_seg2cat_app'];
$app->cp = new \Tests\Support\Recorder();
$app->uri = new \EE_URI();
$headers = array();
$GLOBALS['low_seg2cat_nav_hooks'] = array();
$sidebar = new \Tests\Support\CallbackService(array('addHeader' => function ($title) use (&$headers) {
    $item = new \Tests\Support\Recorder();
    $headers[$title] = $item;
    return $item;
}));
$app->services['CP/Sidebar'] = new \Tests\Support\CallbackService(array('make' => function () use ($sidebar) {
    return $sidebar;
}));
class_exists('Low\\Seg2Cat\\FluxCapacitor\\Conduit\\McpNav');

/**
 * Observe fluent calls with concrete URL values and independent header identity.
 *
 * @param array $calls
 * @return array
 */
function nav_calls(array $calls)
{
    return array_map(function ($call) {
        $call[1] = array_map(function ($argument) {
            return is_object($argument) ? (string) $argument : $argument;
        }, $call[1]);
        return $call;
    }, $calls);
}

/**
 * Retain public navigation state without altering source private collections.
 *
 * @param object $nav
 * @param array $headers
 * @return array
 */
function nav_summary($nav, array $headers)
{
    $calls = array();
    foreach ($headers as $title => $header) {
        $calls[$title] = nav_calls($header->calls);
    }
    $active = null;
    foreach ((array) $nav->nav_items as $key => $item) {
        if ($item === $nav->getActiveItem()) {
            $active = $key;
        }
    }
    return array('keys' => array_keys((array) $nav->nav_items), 'title' => $nav->active_title,
        'active' => $active, 'headers' => $calls, 'hooks' => $GLOBALS['low_seg2cat_nav_hooks'],
        'breadcrumbs' => nav_calls($GLOBALS['low_seg2cat_app']->cp->calls));
}

PHP;
        $result = Process::probe($prefix . $code, array('module_nav_layout' => $layout));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        return json_decode($result['stdout'], true);
    }
}
