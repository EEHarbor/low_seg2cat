<?php

namespace Tests;

class NativeControllerContractTest extends TestCase
{
    /**
     * Return native controller consumers with independently owned state contracts.
     *
     * @return array
     */
    public static function nativeConsumers(): array
    {
        return array(array('bare'), array('public'), array('protected'), array('private'), array('magic'), array('array_access'));
    }

    /**
     * Preserve bare public reads, visibility, inherited magic and ArrayAccess state.
     *
     * @dataProvider nativeConsumers
     * @param string $kind Native consumer contract.
     * @return void
     */
    public function testNativeControllerContracts($kind): void
    {
        $result = \Tests\Support\Process::execute(array(PHP_BINARY, LOW_SEG2CAT_ROOT . '/tests/mcp-api-contract.php', LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar', $kind));
        $status = $result['status'];
        $stdout = $result['stdout'];
        $stderr = $result['stderr'];
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        $result = json_decode($stdout, true);
        self::assertIsArray($result, $stdout);
        if ($kind === 'private') {
            self::assertSame(array('Error', 'Cannot access private property NativeApiConsumer::$data'), $result['error']);
            self::assertSame(array('seed' => true), $result['data']);
            self::assertSame(array(), $result['calls']);
            return;
        }
        self::assertNull($result['error']);
        self::assertSame(array(array('limit', 10), array('offset', 10)), $result['calls']);
        self::assertSame($kind === 'bare' ? array('pagination' => 'links') : array('seed' => true, 'pagination' => 'links'), $result['data']);
        self::assertSame($kind === 'array_access' ? 'ArrayObject' : 'array', $result['data_type']);
        self::assertSame($kind === 'magic' ? array('get:data') : array(), $result['magic']);
    }

}
