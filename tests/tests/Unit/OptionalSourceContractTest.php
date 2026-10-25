<?php

namespace Tests;

class OptionalSourceContractTest extends TestCase
{
    /**
     * Exercise readable and unavailable optional add-on customizations.
     *
     * @return array
     */
    public static function optionalFiles(): array
    {
        return array(array('navigation', true), array('navigation', false), array('updater', true), array('updater', false));
    }

    /**
     * Skip unreadable optional files while preserving readable customization execution.
     *
     * @dataProvider optionalFiles
     * @param string $kind Native caller under test.
     * @param bool $readable Whether the optional file can be loaded.
     * @return void
     */
    public function testOptionalFilePermissions($kind, $readable): void
    {
        $root = dirname(__DIR__, 3);
        $arguments = array($root . '/tests/optional-source-contract.php', $root . '/low_seg2cat/low_seg2cat.phar', 'low_seg2cat', 'Low\Seg2Cat', $kind, $readable ? 'readable' : 'unreadable');
        require_once $root . '/tests/Support/Process.php';
        $result = \Tests\Support\Process::execute(array_merge(array(PHP_BINARY), $arguments), getenv(), 5.0);
        $status = $result['status']; $stdout = $result['stdout']; $stderr = $result['stderr'];
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame(array('readable' => $readable, 'optional_loaded' => $readable, 'result' => true), json_decode($stdout, true));
    }
}
