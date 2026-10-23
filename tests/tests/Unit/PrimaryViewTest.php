<?php

namespace Tests;

use Tests\Support\CallbackService;
use Tests\Support\PrimaryView;
use Tests\Support\ViewRenderer;

class PrimaryViewTest extends TestCase
{
    /**
     * Cover both original templates with meaningful display and checkbox inputs.
     *
     * @return array
     */
    public static function licenseViews()
    {
        $cases = array();
        foreach (array('loose', 'archive') as $layout) {
            $cases[$layout . ' legacy'] = array($layout, 2, 'valid-key', false, 'valid-key');
            $cases[$layout . ' quoted'] = array($layout, 7, 'key"<&', false, 'key&quot;&lt;&amp;');
            $cases[$layout . ' ignored'] = array($layout, 7, 'low-seg2cat-ignore-site-1', true, '');
            $cases[$layout . ' empty'] = array($layout, 7, '', false, '');
            $cases[$layout . ' zero'] = array($layout, 7, '0', false, '0');
        }
        return $cases;
    }

    /**
     * Match original include rendering while exposing primary-script branch hits.
     *
     * @dataProvider licenseViews
     * @param string $layout
     * @param int $version
     * @param string $key
     * @param bool $ignored
     * @param string $visible
     * @return void
     */
    public function testPrimaryLicenseOutputMatchesOriginalViewRendering($layout, $version, $key, $ignored, $visible)
    {
        $variables = array('ee_ver' => $version, 'license_key' => $key, 'ignore_site' => $ignored, 'action_url' => '/save');
        $this->app->services['CP/Alert'] = new CallbackService(array('getAllInlines' => function () {
            return '<aside>alerts</aside>';
        }));
        $source = $layout === 'loose' ? LOW_SEG2CAT_ROOT . '/low_seg2cat/views/license.php'
            : 'phar://' . LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar/Views/license.php';
        $expected = (new ViewRenderer())->render($source, $variables);
        $result = PrimaryView::render($layout, $variables);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $this->assertSame($expected, $result['stdout']);
        $this->assertStringContainsString('name="license_key" value="' . $visible . '"', $result['stdout']);
        $this->assertSame($ignored, strpos($result['stdout'], 'checked="checked"') !== false);
        $this->assertSame($version > 2, strpos($result['stdout'], '<aside>alerts</aside>') !== false);
        $this->assertSame(hash_file('sha256', $source), $result['measurement_source']['original_sha256']);
        $this->assertSame($result['measurement_source']['original_sha256'], $result['measurement_source']['measurement_sha256']);
        if ($layout === 'archive') {
            $this->assertFileDoesNotExist(dirname($result['measurement_source']['measured_source']));
        }
    }
}
