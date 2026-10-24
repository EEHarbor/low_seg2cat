<?php

require_once __DIR__ . '/Support/native.php';

class LowSegNativeCallback
{
    public static $calls = 0;
    public static $mode;

    /**
     * Record native hydration and raise the requested owned callback control.
     *
     * @return void
     * @throws RuntimeException When the ordinary exception control is selected.
     */
    public static function run(): void
    {
        self::$calls++;
        if (self::$mode === 'exception') {
            throw new RuntimeException('owned malformed callback exception');
        }
        if (self::$mode === 'diagnostic') {
            @trigger_error('owned malformed callback diagnostic', E_USER_DEPRECATED);
        }
    }

    /**
     * Exercise native deferred legacy hydration.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        self::run();
    }
}

class LowSegNativeModern
{
    /**
     * Exercise native deferred modern hydration.
     *
     * @param array $data Stored object fields.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        LowSegNativeCallback::run();
    }
}

$caller = $argv[1];
$tail = $argv[2];
LowSegNativeCallback::$mode = $argv[3];
$class = $argv[4] === 'modern' ? LowSegNativeModern::class : LowSegNativeCallback::class;
$wire = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new $class()) . 'i:2;' . $tail . '}';
$probe = static function () {};
$previous = set_error_handler($probe);
restore_error_handler();
$value = null;
$error = null;
$cache = 'Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache';
try {
    if ($caller === 'decoder') {
        $value = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($wire, $valid);
    }
    if ($caller === 'global') {
        ee()->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode($wire)));
        $value = (new \Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings())->getSettings();
    }
    if ($caller === 'persistent') {
        $cache::set('owned-native-malformed', array());
        file_put_contents(PATH_CACHE . 'low_seg2cat/' . sha1('owned-native-malformed'), $wire);
        \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
        $value = $cache::get('owned-native-malformed');
    }
} catch (Throwable $exception) {
    $error = array(get_class($exception), $exception->getMessage());
} finally {
    if ($caller === 'persistent') {
        $cache::delete('owned-native-malformed');
    }
    $after = set_error_handler($probe);
    restore_error_handler();
}
echo json_encode(array('calls' => LowSegNativeCallback::$calls, 'error' => $error, 'handler_restored' => $previous === $after, 'value' => serialize($value), 'wire' => $wire)), "\n";
