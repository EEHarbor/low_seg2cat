<?php

namespace Tests;

use Tests\Support\PrimaryLegacy;

class NativeAccessorTest extends TestCase
{
    /**
     * Exercise the legacy fallback, its bootstrap-defined sibling and version lookup.
     *
     * @return array
     */
    public static function accessorContexts()
    {
        return array('fallback' => array(false, true), 'existing accessor' => array(true, true),
            'version from facade' => array(false, false));
    }

    /**
     * Resolve the current legacy facade each time without replacing an existing ee().
     *
     * @dataProvider accessorContexts
     * @param bool $existing
     * @param bool $definedVersion
     * @return void
     */
    public function testOriginalAccessorDelegatesToTheCurrentLegacyFacade($existing, $definedVersion)
    {
        $result = PrimaryLegacy::accessor($existing, $definedVersion);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $data = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($existing, $data['before']);
        $this->assertTrue($data['after']);
        $this->assertTrue($data['first_same']);
        $this->assertTrue($data['second_same']);
        $this->assertTrue($data['different']);
        $this->assertSame($definedVersion ? 2 : 3, $data['calls']);
        $this->assertSame($definedVersion ? array() : array('app_version'), $data['config_calls']);
        $this->assertSame('2.5.5', $data['version']);
        $this->assertSame($existing ? $result['existing_accessor_file'] : $result['measurement_source']['measured_source'], $data['function_file']);
        $this->assertSame(PHP_BINARY, $data['binary']);
        $this->assertSame(PHP_VERSION, $data['php']);
        $this->assertSame($data['e_all'], $data['mask'] & $data['e_all']);
        $provenance = $result['measurement_source'];
        $this->assertSame(hash_file('sha256', $provenance['original_source']), $provenance['original_sha256']);
        $this->assertSame($provenance['original_sha256'], $provenance['measurement_sha256']);
        $this->assertFileDoesNotExist(dirname($provenance['measured_source']));
    }
}
