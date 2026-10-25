<?php

namespace Tests;

use Tests\Support\PrimaryLegacy;

class NativeAccessorFixtureTest extends TestCase
{
    /**
     * Cover early completion, hidden runtime diagnostics and generated compilation.
     *
     * @return array
     */
    public static function rejectedPreparations()
    {
        return array('early exit' => array('exit(0);', 'Legacy accessor did not complete.'),
            'hidden diagnostic' => array('@trigger_error("accessor-startup-sentinel", E_USER_DEPRECATED);', 'accessor-startup-sentinel'),
            'compile diagnostic' => array('$value="a"; echo $value{0};', PHP_VERSION_ID < 80400 ? 'curly braces' : 'unexpected token'));
    }

    /**
     * Reject incomplete legacy jobs without retaining evidence or temporary source.
     *
     * @dataProvider rejectedPreparations
     * @param string $prepare
     * @param string $message
     * @return void
     */
    public function testRejectedAccessorRunPublishesNoGraphAndRemovesItsCopy($prepare, $message)
    {
        $directory = sys_get_temp_dir() . '/low-seg2cat-accessor-control-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $previousDirectory = getenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR');
        $previousExtension = getenv('LOW_SEG2CAT_XDEBUG_EXTENSION');
        try {
            putenv('LOW_SEG2CAT_CHILD_COVERAGE_DIR=' . $directory);
            $architecture = php_uname('m') === 'arm64' ? 'arm64' : 'x86';
            putenv('LOW_SEG2CAT_XDEBUG_EXTENSION=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-' .
                PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '-' . $architecture . '.so');
            $result = PrimaryLegacy::accessor(false, true, $prepare);
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
