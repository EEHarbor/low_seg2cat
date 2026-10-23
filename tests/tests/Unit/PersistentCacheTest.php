<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

class PersistentCacheTest extends TestCase
{
    /**
     * Reuse a successfully written request value after its disk file disappears.
     *
     * @return void
     */
    public function testWrittenValueRemainsAvailableFromRequestMemory()
    {
        $this->assertTrue(PersistentCache::set('record', array('id' => 9)));
        unlink(PATH_CACHE . 'low_seg2cat/' . sha1('record'));
        $this->assertSame(array('id' => 9), PersistentCache::get('record'));
        $this->assertSame(array(), $this->app->logger->calls);
    }

    /**
     * Return a clean cache miss when stored serialization is malformed.
     *
     * @return void
     */
    public function testMalformedStoredDataReturnsFalseWithoutDiagnostics()
    {
        $this->assertTrue(PersistentCache::set('record', array('id' => 9)));
        file_put_contents(PATH_CACHE . 'low_seg2cat/' . sha1('record'), 'not serialized data');
        StaticCache::clear();
        $this->assertFalse(PersistentCache::get('record'));
    }

    /**
     * Preserve zero-valued compound identity components in persistent file names.
     *
     * @return void
     */
    public function testZeroKeyComponentDoesNotOverwriteAnotherFile()
    {
        $this->assertTrue(PersistentCache::set(array('site', 0), array('zero-site')));
        $this->assertTrue(PersistentCache::set(array('site'), array('other-site')));
        $this->assertCount(2, glob(PATH_CACHE . 'low_seg2cat/*'));
    }

    /**
     * Clear an empty cache without disturbing unrelated in-memory entries.
     *
     * @return void
     */
    public function testEmptyCacheCanBeCleared()
    {
        StaticCache::set('unrelated', 'keep');
        $this->assertTrue(PersistentCache::clear());
        $this->assertSame(array(), glob(PATH_CACHE . 'low_seg2cat/*'));
        $this->assertSame('keep', StaticCache::get('unrelated'));
    }

    /**
     * Return a cache miss and log the actual missing hashed file location.
     *
     * @return void
     */
    public function testMissingFileCacheLogsTheMissingLocation()
    {
        $this->assertFalse(PersistentCache::get('missing'));
        $this->assertSame(array('developer', array('Low Seg2Cat: Write Cache enabled but could not find cache for: ' .
            PATH_CACHE . 'low_seg2cat/' . sha1('missing'))), $this->app->logger->calls[0]);
        $this->assertSame(array(), StaticCache::all());
    }

    /**
     * Reject an unwritable generic cache directory without attempting a write.
     *
     * @dataProvider pathCheckedOperations
     * @param string $operation
     * @return void
     */
    public function testUnwritableRootCachePathReturnsFalse($operation)
    {
        mkdir(PATH_CACHE, 0700);
        chmod(PATH_CACHE, 0500);
        try {
            $this->assertFalse($this->cacheOperation($operation));
            $this->assertSame(array('developer', array('Low Seg2Cat: Write Cache enabled but cache path not writable: ' . PATH_CACHE)),
                $this->app->logger->calls[0]);
            $this->assertSame(array(), StaticCache::all());
        } finally {
            chmod(PATH_CACHE, 0700);
        }
    }

    /**
     * Reject an unwritable add-on directory while retaining the parent cache.
     *
     * @dataProvider pathCheckedOperations
     * @param string $operation
     * @return void
     */
    public function testUnwritableModuleCachePathReturnsFalse($operation)
    {
        mkdir(PATH_CACHE, 0700);
        mkdir(PATH_CACHE . 'low_seg2cat/', 0700);
        chmod(PATH_CACHE . 'low_seg2cat/', 0500);
        try {
            $this->assertFalse($this->cacheOperation($operation));
            $this->assertSame(array('developer', array('Low Seg2Cat: Write Cache enabled but module cache path not writable: ' . PATH_CACHE . 'low_seg2cat/')),
                $this->app->logger->calls[0]);
            $this->assertSame(array(), StaticCache::all());
        } finally {
            chmod(PATH_CACHE . 'low_seg2cat/', 0700);
        }
    }

    /**
     * Leave nested directories alone when clearing this add-on's cache files.
     *
     * @return void
     */
    public function testClearLeavesDirectoriesAndUnrelatedMemoryIntact()
    {
        PersistentCache::set('record', array('id' => 1));
        $nested = PATH_CACHE . 'low_seg2cat/nested';
        mkdir($nested, 0700);
        try {
            $this->assertTrue(PersistentCache::clear());
            $this->assertTrue(is_dir($nested));
            $this->assertFalse(PersistentCache::has('record'));
        } finally {
            rmdir($nested);
        }
    }

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
     * Serve a directly preloaded processed-key cache entry without filesystem work.
     *
     * @return void
     */
    public function testDirectProcessedRequestCacheEntryAvoidsFilesystemLookup()
    {
        $processed = sha1('preloaded');
        StaticCache::put($processed, array('id' => 9), true);
        $this->assertSame(array('id' => 9), PersistentCache::get($processed));
        $this->assertFalse(is_dir(PATH_CACHE));
    }

    /**
     * Supply the public cache operations that require writable directories.
     *
     * @return array
     */
    public static function pathCheckedOperations()
    {
        return array(array('get'), array('set'), array('delete'), array('clear'));
    }

    /**
     * Invoke one operation using its supported public argument shape.
     *
     * @param string $operation
     * @return mixed
     */
    private function cacheOperation($operation)
    {
        if ($operation === 'set') {
            return PersistentCache::set('record', array('id' => 1));
        }
        if ($operation === 'clear') {
            return PersistentCache::clear();
        }
        return call_user_func(array(PersistentCache::class, $operation), 'record');
    }
}
