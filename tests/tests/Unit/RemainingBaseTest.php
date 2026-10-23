<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Base\Ft;
use Low\Seg2Cat\FluxCapacitor\Base\Mcp;
use Low\Seg2Cat\FluxCapacitor\Base\Upd;
use Tests\Support\CallbackService;
use Tests\Support\Process;

class RemainingBaseTest extends TestCase
{
    /**
     * Exercise fieldtype metadata, accepted contexts, and settings rows/aliases.
     *
     * @return void
     */
    public function testFieldtypeDefaultsAndSettingsControls()
    {
        $fieldtype = new class extends Ft {};
        $this->assertSame('4.1.1', $fieldtype->version);
        $this->assertTrue($fieldtype->update('1.0'));
        $this->assertTrue($fieldtype->accepts_content_type('channel'));
        $this->assertFalse($fieldtype->accepts_content_type('member'));
        $fieldtype->displaySettingsRow('Title', '<input>', 'Description', true);
        $fieldtype->_display_settings_add_row('Other', '<textarea>');
        $result = $fieldtype->displaySettingsReturn();
        $this->assertSame('low_seg2cat', $result['field_options_low_seg2cat']['group']);
        $this->assertSame('Description', $result['field_options_low_seg2cat']['settings'][0]['desc']);
        $this->assertTrue($result['field_options_low_seg2cat']['settings'][0]['wide']);
        $this->assertCount(2, $result['field_options_low_seg2cat']['settings']);
        $this->assertSame('Custom', $fieldtype->_package_display_settings('custom', 'group', 'Custom')['custom']['label']);
    }

    /**
     * Resolve fieldtype channels from explicit settings, form input, entry model, or URI.
     *
     * @return void
     */
    public function testFieldtypeChannelResolutionUsesSupportedSources()
    {
        $fieldtype = new class extends Ft {};
        $this->app->input = new CallbackService(array('get_post' => function () { return 6; }));
        $fieldtype->settings['field_channel_id'] = 4;
        $this->assertSame(4, $fieldtype->getChannelId());
        $fieldtype->settings = array();
        $this->assertSame(6, $fieldtype->getChannelId());
        $this->app->input = new CallbackService(array('get_post' => function () { return false; }));
        $fieldtype->contentId = 12;
        $this->app->channelEntries = array((object) array('entry_id' => 12, 'channel_id' => 8));
        $this->assertSame(8, $fieldtype->getChannelId());
        $fieldtype->contentId = 0;
        $this->app->incomingUri = 'cp/publish/create/9';
        $this->assertSame(9, $fieldtype->getChannelId());
        $this->app->incomingUri = 'cp/publish/edit';
        $this->assertFalse($fieldtype->getChannelId());
    }

    /**
     * Install module rows, honor extension-only installers, and remove module records.
     *
     * @return void
     */
    public function testUpdaterLifecycleKeepsDatabaseOperationsScoped()
    {
        $updater = new Upd();
        $this->assertSame('4.1.1', $updater->version);
        $this->app->db->calls = array();
        $this->assertTrue($updater->install());
        $this->assertSame(array('insert', array('modules', array('module_name' => Upd::class,
            'module_version' => '4.1.1', 'has_cp_backend' => 'y', 'has_publish_fields' => 'n'))), $this->app->db->calls[0]);
        $updater->ext_settings = 'y';
        $this->assertTrue($updater->install());
        $this->assertCount(1, $this->app->db->calls);
        $this->assertFalse($updater->update());
        $this->assertFalse($updater->update('4.1.1'));
        $updater->module_name = 'Low_seg2cat';
        $this->assertTrue($updater->update('4.0.0'));
        $this->app->db->rowsByTable['modules'] = array(array('module_id' => 42));
        $this->assertTrue($updater->uninstall());
        $this->assertContains(array('where', array('module_id', 42)), $this->app->db->calls);
        $this->assertContains(array('delete', array('module_member_roles')), $this->app->db->calls);
        $this->assertContains(array('where', array('class', Upd::class . '_ext')), $this->app->db->calls);
    }

    /**
     * Render license settings from absent, present, and ignored license records.
     *
     * @return void
     */
    public function testControlPanelLicensePageUsesCurrentSiteAndIgnoreState()
    {
        $controlPanel = new Mcp();
        $this->assertSame('4.1.1', $controlPanel->version);
        $controlPanel->index();
        $this->assertSame(array('cp://addons/settings/low_seg2cat/license'), $this->app->redirects);
        $view = $controlPanel->license();
        $this->assertSame('rendered:low_seg2cat:license', $view['body']);
        $this->assertSame('', $this->app->rendered[0][1]['license_key']);
        $this->app->db->rowsByTable['eeharbor_licenses'] = array(array('license_key' => 'abc', 'ignore_site' => '0'));
        $controlPanel->license();
        $this->assertSame('abc', $this->app->rendered[1][1]['license_key']);
        $this->app->db->rowsByTable['eeharbor_licenses'][0]['ignore_site'] = '1';
        $controlPanel->license();
        $this->assertSame('', $this->app->rendered[2][1]['license_key']);
        $this->assertSame(1, $this->app->rendered[2][1]['ignore_site']);
    }

    /**
     * Paginate the query while counting a clone and retaining caller-owned records.
     *
     * @return void
     */
    public function testControlPanelModelPaginationKeepsTheSourceQuery()
    {
        $controlPanel = new class extends Mcp { public $data = array(); };
        $model = new \Tests\Support\ModelQuery(array((object) array('id' => 1), (object) array('id' => 2), (object) array('id' => 3)));
        $this->app->input = new CallbackService(array('get' => function () { return '2'; }));
        $pagination = new \Tests\Support\Recorder();
        $this->app->services['CP/Pagination'] = $pagination;
        $this->assertSame($model, $controlPanel->paginateModel($model, '/list', 2));
        $this->assertSame($pagination, $controlPanel->data['pagination']);
        $this->assertSame(1, $model->all()->count());
        $this->assertContains(array('currentPage', array(2)), $pagination->calls);
        $this->app->input = new CallbackService(array('get' => function () { return null; }));
        $controlPanel->paginateModel($model);
        $this->assertContains(array('currentPage', array(1)), $pagination->calls);
    }

    /**
     * Select each shipped EE bridge in a fresh process without redefining constants.
     *
     * @dataProvider bridgeVersions
     * @param string $version
     * @param string $parent
     * @return void
     */
    public function testBridgeSelectorSupportsEveryDeclaredMajor($version, $parent)
    {
        $code = <<<'CODE'
$flux = new \Low\Seg2Cat\FluxCapacitor\FluxCapacitor();
echo json_encode(array('parent' => get_parent_class('Low\\Seg2Cat\\FluxCapacitor\\Bridge\\EE'),
    'major' => $flux->getEEVersion(), 'full' => $flux->getEEVersion(false),
    'is2' => $flux->is_ee2(), 'is3' => $flux->is_ee3(), 'is4' => $flux->is_ee4(), 'is5' => $flux->is_ee5()));
CODE;
        $result = Process::probe($code, array('version' => $version));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame('Low\\Seg2Cat\\FluxCapacitor\\Bridge\\' . $parent, $data['parent']);
        $this->assertSame(substr($version, 0, 1), $data['major']);
        $this->assertSame($version, $data['full']);
        foreach (array(2, 3, 4, 5) as $major) {
            $this->assertSame(substr($version, 0, 1) === (string) $major, $data['is' . $major]);
        }
    }

    /**
     * Supply legacy through current bridge selector inputs.
     *
     * @return array
     */
    public static function bridgeVersions()
    {
        return array(array('2.11.0', 'EE2'), array('3.5.0', 'EE3'), array('4.3.0', 'EE4'),
            array('5.4.0', 'EE5'), array('6.4.0', 'EE6'), array('7.5.0', 'EE6'));
    }
}
