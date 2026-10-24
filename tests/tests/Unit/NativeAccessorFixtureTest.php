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
            $expected = array();
            if ($message === 'accessor-startup-sentinel') {
                $expected = array(array(E_USER_DEPRECATED, '/\Aaccessor-startup-sentinel\z/'));
            }
            if ($message === 'curly braces' || $message === 'unexpected token') {
                $expected = array(array(E_DEPRECATED, '/\AArray and string offset access syntax with curly braces is deprecated\z/'),
                    array(E_WARNING, '/\AUnhandled native diagnostic: (?:Fatal error: Array and string offset access syntax with curly braces is no longer supported|Parse error: syntax error, unexpected token "\{", expecting (?:"\]"|"," or ";")) in [^\r\n]+\/prepend\.php on line [0-9]+\z/'));
            }
            $result = PrimaryLegacy::accessor(false, true, $prepare, $expected);
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
