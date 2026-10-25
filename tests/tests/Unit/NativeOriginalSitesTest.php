<?php

namespace Tests;

class NativeOriginalSitesTest extends TestCase
{
    /**
     * Keep an original namespace hook outside the native directory failure boundary.
     *
     * @return void
     */
    public function testNamespaceMkdirRetainsItsInstalledHandler(): void
    {
        $code = <<<'CODE'
$GLOBALS['installed_directory_handler'] = static function () { return true; };
eval('namespace Low\Seg2Cat\FluxCapacitor\Conduit; function mkdir(&$path, $mode) { \set_error_handler($GLOBALS["installed_directory_handler"], E_USER_NOTICE); return \mkdir($path, $mode); }');
$value = \Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::set('native-local-site', 'stored');
$handler = set_error_handler(static function () {}); restore_error_handler();
echo json_encode(array($value, $handler === $GLOBALS['installed_directory_handler']));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(true, true), json_decode($result['stdout'], true));
    }

    /**
     * Keep independent native constant selection at both original mkdir expressions.
     *
     * @return void
     */
    public function testMkdirConstantSelectionRemainsIndependent(): void
    {
        $code = <<<'CODE'
$GLOBALS['native_modes'] = array();
eval('namespace Low\Seg2Cat\FluxCapacitor\Conduit; function mkdir(&$path, $mode) { $GLOBALS["native_modes"][] = $mode; if (count($GLOBALS["native_modes"]) === 1) { \define(__NAMESPACE__ . "\\DIR_WRITE_MODE", 0777); } return \mkdir($path, $mode); }');
$value = \Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::set('native-sites', 'stored');
echo json_encode(array($value, $GLOBALS['native_modes'], file_get_contents(PATH_CACHE . 'low_seg2cat/' . sha1('native-sites'))));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(true, array(0700, 0777), 's:6:"stored";'), json_decode($result['stdout'], true));
    }

    /**
     * Preserve the original reporting scope of an actual computed settings offset.
     *
     * @return void
     */
    public function testComputedArrayAccessRetainsOriginalReportingScope(): void
    {
        $code = <<<'CODE'
class NativeReportingAccess implements \ArrayAccess {
    public function offsetExists($offset): bool { return true; }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return error_reporting(); }
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}
ee()->db->rowsByTable['fieldtypes'] = array(array('settings' => base64_encode(serialize(new NativeReportingAccess()))));
echo json_encode((new \Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings())->getSetting('computed'));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(PHP_VERSION_ID < 80000 ? 0 : 4437, json_decode($result['stdout'], true));
    }

    /**
     * Allow a successful virtual cache producer without inventing host search bits.
     *
     * @return void
     */
    public function testWritableVirtualStorageRetainsNativeProducer(): void
    {
        $code = <<<'CODE'
class NativeWritableStorage {
    public $context;
    public static $directories = array();
    public static $files = array();
    private $path;
    public function url_stat($path, $flags) {
        $directory = isset(self::$directories[$path]);
        if (!$directory && !isset(self::$files[$path])) { return false; }
        $mode = $directory ? 040666 : 0100666;
        $size = $directory ? 0 : strlen(self::$files[$path]);
        return array(0, 0, $mode, 1, getmyuid(), getmygid(), 0, $size, 0, 0, 0, -1, -1,
            'mode' => $mode, 'uid' => getmyuid(), 'gid' => getmygid(), 'size' => $size);
    }
    public function mkdir($path, $mode, $options) { self::$directories[$path] = true; return true; }
    public function stream_metadata($path, $option, $value) { return true; }
    public function stream_open($path, $mode, $options, &$opened) { $this->path = $path; self::$files[$path] = ''; return true; }
    public function stream_write($bytes) { self::$files[$this->path] .= $bytes; return strlen($bytes); }
    public function stream_close() {}
    public function stream_lock($operation) { return true; }
}
stream_wrapper_register('nativecache', NativeWritableStorage::class);
$class = new ReflectionClass(\Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::class);
foreach (array('cache_path' => 'nativecache://root/', 'module_cache_path' => 'nativecache://root/low_seg2cat/') as $name => $path) {
    $property = $class->getProperty($name);
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $property->setValue(null, $path);
}
$value = \Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::set('native-stream', 'stored');
echo json_encode(array($value, count(NativeWritableStorage::$directories), array_values(NativeWritableStorage::$files)));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(true, 2, array('s:6:"stored";')), json_decode($result['stdout'], true));
    }
    /**
     * Avoid new conversions of opaque paths assigned by the original by-reference hook.
     *
     * @return void
     */
    public function testOpaqueCachePathRetainsOriginalConversions(): void
    {
        $code = <<<'CODE'
class NativeOpaquePath {
    public static $conversions = 0;
    private $path;
    public function __construct($path) { $this->path = $path; }
    public function __toString() { self::$conversions++; return $this->path; }
}
eval('namespace Low\Seg2Cat\FluxCapacitor\Conduit; function mkdir(&$path, $mode) { $created = \mkdir($path, $mode); $path = new \NativeOpaquePath($path); return $created; }');
$value = \Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::set('opaque-path', 'stored');
echo json_encode(array($value, NativeOpaquePath::$conversions,
    file_get_contents(PATH_CACHE . 'low_seg2cat/' . sha1('opaque-path'))));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(true, 6, 's:6:"stored";'), json_decode($result['stdout'], true));
    }

    /**
     * Keep new native presence checks independent of unrelated namespace functions.
     *
     * @return void
     */
    public function testNativeCachePresenceKeepsItsGlobalBinding(): void
    {
        $code = <<<'CODE'
eval('namespace Low\Seg2Cat\FluxCapacitor\Conduit; function array_key_exists($key, $values) { return false; }');
\Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::set('kept', 7);
echo json_encode(array(\Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::get('kept'),
    \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::has('kept')));
CODE;
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(7, true), json_decode($result['stdout'], true));
    }

    /**
     * Supply both original directory search guards with native file-URI operands.
     *
     * @return array
     */
    public static function fileDirectoryGuards(): array
    {
        return array('generic' => array('generic'), 'module' => array('module'));
    }

    /**
     * Retain quiet search-permission fallback for native file stream directory paths.
     *
     * @dataProvider fileDirectoryGuards
     * @param string $blocked Which existing directory lacks search permission.
     * @return void
     */
    public function testNativeFileDirectoriesRetainSearchGuard($blocked): void
    {
        $code = <<<'CODE'
$directory = sys_get_temp_dir() . '/file-guard';
mkdir($directory, 0700);
mkdir($directory . '/low_seg2cat', 0700);
$reflection = new ReflectionClass(\Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::class);
foreach (array('cache_path' => 'file://' . $directory . '/', 'module_cache_path' => 'file://' . $directory . '/low_seg2cat/') as $name => $path) {
    $property = $reflection->getProperty($name);
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $property->setValue(null, $path);
}
$blocked = BLOCKED_DIRECTORY === 'generic' ? $directory : $directory . '/low_seg2cat';
chmod($blocked, 0200);
try {
    $value = \Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache::set('file-guard', 'stored');
    echo json_encode(array($value, count(ee()->logger->calls)));
} finally {
    chmod($blocked, 0700);
}
CODE;
        $code = str_replace('BLOCKED_DIRECTORY', var_export($blocked, true), $code);
        $result = \Tests\Support\Process::probe($code);
        self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(array(false, 1), json_decode($result['stdout'], true));
    }

}
