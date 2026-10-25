<?php

namespace Tests;

use Tests\Support\PrimaryView;

class PrimaryViewFixtureTest extends TestCase
{
    /**
     * Reject an unknown measurement layout before allocating resources.
     *
     * @return void
     */
    public function testUnknownPrimaryLayoutIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        PrimaryView::render('unknown', array());
    }

    /**
     * Exercise incomplete and diagnostic preparation independently of source.
     *
     * @return array
     */
    public static function rejectedPreparations()
    {
        return array('early zero exit' => array('exit(0);', 'Primary view did not complete.'),
            'suppressed generated diagnostic' => array('@trigger_error("primary-generated-sentinel", E_USER_DEPRECATED);', 'primary-generated-sentinel'),
            'generated compile diagnostic' => array('switch (1) { case 1: continue; }', 'continue'),
            'nested generated diagnostic' => array('file_put_contents(sys_get_temp_dir() . "/nested.php", ' .
                '\'<?php @trigger_error("primary-nested-sentinel", E_USER_DEPRECATED);\'); ' .
                'require sys_get_temp_dir() . "/nested.php";', 'primary-nested-sentinel'));
    }

    /**
     * Reject incomplete runs without publishing an accepted source measurement.
     *
     * @dataProvider rejectedPreparations
     * @param string $prepare
     * @param string $message
     * @return void
     */
    public function testPreparationFailurePublishesNoSourceGraph($prepare, $message)
    {
        $directory = sys_get_temp_dir() . '/low-seg2cat-primary-control-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $previousDirectory = getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR');
        $previousExtension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
        try {
            putenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR=' . $directory);
            $version = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
            $architecture = php_uname('m') === 'arm64' ? 'arm64' : 'x86';
            putenv('LOW_SEG2CAT_XDEBUG_EXTENSION=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-' . $version . '-' . $architecture . '.so');
            $result = PrimaryView::render('archive', array(), $prepare);
            $this->assertNotSame(0, $result['status']);
            $this->assertStringContainsString($message, $result['stderr']);
            $this->assertSame(array(), glob($directory . '/*'));
            $this->assertFileDoesNotExist(dirname($result['measurement_source']['measured_source']));
        } finally {
            putenv($previousDirectory === false ? 'LOW_SEG2CAT_CHILD_COVERAGE_DIR' : 'LOW_SEG2CAT_CHILD_COVERAGE_DIR=' . $previousDirectory);
            putenv($previousExtension === false ? 'LOW_SEG2CAT_XDEBUG_EXTENSION' : 'LOW_SEG2CAT_XDEBUG_EXTENSION=' . $previousExtension);
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
