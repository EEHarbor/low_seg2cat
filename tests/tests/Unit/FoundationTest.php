<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Base\Ext;
use Low\Seg2Cat\FluxCapacitor\Base\Mod;
use Low\Seg2Cat\FluxCapacitor\Base\Pi;
use Low\Seg2Cat\FluxCapacitor\Base\Tab;
use Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;
use Tests\Support\CallbackService;

class FoundationTest extends TestCase
{
    /**
     * Exercise structured, raw, absent, zero, deleted, and cleared static keys.
     *
     * @return void
     */
    public function testStaticCacheRoundTripsStructuredAndProcessedKeys()
    {
        $this->assertFalse(StaticCache::get('missing'));
        StaticCache::set(array('site', '', 1), array('ids' => array(3, 4)));
        $this->assertSame(array('ids' => array(3, 4)), StaticCache::get(array('site', 1)));
        $this->assertTrue(StaticCache::has(array('site', 1)));
        StaticCache::put('raw-key', (object) array('id' => 2), true);
        $this->assertSame(2, StaticCache::get('raw-key', true)->id);
        StaticCache::put('zero', 0);
        $this->assertTrue(StaticCache::has('zero'));
        $this->assertCount(3, StaticCache::all());
        StaticCache::delete('raw-key', true);
        $this->assertFalse(StaticCache::has('raw-key', true));
        $this->assertTrue(StaticCache::clear());
        $this->assertSame(array(), StaticCache::all());
    }

    /**
     * Register a new base extension with explicit hook options only once.
     *
     * @return void
     */
    public function testBaseExtensionRegistrationPreservesOptionsAndAvoidsDuplicates()
    {
        $extension = new Ext();
        $extension->settings = array('enabled' => true);
        $this->app->db->calls = array();
        $this->assertTrue($extension->registerExtension('method', 'hook', 7, 'n'));
        $data = $this->app->db->calls[1][1][1];
        $this->assertSame('hook', $data['hook']);
        $this->assertSame(7, $data['priority']);
        $this->assertSame('n', $data['enabled']);
        $this->assertSame(array('enabled' => true), unserialize($data['settings']));
        $this->app->db->rows = array(array('class' => Ext::class));
        $this->assertTrue($extension->registerExtension('method', 'hook'));
        $this->assertCount(3, $this->app->db->calls);
    }

    /**
     * Default the hook name and normalize invalid or unset settings.
     *
     * @return void
     */
    public function testBaseExtensionRegistrationNormalizesSettings()
    {
        $extension = new Ext();
        $extension->settings = false;
        $extension->registerExtension('default_hook');
        $this->assertSame(array(), $extension->settings);
        unset($extension->settings);
        $extension->registerExtension('another_hook');
        $this->assertSame(array(), $extension->settings);
        $last = end($this->app->db->calls);
        $this->assertSame('another_hook', $last[1][1]['hook']);
    }

    /**
     * Exercise activation, updating, disabling, and legacy aliases on shipped code.
     *
     * @return void
     */
    public function testBaseExtensionLifecycleAndAliases()
    {
        $extension = new Ext();
        $extension->hooks = array('hook_one', 'hook_two');
        $this->assertTrue($extension->activateExtension());
        $this->assertTrue($extension->activate_extension());
        $this->assertTrue($extension->updateExtension('1.0'));
        $this->assertTrue($extension->update_extension('1.0'));
        $this->assertTrue($this->invoke($extension, 'update_version'));
        $this->assertTrue($extension->disable_extension());
        $this->assertSame(array('delete', array('menu_items')), end($this->app->db->calls));
        $this->assertTrue($extension->cpCustomMenu(new \stdClass()));
        $this->assertTrue($extension->cp_custom_menu(new \stdClass()));
        $this->assertTrue($this->invoke($extension, 'unregisterExtension', array('one')));
        $this->assertTrue($this->invoke($extension, 'unregisterExtension', array('one', 'two')));
        $this->assertTrue($this->invoke($extension, 'unregister_extension', array('one')));
        $this->assertTrue($this->invoke($extension, 'register_extension', array('one')));
    }

    /**
     * Trim HTML-space aliases and preserve explicit fallback/trim behavior.
     *
     * @return void
     */
    public function testModuleParametersNormalizeWhitespaceAndFallbacks()
    {
        $module = new Mod();
        $this->assertSame('4.1.1', $module->version);
        $this->app->TMPL = new CallbackService(array('fetch_param' => function ($key) {
            $values = array('padded' => ' &nbsp;news&#32; ', 'raw' => '  value  ', 'empty' => false, 'zero' => '0');
            return $values[$key];
        }));
        $this->assertSame('news', $this->invoke($module, 'getParameter', array('padded', 'fallback')));
        $this->assertSame('  value  ', $this->invoke($module, 'getParameter', array('raw', null, false)));
        $this->assertSame('fallback', $this->invoke($module, 'getParameter', array('empty', 'fallback')));
        $this->assertSame('fallback', $this->invoke($module, 'getParameter', array('zero', 'fallback')));
    }

    /**
     * Initialize bundled plugin and tab base classes from actual add-on metadata.
     *
     * @return void
     */
    public function testPluginAndTabConstructorsReadMetadata()
    {
        $this->assertSame('4.1.1', (new Pi())->version);
        $tab = new Tab();
        $this->assertSame('4.1.1', $tab->version);
        $this->assertSame(array(), $tab->display(12));
        $this->assertTrue($tab->save((object) array('entry_id' => 1), array()));
    }

    /**
     * Resolve tab entry variables from model, legacy parameters, or defaults.
     *
     * @return void
     */
    public function testTabEntryVariablesUseAllSupportedShapes()
    {
        $tab = new Tab();
        $record = (object) array('entry_id' => 5, 'channel_id' => 6, 'site_id' => 7);
        $this->assertSame((array) $record, $tab->getEntryVariables($record, array()));
        $this->assertSame((array) $record, $tab->getEntryVariables(null, array('entry_id' => 5, 'meta' => array('channel_id' => 6, 'site_id' => 7))));
        $this->assertSame(array('entry_id' => 0, 'channel_id' => 0, 'site_id' => 0), $tab->getEntryVariables(null, array()));
    }

    /**
     * Read and update serialized fieldtype settings while preserving other keys.
     *
     * @return void
     */
    public function testGlobalFieldSettingsReadAndUpdateExistingRows()
    {
        $settings = new GlobalFieldSettings('custom_field');
        $this->assertSame('custom_field', $settings->fieldtype_name);
        $this->app->db->rows = array(array('settings' => base64_encode(serialize(array('old' => 1, 'keep' => true)))));
        $this->assertSame(1, $settings->getSetting('old'));
        $this->assertTrue($settings->updateSetting('old', 2));
        $call = end($this->app->db->calls);
        $this->assertSame(array('old' => 2, 'keep' => true), unserialize(base64_decode($call[1][1]['settings'])));
        $this->assertSame('fieldtypes', $call[1][0]);
    }

    /**
     * Distinguish missing database rows from stored serialized false settings.
     *
     * @return void
     */
    public function testGlobalFieldSettingsHandleMissingAndEmptyRows()
    {
        $settings = new GlobalFieldSettings();
        $this->assertSame('low_seg2cat', $settings->fieldtype_name);
        $this->assertNull($settings->getSettings());
        $this->assertNull($settings->getSetting('anything'));
        $this->assertFalse($settings->updateSetting('new', 2));
        $this->app->db->rows = array(array('settings' => base64_encode(serialize(false))));
        $this->assertSame(array(), $settings->getSettings());
    }
}
