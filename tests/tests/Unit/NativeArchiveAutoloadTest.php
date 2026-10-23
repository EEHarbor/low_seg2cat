<?php

namespace Tests;

use Tests\Support\Process;

class NativeArchiveAutoloadTest extends TestCase
{
    /**
     * Resolve archive members independently of an installation's working directory.
     *
     * @return array
     */
    public static function workingDirectories()
    {
        return array('repository directory' => array(false), 'owned empty directory' => array(true));
    }

    /**
     * Load only actual bundled classes through the original archive callback.
     *
     * @dataProvider workingDirectories
     * @param bool $ownedWorkingDirectory
     * @return void
     */
    public function testOriginalArchiveAutoloaderSupportsColdWarmAndMissingClasses($ownedWorkingDirectory)
    {
        $code = '$ownedWorkingDirectory = ' . var_export($ownedWorkingDirectory, true) . '; ';
        $code .= <<<'CODE'
$removed = 0;
foreach (spl_autoload_functions() as $loader) {
    if ($loader instanceof Closure && (new ReflectionFunction($loader))->getFileName() === LOW_SEG2CAT_ROOT . '/low_seg2cat/autoload.php') {
        $removed += spl_autoload_unregister($loader) ? 1 : 0;
    }
}
if ($ownedWorkingDirectory) {
    $directory = sys_get_temp_dir() . '/empty-working-directory';
    mkdir($directory, 0700);
    chdir($directory);
}
$archive = LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
require_once $archive;
$index = 'phar://' . $archive . '/index.php';
$callbacks = array();
foreach (spl_autoload_functions() as $loader) {
    if ($loader instanceof Closure && (new ReflectionFunction($loader))->getFileName() === $index) {
        $callbacks[] = $loader;
    }
}
$foreign = class_exists('FixtureVendor\AbsentClass');
$missing = class_exists('Low\Seg2Cat\FluxCapacitor\AbsentClass');
$classes = array();
foreach (array('StaticCache', 'PersistentCache') as $short) {
    $name = sprintf('Low\Seg2Cat\FluxCapacitor\Conduit\%s', $short);
    $before = class_exists($name, false);
    $loaded = class_exists($name);
    $reflection = $loaded ? new ReflectionClass($name) : null;
    $classes[$short] = array('before' => $before, 'loaded' => $loaded,
        'name' => $reflection ? $reflection->getName() : null,
        'file' => $reflection ? $reflection->getFileName() : null,
        'hash' => $reflection ? hash_file('sha256', $reflection->getFileName()) : null,
        'warm' => class_exists($name));
}
$loaderCount = count(spl_autoload_functions());
require_once $archive;
echo json_encode(array('removed' => $removed, 'archive_callbacks' => count($callbacks),
    'foreign' => $foreign, 'missing' => $missing, 'classes' => $classes,
    'stable_callbacks' => count(spl_autoload_functions()) === $loaderCount,
    'cwd' => getcwd(), 'temporary' => sys_get_temp_dir(),
    'binary' => PHP_BINARY, 'php' => PHP_VERSION, 'mask' => error_reporting(), 'e_all' => E_ALL), JSON_THROW_ON_ERROR);
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $data = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $data['removed']);
        $this->assertSame(1, $data['archive_callbacks']);
        $this->assertFalse($data['foreign']);
        $this->assertFalse($data['missing']);
        $this->assertTrue($data['stable_callbacks']);
        $this->assertSame(PHP_BINARY, $data['binary']);
        $this->assertSame(PHP_VERSION, $data['php']);
        $this->assertSame($data['e_all'], $data['mask'] & $data['e_all']);
        $this->assertSame(array('StaticCache', 'PersistentCache'), array_keys($data['classes']));
        $inventory = json_decode(file_get_contents(LOW_SEG2CAT_ROOT . '/tests/SOURCE_INVENTORY.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($data['classes'] as $short => $class) {
            $unit = 'low_seg2cat/low_seg2cat.phar::FluxCapacitor/Conduit/' . $short . '.php';
            $this->assertFalse($class['before']);
            $this->assertTrue($class['loaded']);
            $this->assertTrue($class['warm']);
            $this->assertSame(sprintf('Low\Seg2Cat\FluxCapacitor\Conduit\%s', $short), $class['name']);
            $this->assertSame('phar://' . LOW_SEG2CAT_ROOT . '/' . str_replace('::', '/', $unit), $class['file']);
            $this->assertSame($inventory[$unit]['sha256'], $class['hash']);
        }
        $this->assertFalse(is_dir($data['temporary']));
        if ($ownedWorkingDirectory) {
            $this->assertSame($data['temporary'] . '/empty-working-directory', $data['cwd']);
            return;
        }
        $this->assertSame(getcwd(), $data['cwd']);
    }
}
