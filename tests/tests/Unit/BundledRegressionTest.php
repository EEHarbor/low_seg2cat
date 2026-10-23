<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Base\Ext;
use Low\Seg2Cat\FluxCapacitor\Base\Pi;
use Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings;
use Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;
use Tests\Support\Process;

class BundledRegressionTest extends TestCase
{
    /**
     * Report successful removal when a present cache key was actually deleted.
     *
     * @return void
     */
    public function testStaticCacheDeletionReportsSuccess()
    {
        StaticCache::put('delete-me', array('id' => 1));
        $this->assertTrue(StaticCache::delete('delete-me'));
        $this->assertFalse(StaticCache::has('delete-me'));
    }

    /**
     * Use the mapped label as the page title rather than its sidebar header object.
     *
     * @dataProvider modernBridges
     * @param string $name
     * @return void
     */
    public function testMappedNavigationKeepsAStringPageTitle($name)
    {
        $class = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $name;
        $bridge = new $class(array('module' => 'low_seg2cat', 'module_name' => 'Low Seg2Cat'));
        $this->app->uri = new \EE_URI();
        $this->app->uri->segments = array(1 => 'edit');
        $this->app->services['CP/Sidebar'] = new \Tests\Support\Recorder();
        $this->app->cp = new \Tests\Support\Recorder();
        $bridge->getNav(array('config' => 'Settings'), array(), array('edit' => 'config'));
        $this->assertSame('Settings', $this->app->view->cp_page_title);
    }

    /**
     * Supply the independently shipped modern bridge implementations.
     *
     * @return array
     */
    public static function modernBridges()
    {
        return array(array('EE3'), array('EE4'), array('EE5'));
    }

    /**
     * Preserve stored falsey values rather than converting them to a cache miss.
     *
     * @dataProvider falseyValues
     * @param mixed $value
     * @return void
     */
    public function testStaticCacheRoundTripsFalseyValues($value)
    {
        StaticCache::put('falsey', $value);
        $this->assertSame($value, StaticCache::get('falsey'));
        $this->assertArrayHasKey(sha1('falsey'), StaticCache::all());
    }

    /**
     * Supply zero, numeric-string zero, empty, null, and false payloads.
     *
     * @return array
     */
    public static function falseyValues()
    {
        return array('integer zero' => array(0), 'string zero' => array('0'), 'empty string' => array(''),
            'null' => array(null), 'false' => array(false));
    }

    /**
     * Report a failed database insert instead of a successful registration.
     *
     * @return void
     */
    public function testExtensionRegistrationReportsFailedInsert()
    {
        $extension = new Ext();
        $this->app->db->mutationResults['insert'] = false;
        $this->assertFalse($extension->registerExtension('failed_hook'));
    }

    /**
     * Return plugin information from the declared static API.
     *
     * @return void
     */
    public function testStaticPluginInfoDoesNotReferenceThis()
    {
        $this->assertSame('Low Seg2Cat', Pi::info()['pi_name']);
    }

    /**
     * Preserve explicit options through the legacy registration alias.
     *
     * @return void
     */
    public function testLegacyRegistrationAliasForwardsHookAndOptions()
    {
        $extension = new Ext();
        $this->invoke($extension, 'register_extension', array('method', 'custom_hook', 7, 'n'));
        $data = end($this->app->db->calls)[1][1];
        $this->assertSame('custom_hook', $data['hook']);
        $this->assertSame(7, $data['priority']);
        $this->assertSame('n', $data['enabled']);
    }

    /**
     * Preserve the hook predicate through the legacy unregister alias.
     *
     * @return void
     */
    public function testLegacyUnregisterAliasForwardsHook()
    {
        $extension = new Ext();
        $this->invoke($extension, 'unregister_extension', array('method', 'custom_hook'));
        $this->assertSame('custom_hook', end($this->app->db->calls)[1][1]['hook']);
    }

    /**
     * Return null for an absent setting without an @-hidden undefined-key warning.
     *
     * @return void
     */
    public function testMissingGlobalFieldSettingHasNoHiddenWarning()
    {
        $this->app->db->rows = array(array('settings' => base64_encode(serialize(array('known' => true)))));
        $this->assertNull((new GlobalFieldSettings())->getSetting('missing'));
    }

    /**
     * Accept the documented array cache key when retrieving a value.
     *
     * @return void
     */
    public function testPersistentCacheArrayKeyCanBeRead()
    {
        $result = Process::probe('\\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\PersistentCache::set(array("site", 1), array("id" => 1)); ' .
            'echo json_encode(\\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\PersistentCache::get(array("site", 1)));');
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('{"id":1}', $result['stdout']);
    }

    /**
     * Load bundled navigation without an @-hidden missing optional include warning.
     *
     * @return void
     */
    public function testBundledNavigationLoadsWithoutMissingIncludeWarning()
    {
        $result = Process::probe('class_exists("Low\\\\Seg2Cat\\\\FluxCapacitor\\\\Conduit\\\\McpNav"); echo "loaded";');
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('loaded', $result['stdout']);
    }

    /**
     * Load a shipped base class through the loader's no-phar-stream fallback.
     *
     * @return void
     */
    public function testSelfExtractionLoaderHasNoHiddenFilesystemWarnings()
    {
        $result = Process::probe('stream_wrapper_unregister("phar"); class_exists("Low\\\\Seg2Cat\\\\FluxCapacitor\\\\Base\\\\Ext"); echo "loaded";');
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('loaded', $result['stdout']);
    }
}
