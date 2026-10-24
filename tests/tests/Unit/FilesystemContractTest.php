<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

class FilesystemContractTest extends TestCase
{
    /**
     * Exercise native creation, trusted aliases and blocked parent boundaries.
     *
     * @return array
     */
    public static function cachePaths(): array
    {
        $cases = array();
        foreach (array(false, true) as $module) {
            foreach (array('normal', 'missing-parent', 'readonly-parent', 'no-search-parent', 'absolute-alias', 'absolute-trailing-alias', 'relative-alias', 'two-hop-alias', 'two-hop-trailing-alias', 'cycle-alias', 'missing-target-parent', 'readonly-target-parent', 'no-search-target-parent', 'existing-no-search') as $shape) {
                $cases[] = array($module, $shape);
            }
        }
        return $cases;
    }

    /**
     * Retain actual cache roundtrips or the original false-and-log failure contract.
     *
     * @dataProvider cachePaths
     * @param bool $module Change the module path rather than the generic cache path.
     * @param string $shape Filesystem boundary under test.
     * @return void
     */
    public function testCacheCreationAndAliases($module, $shape): void
    {
        $base = sys_get_temp_dir() . '/low_seg2cat-cache-contract-' . bin2hex(random_bytes(8));
        mkdir($base, 0700);
        mkdir($base . '/parent', 0700);
        mkdir($base . '/target', 0700);
        $directory = $base . '/parent/cache';
        $cache = $module ? $base . '/generic/' : $directory . '/';
        if ($module) {
            mkdir($cache, 0700);
        }
        $paths = array('cache_path' => $cache, 'module_cache_path' => $module ? $directory . '/' : $directory . '/low_seg2cat/');
        $original = array();
        foreach ($paths as $name => $value) {
            $property = new \ReflectionProperty(PersistentCache::class, $name);
            if (PHP_VERSION_ID < 80100) {
                $property->setAccessible(true);
            }
            $original[$name] = $property->getValue();
            $property->setValue(null, $value);
        }
        if (strpos($shape, 'alias') !== false || strpos($shape, 'target-parent') !== false) {
            $target = $base . '/target/cache';
            if ($shape === 'absolute-trailing-alias') {
                $target .= '/';
            }
            if ($shape === 'relative-alias') {
                $target = '../target/cache';
            }
            if ($shape === 'two-hop-alias' || $shape === 'two-hop-trailing-alias') {
                symlink('../target/cache', $base . '/parent/inner');
                $target = $shape === 'two-hop-alias' ? 'inner' : 'inner/';
            }
            if ($shape === 'cycle-alias') {
                $target = 'cache';
            }
            if ($shape === 'missing-target-parent') {
                $target = $base . '/absent/cache';
            }
            symlink($target, $directory);
        }
        if ($shape === 'missing-parent') {
            rmdir($base . '/parent');
        }
        if ($shape === 'readonly-parent' || $shape === 'no-search-parent') {
            chmod($base . '/parent', $shape === 'readonly-parent' ? 0555 : 0600);
        }
        if ($shape === 'readonly-target-parent' || $shape === 'no-search-target-parent') {
            chmod($base . '/target', $shape === 'readonly-target-parent' ? 0555 : 0600);
        }
        if ($shape === 'existing-no-search') {
            mkdir($directory, 0200);
        }
        StaticCache::clear();
        try {
            $accepted = in_array($shape, array('normal', 'absolute-alias', 'absolute-trailing-alias', 'relative-alias'), true);
            self::assertSame($accepted, PersistentCache::set('owned-path', array('value' => 0)));
            if ($accepted) {
                StaticCache::clear();
                self::assertSame(array('value' => 0), PersistentCache::get('owned-path'));
                self::assertTrue(PersistentCache::delete('owned-path'));
                self::assertCount(0, ee()->logger->calls);
            }
            if (!$accepted) {
                self::assertCount(1, ee()->logger->calls);
                self::assertStringContainsString('cache', ee()->logger->calls[0][1][0]);
            }
        } finally {
            foreach (array('parent', 'target', 'parent/cache') as $path) {
                if (!is_link($base . '/' . $path) && is_dir($base . '/' . $path)) {
                    chmod($base . '/' . $path, 0700);
                }
            }
            foreach ($original as $name => $value) {
                $property = new \ReflectionProperty(PersistentCache::class, $name);
                if (PHP_VERSION_ID < 80100) {
                    $property->setAccessible(true);
                }
                $property->setValue(null, $value);
            }
            StaticCache::clear();
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                    continue;
                }
                unlink($file->getPathname());
            }
            rmdir($base);
        }
    }

    /**
     * Trim only native separators before checking cleanup root links.
     *
     * @return void
     */
    public function testDirectCleanupPreservesLinkTargetsAndBackslashNames(): void
    {
        require_once PATH_THIRD . 'low_seg2cat/low_seg2cat.phar';
        $base = sys_get_temp_dir() . '/low_seg2cat-cleanup-contract-' . bin2hex(random_bytes(8));
        mkdir($base . '/outside', 0700, true);
        file_put_contents($base . '/outside/sentinel', 'outside');
        try {
            foreach (array('', '/', '//') as $suffix) {
                foreach (array('outside', 'missing') as $target) {
                    symlink($base . '/' . $target, $base . '/link');
                    \Extract_low_seg2cat::_removeTmpFiles($base . '/link' . $suffix, getcwd());
                    self::assertFalse(is_link($base . '/link'));
                    self::assertSame('outside', file_get_contents($base . '/outside/sentinel'));
                }
            }
            $directory = $base . '/owned\\';
            mkdir($directory, 0700);
            file_put_contents($directory . '/.hidden', 'stale');
            \Extract_low_seg2cat::_removeTmpFiles($directory . '/', getcwd());
            self::assertDirectoryDoesNotExist($directory);
        } finally {
            unlink($base . '/outside/sentinel');
            rmdir($base . '/outside');
            rmdir($base);
        }
    }
}
