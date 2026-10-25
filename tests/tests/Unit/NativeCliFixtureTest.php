<?php

namespace Tests;

use Tests\Support\Process;

class NativeCliFixtureTest extends TestCase
{
    /**
     * Preserve valid native CLI declarations, namespaces and source frames.
     *
     * @dataProvider inlineSources
     * @param string $code Original native inline code.
     * @param string $expected Native output.
     * @return void
     */
    public function testNativeInlineSourceRemainsAtItsOriginalFrame($code, $expected): void
    {
        $result = Process::execute(array(PHP_BINARY, '-t', sys_get_temp_dir(), '--run', $code));
        self::assertSame(0, $result['status'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame($expected, $result['stdout']);
    }

    /**
     * Exercise the native declaration prefixes and both namespace syntaxes.
     *
     * @return array Inline source and its native output.
     */
    public static function inlineSources(): array
    {
        return array(
            'plain' => array('echo "plain";', 'plain'),
            'strict' => array('declare(strict_types=1); echo "strict";', 'strict'),
            'commented-prefix' => array('/* prefix */ declare(strict_types=1); namespace Owned; echo "named";', 'named'),
            'qualified-namespace' => array('namespace Owned\\Nested; echo "nested";', 'nested'),
            'bracketed-namespace' => array('namespace Owned { echo "bracketed"; }', 'bracketed'),
            'global-namespace' => array('namespace { echo "global"; }', 'global'),
            'ticks' => array('declare(ticks=1); echo "ticks";', 'ticks'),
            'block-declare' => array('declare(ticks=1) { echo "block"; }', 'block'),
            'alternate-declare' => array('declare(ticks=1): echo "alternate"; enddeclare;', 'alternate'),
            'line-comment' => array("// prefix\nnamespace Owned; echo \"line\";", 'line'),
            'strict-type-effect' => array('declare(strict_types=1); function owned(int $value){} try { owned("1"); } catch (\\TypeError $error) { echo "strict retained"; }', 'strict retained'),
            'native-frame' => array('echo __FILE__;', 'Command line code'),
            'namespaced-frame' => array('declare(strict_types=1); namespace Owned; function run(){echo __FILE__;} run();', 'Command line code'),
        );
    }

    /**
     * Consume native docroot argument values before locating inline source.
     *
     * @return void
     */
    public function testDocrootOptionSpellingsStillInstallTheNativeGuard(): void
    {
        $root = sys_get_temp_dir();
        foreach (array(array('-t', $root), array('-t' . $root), array('--docroot', $root), array('--docroot=' . $root)) as $options) {
            foreach (array('-r', '--run') as $inline) {
                $command = array_merge(array(PHP_BINARY), $options, array($inline,
                    'echo \\class_exists("LowSeg2CatDiagnostics",false) ? "guard" : "missing";'));
                $result = Process::execute($command);
                self::assertSame(0, $result['status'], $result['stderr']);
                self::assertSame('', $result['stderr']);
                self::assertSame('guard', $result['stdout']);
            }
        }
    }
}
