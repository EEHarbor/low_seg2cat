<?php

namespace Tests;

use Tests\Support\Process;

class NativeFieldtypeLayoutTest extends TestCase
{
    /**
     * Preserve an add-on version constant already defined by its bootstrap.
     *
     * @return void
     */
    public function testPredefinedVersionIsRetainedDuringNativeFieldtypeLoading()
    {
        $code = <<<'CODE'
define('Low\\Seg2Cat\\VERSION', '4.1.1');
class_exists('Low\\Seg2Cat\\FluxCapacitor\\Base\\Ft');
echo constant('Low\\Seg2Cat\\VERSION');
CODE;
        $result = Process::probe($code, array('fieldtype_layout' => 'system'));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('4.1.1', $result['stdout']);
    }

    /**
     * Load the unchanged archive against the actual EE parent from each supported layout.
     *
     * @dataProvider layouts
     * @param string $layout
     * @return void
     */
    public function testRealFieldtypeParentLoadsFromSupportedLayouts($layout)
    {
        $code = <<<'CODE'
$class = 'Low\\Seg2Cat\\FluxCapacitor\\Base\\Ft';
$loaded = class_exists($class);
$parent = new \ReflectionClass('EE_Fieldtype');
$trait = new \ReflectionClass('ExpressionEngine\\Service\\Template\\Variables\\ModifiableTrait');
echo json_encode(array('loaded' => $loaded, 'inherits' => is_subclass_of($class, 'EE_Fieldtype'),
    'parent_hash' => hash_file('sha256', $parent->getFileName()), 'expected_parent' => getenv('LOW_SEG2CAT_FT_PARENT_SHA256'),
    'trait_hash' => hash_file('sha256', $trait->getFileName()), 'expected_trait' => getenv('LOW_SEG2CAT_FT_TRAIT_SHA256')));
CODE;
        $result = Process::probe($code, array('fieldtype_layout' => $layout));
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertTrue($data['loaded']);
        $this->assertTrue($data['inherits']);
        $this->assertSame($data['expected_parent'], $data['parent_hash']);
        $this->assertSame($data['expected_trait'], $data['trait_hash']);
    }

    /**
     * Supply modern system, legacy app, and installer fallback directory layouts.
     *
     * @return array
     */
    public static function layouts()
    {
        return array(array('system'), array('legacy'), array('installer'));
    }
}
