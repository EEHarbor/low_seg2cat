<?php

namespace Tests;

class ProceduralTest extends TestCase
{
    /**
     * Read the actual add-on manifest and English language dictionary.
     *
     * @return void
     */
    public function testSetupAndLanguageFilesExposeShippedMetadata()
    {
        $setup = require LOW_SEG2CAT_ROOT . '/low_seg2cat/addon.setup.php';
        $json = json_decode(file_get_contents(LOW_SEG2CAT_ROOT . '/low_seg2cat/addon.json'), true);
        $this->assertSame($json['name'], $setup['name']);
        $this->assertSame($json['version'], $setup['version']);
        $this->assertSame($json['namespace'], $setup['namespace']);
        $this->assertTrue($setup['settings_exist']);
        require LOW_SEG2CAT_ROOT . '/low_seg2cat/language/english/low_seg2cat_lang.php';
        $this->assertCount(7, $lang);
        $this->assertSame('Ignore pagination', $lang['ignore_pagination']);
    }

    /**
     * Render the settings wrapper using its real embedded form view.
     *
     * @return void
     */
    public function testSettingsViewEmbedsSharedForm()
    {
        $view = new \Tests\Support\ViewRenderer();
        $output = $view->render(LOW_SEG2CAT_ROOT . '/low_seg2cat/views/settings.php');
        $this->assertStringContainsString('<div class="box">', $output);
        $this->assertSame(array('ee:_shared/form'), $view->embedded);
        $this->assertStringContainsString('shared-form', $output);
    }

    /**
     * Render the add-on license template with legacy and modern EE alert handling.
     *
     * @dataProvider licenseViews
     * @param string $file
     * @param int $version
     * @param string $key
     * @param string $visibleKey
     * @return void
     */
    public function testLicenseViewsKeepIgnoredKeysHidden($file, $version, $key, $visibleKey)
    {
        $this->app->services['CP/Alert'] = new \Tests\Support\CallbackService(array('getAllInlines' => function () {
            return '<aside>alerts</aside>';
        }));
        $view = new \Tests\Support\ViewRenderer();
        $output = $view->render($file, array('ee_ver' => $version, 'license_key' => $key, 'ignore_site' => true, 'action_url' => '/save'));
        $this->assertStringContainsString('License Key', $output);
        $this->assertStringContainsString('value="' . $visibleKey . '"', $output);
        $this->assertStringContainsString('checked="checked"', $output);
        if ($version > 2) {
            $this->assertStringContainsString('<aside>alerts</aside>', $output);
        } else {
            $this->assertStringNotContainsString('<aside>alerts</aside>', $output);
        }
    }

    /**
     * Supply the add-on license template and both display branches.
     *
     * @return array
     */
    public static function licenseViews()
    {
        $root = dirname(__DIR__, 3);
        $files = array($root . '/low_seg2cat/views/license.php');
        $cases = array();
        foreach ($files as $file) {
            $cases[] = array($file, 7, 'valid-key', 'valid-key');
            $cases[] = array($file, 2, 'low-seg2cat-ignore-site-1', '');
        }
        return $cases;
    }

}
