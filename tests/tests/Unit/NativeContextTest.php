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
     * Paginate from a top-level caller without inventing a backtrace-derived URL.
     *
     * @return void
     */
    public function testPaginationWithoutAnOuterFunctionUsesTheEmptyUrlFallback()
    {
        $code = <<<'CODE'
$app = ee();
$app->input = new \Tests\Support\CallbackService(array('get' => function () { return false; }));
$pagination = new \Tests\Support\Recorder();
$app->services['CP/Pagination'] = $pagination;
$controlPanel = new class extends \Low\Seg2Cat\FluxCapacitor\Base\Mcp { public $data = array(); };
$model = new \Tests\Support\ModelQuery(array((object) array('id' => 1)));
$returned = $controlPanel->paginateModel($model);
echo json_encode(array('same_model' => $returned === $model, 'count' => $model->all()->count(), 'calls' => $pagination->calls));
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertTrue($data['same_model']);
        $this->assertSame(1, $data['count']);
        $this->assertContains(array('render', array('')), $data['calls']);
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

    /**
     * Execute CP-only base constructors and menu callbacks in a fresh CP request.
     *
     * @return void
     */
    public function testControlPanelContextInitializesVersionFootersAndMenu()
    {
        $code = <<<'CODE'
$app = ee();
$app->uri = new \EE_URI();
$tab = new \Low\Seg2Cat\FluxCapacitor\Base\Tab();
$fieldtype = new class extends \Low\Seg2Cat\FluxCapacitor\Base\Ft {};
$controlPanel = new \Low\Seg2Cat\FluxCapacitor\Base\Mcp();
$extension = new \Low\Seg2Cat\FluxCapacitor\Base\Ext();
$menu = new \Tests\Support\Recorder();
$extension->cp_custom_menu($menu);
echo json_encode(array('foot' => $app->foot, 'menu' => $menu->calls,
    'menu_url' => (string) $menu->calls[0][1][1], 'tab_version' => $tab->version,
    'fieldtype_version' => $fieldtype->version, 'nav' => $controlPanel->nav));
CODE;
        $result = Process::probe($code, array('request' => 'CP'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertCount(2, $data['foot']);
        $this->assertStringContainsString('low_seg2cat__up', $data['foot'][0]);
        $this->assertSame('4.1.1', $data['tab_version']);
        $this->assertSame('4.1.1', $data['fieldtype_version']);
        $this->assertNull($data['nav']);
        $this->assertSame('addItem', $data['menu'][0][0]);
        $this->assertSame('Low Seg2Cat', $data['menu'][0][1][0]);
        $this->assertSame('cp://addons/settings/low_seg2cat/index', $data['menu_url']);
    }

    /**
     * Exercise legacy table settings, channel lookup, and extension-only deletion.
     *
     * @return void
     */
    public function testLegacyFieldtypeSettingsAndExtensionDisable()
    {
        $code = <<<'CODE'
$app = ee();
$app->cp = new \Tests\Support\LegacyControlPanel();
$app->table = new \Tests\Support\Recorder();
$app->input = new \Tests\Support\CallbackService(array('get_post' => function ($key) { return '8'; }));
$fieldtype = new class extends \Low\Seg2Cat\FluxCapacitor\Base\Ft {};
$fieldtype->displaySettingsRow('Title', '<input>');
$fieldtype->displaySettingsRow('Wide', '<textarea>', 'Description', true);
$channel = $fieldtype->getChannelId();
$settings = $fieldtype->_package_display_settings();
$extension = new \Low\Seg2Cat\FluxCapacitor\Base\Ext();
$app->db->calls = array();
$disabled = $extension->disableExtension();
echo json_encode(array('rows' => $app->table->calls, 'channel' => $channel, 'settings' => $settings,
    'disabled' => $disabled, 'queries' => $app->db->calls));
CODE;
        $result = Process::probe($code, array('version' => '2.5.5'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame('8', $data['channel']);
        $this->assertTrue($data['settings']);
        $this->assertSame(array('add_row', array('Title', '<input>')), $data['rows'][0]);
        $this->assertSame(array('add_row', array(array('colspan' => '2', 'data' => 'Wide<textarea>'))), $data['rows'][1]);
        $this->assertTrue($data['disabled']);
        $this->assertSame(array(array('where', array('class', 'Low\\Seg2Cat\\FluxCapacitor\\Base\\Ext')), array('delete', array('extensions'))), $data['queries']);
    }

    /**
     * Delete historical module-group references under an actual EE2 major context.
     *
     * @return void
     */
    public function testLegacyUninstallerUsesGroupsAndLeavesMenuItemsAlone()
    {
        $code = <<<'CODE'
$app = ee();
$app->cp = new \Tests\Support\LegacyControlPanel();
$updater = new \Low\Seg2Cat\FluxCapacitor\Base\Upd();
$app->db->rowsByTable['modules'] = array(array('module_id' => '9'));
$app->db->calls = array();
$result = $updater->uninstall();
echo json_encode(array('result' => $result, 'queries' => $app->db->calls));
CODE;
        $result = Process::probe($code, array('version' => '2.5.5'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertTrue($data['result']);
        $this->assertContains(array('where', array('module_id', '9')), $data['queries']);
        $this->assertContains(array('delete', array('module_member_groups')), $data['queries']);
        $deletions = array_values(array_filter($data['queries'], function ($query) { return $query[0] === 'delete'; }));
        $this->assertCount(4, $deletions);
        $this->assertNotContains(array('delete', array('menu_items')), $data['queries']);
    }
}
