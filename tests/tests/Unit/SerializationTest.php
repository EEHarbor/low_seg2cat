<?php

namespace Tests;

class SerializationTest extends TestCase
{
    /**
     * Remove cache directories created by this case after its owned files are deleted.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_dir(PATH_CACHE . 'low_seg2cat/')) {
            rmdir(PATH_CACHE . 'low_seg2cat/');
        }
        if (is_dir(PATH_CACHE)) {
            rmdir(PATH_CACHE);
        }
        parent::tearDown();
    }

    /**
     * Preserve scalar types and nested containers without changing stored bytes.
     *
     * @return void
     */
    public function testStoredTypes(): void
    {
        foreach (array(null, false, true, 0, 0.0, '0', '', array(), array('null' => null, 'zero' => 0), (object) array('name' => 'value')) as $value) {
            $stored = serialize($value);
            self::assertSame($stored, serialize(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored, $valid)));
            self::assertTrue($valid);
        }
    }

    /**
     * Read legacy escaped strings while leaving opaque ordinary string bytes intact.
     *
     * @return void
     */
    public function testLegacyEscapedStringsAndReferences(): void
    {
        $value = 'A';
        $original = array(&$value, &$value, 'literal' => 'S:1:"x";');
        $stored = str_replace('s:1:"A";', 'S:1:"\41";', serialize($original));
        $decoded = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored, $valid);
        self::assertTrue($valid);
        self::assertSame(serialize($original), serialize($decoded));
        $decoded[0] = 'changed';
        self::assertSame('changed', $decoded[1]);
        self::assertSame('S:1:"x";', $decoded['literal']);
        self::assertSame("\0\xffA", \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('S:3:"\00\ffA";'));

        $object = (object) array('name' => 'A', 'literal' => 'S:1:"x";');
        self::assertSame(serialize($object), serialize(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(str_replace('s:1:"A";', 'S:1:"\41";', serialize($object)))));
    }

    /**
     * Keep custom serialized payloads opaque and preserve object identity references.
     *
     * @return void
     */
    public function testOpaquePayloadAndObjectReferences(): void
    {
        $class = OpaqueSerializedFixture::class;
        $payload = 'S:1:"\41";}opaque';
        $stored = 'C:' . strlen($class) . ':"' . $class . '":' . strlen($payload) . ':{' . $payload . '}';
        self::assertSame($payload, \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored)->payload);
        $object = (object) array('name' => 'A');
        $stored = str_replace('s:1:"A";', 'S:1:"\41";', serialize(array($object, $object)));
        $decoded = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored);
        self::assertSame($decoded[0], $decoded[1]);
        self::assertSame('A', $decoded[0]->name);
    }

    /**
     * Restore the caller's handler when an ordinary object callback throws.
     *
     * @return void
     */
    public function testCallbackExceptionRestoresTheHandler(): void
    {
        $probe = static function () {};
        $before = set_error_handler($probe);
        restore_error_handler();
        try {
            \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(serialize(new ThrowingSerializedFixture()));
            self::fail('The object callback exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('owned ordinary callback exception', $exception->getMessage());
        }
        $after = set_error_handler($probe);
        restore_error_handler();
        self::assertSame($before, $after);
    }

    /**
     * Distinguish invalid storage from a valid serialized false value.
     *
     * @return void
     */
    public function testMalformedStorage(): void
    {
        foreach (array('', 'garbage', 'a:1:{s:3:"key";s:4:"bad";}', 'S:1:"\zz";', 'S:2:"A";') as $stored) {
            self::assertFalse(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($stored, $valid));
            self::assertFalse($valid);
        }
        self::assertFalse(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('b:0;', $valid));
        self::assertTrue($valid);
    }

    /**
     * Preserve the original parser's valid-prefix tolerance and accepted-value flag.
     *
     * @return void
     */
    public function testTrailingBytesPreserveTheParsedPrefix(): void
    {
        foreach (array(null, false, 0, 'A', array('name' => 'A')) as $value) {
            self::assertSame($value, \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(serialize($value) . 'trailing', $valid));
            self::assertTrue($valid);
        }
        self::assertSame(array('name' => 'A'), \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode('a:1:{s:4:"name";S:1:"\\41";}trailing', $valid));
        self::assertTrue($valid);
        self::assertSame('S:1:"x";', \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode(serialize('S:1:"x";') . 'trailing', $valid));
        self::assertTrue($valid);
    }


    /**
     * Retain valid prefix and escaped-string storage through the distributed cache/settings callers.
     *
     * @return void
     */
    public function testActualStoredDataCallers(): void
    {
        foreach (array('a:1:{s:4:"name";S:1:"\41";}', 'a:1:{s:4:"name";s:1:"A";}trailing') as $stored) {
            $cache = '\\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\PersistentCache';
            try {
                self::assertTrue($cache::set('owned-legacy', array()));
                file_put_contents(PATH_CACHE . 'low_seg2cat/' . sha1('owned-legacy'), $stored);
                \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
                self::assertSame(array('name' => 'A'), $cache::get('owned-legacy'));
                $this->app->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode($stored)));
                self::assertSame(array('name' => 'A'), (new \Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings())->getSettings());
            } finally {
                $cache::delete('owned-legacy');
            }
        }
    }

    /**
     * Keep native missing-class warnings and caught object diagnostics observable.
     *
     * @dataProvider unexpectedCallbacks
     * @param string $code Native callback setup and decode statements.
     * @param string $message Expected durable diagnostic text.
     * @return void
     */
    public function testUnexpectedCallbacksRemainStrict($code, $message): void
    {
        $prefix = '$probe = static function () {}; $before = set_error_handler($probe); restore_error_handler(); ';
        $suffix = '$after = set_error_handler($probe); restore_error_handler(); if ($before !== $after) { throw new RuntimeException("Handler changed"); } echo "restored";';
        $result = \Tests\Support\Process::probe($prefix . $code . $suffix);
        self::assertNotSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('restored', $result['stdout']);
        self::assertStringContainsString('LOW_SEG2CAT_DIAGNOSTIC', $result['stderr']);
        self::assertStringContainsString($message, $result['stderr']);
    }

    /**
     * Exercise a native callback warning and a user callback diagnostic after catches.
     *
     * @return array
     */
    public static function unexpectedCallbacks(): array
    {
        return array('missing class' => array('function owned_missing_class_callback($class) { @trigger_error("owned missing class callback warning", E_USER_WARNING); } ini_set("unserialize_callback_func", "owned_missing_class_callback"); try { \\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\SerializedData::decode(\'O:24:"OwnedMissingRuntimeClass":0:{}\'); } catch (ErrorException $error) {} ', 'owned missing class callback warning'),
            'wakeup' => array('class OwnedWakeupDiagnostic { public function __wakeup() { @trigger_error("owned parser callback diagnostic", E_USER_DEPRECATED); } } try { \\Low\\Seg2Cat\\FluxCapacitor\\Conduit\\SerializedData::decode(serialize(new OwnedWakeupDiagnostic())); } catch (ErrorException $error) {} ', 'owned parser callback diagnostic'));
    }
}

class ThrowingSerializedFixture
{
    /**
     * Throw an authored application exception rather than a PHP diagnostic.
     *
     * @return void
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('owned ordinary callback exception');
    }
}

class OpaqueSerializedFixture implements \Serializable
{
    public $payload;

    /**
     * Return the legacy custom payload without parsing its contents.
     *
     * @return string
     */
    public function serialize(): string
    {
        return $this->payload;
    }

    /**
     * Retain opaque legacy bytes supplied by the native C token.
     *
     * @param string $data Custom payload.
     * @return void
     */
    public function unserialize($data): void
    {
        $this->payload = $data;
    }

    /**
     * Supply the modern interface alongside legacy custom serialization.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return array('payload' => $this->payload);
    }

    /**
     * Restore modern serialized fields without replacing legacy behavior.
     *
     * @param array $data Serialized fields.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->payload = $data['payload'];
    }
}
