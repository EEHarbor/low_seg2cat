<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

class PersistentCacheTest extends TestCase
{
    /**
     * Reset cache paths to the unique process directory before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        foreach (array('cache_path' => PATH_CACHE, 'module_cache_path' => PATH_CACHE . 'low_seg2cat/') as $property => $path) {
            $reflection = new \ReflectionProperty(PersistentCache::class, $property);
            if (PHP_VERSION_ID < 80100) {
                $reflection->setAccessible(true);
            }
            $reflection->setValue(null, $path);
        }
    }

    /**
     * Remove only files created inside this process's test cache directory.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_dir(PATH_CACHE . 'low_seg2cat/')) {
            foreach (glob(PATH_CACHE . 'low_seg2cat/*') as $file) {
                unlink($file);
            }
            rmdir(PATH_CACHE . 'low_seg2cat/');
        }
        if (is_dir(PATH_CACHE)) {
            rmdir(PATH_CACHE);
        }
        parent::tearDown();
    }

    /**
     * Round-trip structured values through the actual filesystem and serialization.
     *
     * @return void
     */
    public function testFileCacheStoresLoadsAndDeletesValues()
    {
        $value = array('ids' => array(1, 2), 'nullable' => null, 'flag' => false);
        $this->assertFalse(PersistentCache::has('record'));
        $this->assertTrue(PersistentCache::set('record', $value));
        $this->assertTrue(PersistentCache::has('record'));
        $this->assertSame(serialize($value), file_get_contents(PATH_CACHE . 'low_seg2cat/' . sha1('record')));
        StaticCache::clear();
        $this->assertSame($value, PersistentCache::get('record'));
        $this->assertTrue(PersistentCache::delete('record'));
        $this->assertFalse(PersistentCache::has('record'));
        $this->assertTrue(PersistentCache::delete('already-missing'));
    }

    /**
     * Preserve unrelated in-memory keys when clearing persistent records.
     *
     * @return void
     */
    public function testClearRemovesOnlyPersistentCacheFilesAndMatchingMemoryKeys()
    {
        PersistentCache::set(array('site', 1), (object) array('id' => 3));
        PersistentCache::set('other', 'value');
        StaticCache::set('unrelated', 'keep');
        $this->assertTrue(PersistentCache::clear());
        $this->assertSame(array(), glob(PATH_CACHE . 'low_seg2cat/*'));
        $this->assertSame('keep', StaticCache::get('unrelated'));
        $this->assertFalse(StaticCache::has(sha1('site:1'), true));
    }

    /**
     * Return an already available in-memory value without filesystem work.
     *
     * @return void
     */
    public function testExistingStaticValueAvoidsFilesystemLookup()
    {
        StaticCache::put('preloaded', array('id' => 9), true);
        $this->assertSame(array('id' => 9), PersistentCache::get('preloaded'));
        $this->assertFalse(is_dir(PATH_CACHE));
    }
}
