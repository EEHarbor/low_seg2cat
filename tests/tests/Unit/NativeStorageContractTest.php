<?php

namespace Tests;

class NativeStorageContractTest extends TestCase
{
    /**
     * Remove owned empty cache directories before the base environment restores.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (array(PATH_CACHE . 'low_seg2cat/', PATH_CACHE) as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
        parent::tearDown();
    }

    /**
     * Supply native malformed remainders after a valid escaped prefix and object.
     *
     * @return array
     */
    public static function malformedTails(): array
    {
        $cases = array();
        foreach (array('legacy', 'modern') as $hydration) {
            foreach (array('S:9:"bad";', 'S:1:"\xz";', 's:9:"bad";', '!', 'C:1:"X":-999:{S:}', 'C:1:"X":-14:{S:}', 's:999999999999999999999999999999:"S:";', 'S:999999999999999999999999999999:"bad";', 'C:1:"X":+999999999999999999999999999999:{S:}', 'C:1:"X":-999999999999999999999999999999:{S:}') as $tail) {
                $cases[] = array($tail, $hydration);
            }
        }
        return $cases;
    }

    /**
     * Keep native deferred hydration through every actual stored-data caller.
     *
     * @dataProvider malformedTails
     * @param string $tail Unrecognized native remainder.
     * @param string $hydration Legacy or modern callback.
     * @return void
     */
    public function testMalformedTailRetainsHydration($tail, $hydration): void
    {
        foreach (array('decoder', 'global', 'persistent') as $caller) {
            list($status, $stdout, $stderr) = $this->runContract($caller, $tail, 'side-effect', $hydration);
            self::assertSame(0, $status, $stdout . $stderr);
            self::assertSame('', $stderr);
            $result = json_decode($stdout, true);
            self::assertSame(1, $result['calls'], $caller);
            self::assertNull($result['error'], $caller);
            self::assertTrue($result['handler_restored']);
            if ($caller === 'global') {
                self::assertSame('a:0:{}', $result['value']);
            }
            if ($caller === 'decoder' || $caller === 'persistent') {
                self::assertSame('b:0;', $result['value']);
            }
        }
    }

    /**
     * Keep callback exceptions observable after native parsing rejects a later token.
     *
     * @return void
     */
    public function testMalformedTailRetainsCallbackExceptions(): void
    {
        foreach (array('legacy', 'modern') as $hydration) {
            foreach (array('decoder', 'global', 'persistent') as $caller) {
                list($status, $stdout, $stderr) = $this->runContract($caller, 'S:9:"bad";', 'exception', $hydration);
                self::assertSame(0, $status, $stdout . $stderr);
                self::assertSame('', $stderr);
                $result = json_decode($stdout, true);
                self::assertSame(1, $result['calls']);
                self::assertSame(array('RuntimeException', 'owned malformed callback exception'), $result['error']);
                self::assertTrue($result['handler_restored']);
            }
        }
    }

    /**
     * Keep caught callback diagnostics red in actual owned decoder/storage children.
     *
     * @return void
     */
    public function testMalformedTailDiagnosticsCannotPass(): void
    {
        foreach (array('legacy', 'modern') as $hydration) {
            foreach (array('decoder', 'global', 'persistent') as $caller) {
                list($status, $stdout, $stderr) = $this->runContract($caller, 'S:9:"bad";', 'diagnostic', $hydration);
                self::assertSame(2, $status, $stdout . $stderr);
                self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $stderr);
                self::assertStringContainsString('owned malformed callback diagnostic', $stderr);
                $result = json_decode($stdout, true);
                self::assertSame(1, $result['calls']);
                self::assertSame('ErrorException', $result['error'][0]);
                self::assertTrue($result['handler_restored']);
            }
        }
    }

    /**
     * Accept native signed object counts without rewriting custom payload bytes.
     *
     * @return void
     */
    public function testSignedObjectCountsAndNegativeCustomLengths(): void
    {
        foreach (array('1', '01', '+1', '+01') as $count) {
            $stored = 'O:08:"stdClass":' . $count . ':{S:1:"\78";S:1:"\41";}';
            $value = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored, $valid);
            self::assertTrue($valid);
            self::assertSame(array('x' => 'A'), get_object_vars($value));
        }
        self::assertEquals(new \stdClass(), \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('O:8:"stdClass":-0:{}'));
        self::assertFalse(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('C:1:"X":-999:{S:}', $valid));
        self::assertFalse($valid);
    }

    /**
     * Bound decimal text without rejecting native leading-zero lengths.
     *
     * @return void
     */
    public function testLeadingZeroStringLengthsRemainAccepted(): void
    {
        self::assertSame(array('x' => 'A'), \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('a:1:{S:0001:"\78";S:0001:"\41";}', $valid));
        self::assertTrue($valid);
    }

    /**
     * Preserve native overflow values and depth failures without losing handler state.
     *
     * @return void
     */
    public function testNativeParserBoundaryValues(): void
    {
        self::assertSame(PHP_INT_MAX, \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('i:999999999999999999999999999999;', $valid));
        self::assertTrue($valid);
        self::assertSame(PHP_INT_MIN, \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('i:-999999999999999999999999999999;', $valid));
        self::assertTrue($valid);
        $stored = 'a:2:{s:8:"positive";i:999999999999999999999999999999;s:8:"negative";i:-999999999999999999999999999999;}';
        self::assertSame(array('positive' => PHP_INT_MAX, 'negative' => PHP_INT_MIN), \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored, $valid));
        self::assertTrue($valid);
        $deep = str_repeat('a:1:{i:0;', 4097) . 'i:1;' . str_repeat('}', 4097);
        self::assertFalse(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($deep, $valid));
        self::assertFalse($valid);
    }

    /**
     * Keep accepted native integer clamps through actual cache/settings callers.
     *
     * @return void
     */
    public function testAcceptedClampsSurviveStoredDataCallers(): void
    {
        $wire = 'a:3:{s:13:"enabled_types";a:1:{i:0;s:1:"A";}s:8:"positive";i:999999999999999999999999999999;s:8:"negative";i:-999999999999999999999999999999;}';
        $expected = array('enabled_types' => array('A'), 'positive' => PHP_INT_MAX, 'negative' => PHP_INT_MIN);
        self::assertSame($expected, \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($wire, $valid));
        self::assertTrue($valid);
        ee()->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode($wire)));
        self::assertSame($expected, (new \Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings())->getSettings());
        $cache = '\\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\PersistentCache';
        self::assertTrue($cache::set('owned-clamp', array()));
        try {
            file_put_contents(PATH_CACHE . 'low_seg2cat/' . sha1('owned-clamp'), $wire);
            \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
            self::assertSame($expected, $cache::get('owned-clamp'));
        } finally {
            $cache::delete('owned-clamp');
        }

    }

    /**
     * Forward PHP7.4's fifth handler argument without replacing the prior handler.
     *
     * @return void
     */
    public function testNativeDiagnosticHandlerArguments(): void
    {
        $code = 'class OwnedNativeContext { public function __wakeup() { @trigger_error("owned native context", E_USER_DEPRECATED); } } '
            . '$events = array(); $handler = static function ($severity, $message, $file, $line, $context = null) use (&$events) { $events[] = array($severity, $message, func_num_args(), is_array($context)); return true; }; '
            . 'set_error_handler($handler); try { \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(serialize(new OwnedNativeContext())); $after = set_error_handler($handler); restore_error_handler(); echo json_encode(array($events, $after === $handler)); } finally { restore_error_handler(); }';
        list($status, $stdout, $stderr) = $this->diagnosticProcess($code);
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame(array(array(array(E_USER_DEPRECATED, 'owned native context', PHP_VERSION_ID < 80000 ? 5 : 4, PHP_VERSION_ID < 80000)), true), json_decode($stdout, true));
    }

    /**
     * Forward the same native range warning when it originates in a user callback.
     *
     * @return void
     */
    public function testNestedNativeRangeWarningStillFails(): void
    {
        $code = 'class OwnedNestedRange { public function __wakeup() { unserialize(\'i:999999999999999999999999999999;\'); } } '
            . '$probe = static function () {}; $before = set_error_handler($probe); restore_error_handler(); '
            . 'try { \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(serialize(new OwnedNestedRange())); } catch (ErrorException $error) {} '
            . '$after = set_error_handler($probe); restore_error_handler(); if ($before !== $after) { throw new RuntimeException("Handler changed"); } echo "restored";';
        list($status, $stdout, $stderr) = $this->diagnosticProcess($code, array(array(E_WARNING, '/\Aunserialize\(\): Numerical result out of range\z/')));
        self::assertSame(2, $status);
        self::assertSame('restored', $stdout);
        self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $stderr);
        self::assertStringContainsString('unserialize(): Numerical result out of range', $stderr);
    }

    /**
     * Preserve native string byte offsets and quiet missing or nonindexable roots.
     *
     * @return void
     */
    public function testNativeSettingsOffsetsAndInvalidRoots(): void
    {
        $settings = (new \ReflectionClass(\Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings::class))->newInstanceWithoutConstructor();
        $settings->fieldtype_name = 'low_seg2cat';
        $cases = array(array('text', 0, 't'), array('text', '0', 't'), array('text', 1, 'e'), array('text', -1, 't'), array('text', -4, 't'),
            array('text', 4, null), array('text', -5, null), array('text', 'missing', null), array((object) array('name' => 'value'), 'name', null),
            array(5, 'name', null), array(1.5, 0, null), array(true, 0, null), array(array(), 0, null), array(array(0 => 'zero'), 0, 'zero'), array(array('x' => false), 'x', false));
        foreach ($cases as $case) {
            ee()->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize($case[0]))));
            self::assertSame($case[2], $settings->getSetting($case[1]));
        }
    }

    /**
     * Preserve native stored-string byte writes and exact database serialization.
     *
     * @return void
     */
    public function testNativeStringSettingsWrites(): void
    {
        $settings = (new \ReflectionClass(\Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings::class))->newInstanceWithoutConstructor();
        $settings->fieldtype_name = 'low_seg2cat';
        foreach (array(array(0, 'X', 'Xext'), array(-1, 'Z', 'texZ')) as $case) {
            $this->app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize('text'))));
            self::assertTrue($settings->updateSetting($case[0], $case[1]));
            $calls = array_values(array_filter(ee()->db->calls, static function ($call) { return $call[0] === 'update'; }));
            $last = end($calls);
            self::assertSame(base64_encode(serialize($case[2])), $last[1][1]['settings']);
        }
    }

    /**
     * Preserve saved ArrayAccess root objects, computed defaults and native setters.
     *
     * @return void
     */
    public function testNativeArrayAccessSettingsRoundTrip(): void
    {
        $settings = new \Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings();
        $saved = new NativeComputedSettings(array('saved' => 7));
        $payload = base64_encode(serialize($saved));
        ee()->db->rowsByTable['fieldtypes'] = array(array('settings' => $payload));
        self::assertSame(7, $settings->getSetting('saved'));
        self::assertSame('computed-default', $settings->getSetting('missing'));
        self::assertSame(serialize($saved), serialize($settings->getSettings()));
        self::assertTrue($settings->updateSetting('added', false));
        $calls = array_values(array_filter(ee()->db->calls, static function ($call) { return $call[0] === 'update'; }));
        $decoded = unserialize(base64_decode($calls[0][1][1]['settings']));
        self::assertInstanceOf(NativeComputedSettings::class, $decoded);
        self::assertSame(array('saved' => 7, 'added' => false), $decoded->getArrayCopy());
        self::assertSame(array(array('added', false)), $decoded->writes);
    }

    /**
     * Run an actual owned decoder process and return separate streams.
     *
     * @param string $code Native caller statements.
     * @return array Exit status and output streams.
     */
    private function diagnosticProcess($code, array $expectedDiagnostics = array()): array
    {
        $result = \Tests\Support\Process::probe($code, array('expected_diagnostics' => $expectedDiagnostics));
        return array($result['status'], $result['stdout'], $result['stderr']);
    }

    /**
     * Run an owned native storage control with the actual distributed callers.
     *
     * @param string $caller Stored-data caller.
     * @param string $tail Malformed native tail.
     * @param string $mode Callback behavior.
     * @param string $hydration Legacy or modern hydration.
     * @return array Exit status and separate output streams.
     */
    private function runContract($caller, $tail, $mode, $hydration): array
    {
        $arguments = array('owned', $caller, $tail, $mode, $hydration);
        $expected = $mode === 'diagnostic' ? array(array(E_USER_DEPRECATED, '/\Aowned malformed callback diagnostic\z/')) : array();
        return $this->diagnosticProcess('$argv = ' . var_export($arguments, true) . '; require ' . var_export(PATH_THIRD . 'tests/parser-contract.php', true) . ';', $expected);
    }
    /**
     * Retain clean PHP7.4 byte writes and ordinary-array fractional key values.
     *
     * @return void
     */
    public function testAcceptedByteWritesAndFractionalArrayKeys(): void
    {
        $settings = (new \ReflectionClass(\Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings::class))->newInstanceWithoutConstructor();
        $settings->fieldtype_name = 'low_seg2cat';
        foreach (array(array('text', 1, 'long', 'e', 'tlxt'), array('text', 1, 123, 'e', 't1xt'),
            array('text', 1, new NativeOffsetText(), 'e', 'tlxt'),
            array(array('A', 'B'), 1.5, 'Z', 'B', array('A', 'Z')),
            array(array('A', 'B'), 0.5, 'Z', 'A', array('Z', 'B')),
            array(array('A', 'B'), -0.5, 'Z', 'A', array('Z', 'B'))) as $case) {
            NativeOffsetText::$calls = 0;
            $database = new NativeOffsetDatabase(base64_encode(serialize($case[0])));
            ee()->db = $database;
            self::assertSame($case[3], $settings->getSetting($case[1]));
            self::assertTrue($settings->updateSetting($case[1], $case[2]));
            self::assertSame(base64_encode(serialize($case[4])), $database->written['settings']);
            self::assertSame($case[2] instanceof NativeOffsetText ? 1 : 0, NativeOffsetText::$calls);
        }
    }

}

class NativeComputedSettings extends \ArrayObject
{
    public $writes = array();

    /**
     * Supply the native getter's computed value even when offsetExists is false.
     *
     * @param mixed $key Requested setting.
     * @return mixed Stored value or computed default.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return parent::offsetExists($key) ? parent::offsetGet($key) : 'computed-default';
    }

    /**
     * Retain native setter side effects when the global helper updates one setting.
     *
     * @param mixed $key Setting key.
     * @param mixed $value Setting value.
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        $this->writes[] = array($key, $value);
        parent::offsetSet($key, $value);
    }
}


class NativeOffsetDatabase
{
    public $encoded;
    public $written;

    /**
     * Supply the native TEXT row used by the distributed settings reader.
     *
     * @param string $encoded Database carrier.
     */
    public function __construct($encoded) { $this->encoded = $encoded; }

    /**
     * Retain fluent query behavior and record exact serialized update bytes.
     *
     * @param string $method Query operation.
     * @param array $arguments Query arguments.
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if ($method === 'row') { return $this->encoded; }
        if ($method === 'num_rows') { return 1; }
        if ($method === 'update') { $this->written = $arguments[1]; return true; }
        return $this;
    }
}

class NativeOffsetText
{
    public static $calls = 0;

    /**
     * Verify first-byte assignment converts the native value exactly once.
     *
     * @return string
     */
    public function __toString(): string
    {
        self::$calls++;
        return 'long';
    }
}
