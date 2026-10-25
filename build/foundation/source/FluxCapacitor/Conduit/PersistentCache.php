<?php

namespace Low\Seg2Cat\FluxCapacitor\Conduit;

use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

// Based on CI DB caching function
class PersistentCache
{
    private static $cache_path = PATH_CACHE;
    private static $module_cache_path = PATH_CACHE . 'low_seg2cat/';

    /**
     * Retrieve the data stored in the cache.
     * @param  string|array  $key  Either a string or array of items
     * @return array|object        Unserialized data from cache
     */
    public static function get($key)
    {
        $key = self::createKey($key);
        if (StaticCache::has($key, true)) {
            return StaticCache::get($key, true);
        }

        if (! self::checkPath()) {
            return false;
        }

        ee()->load->helper('file');

        if (false === ($cache_data = read_file(self::$module_cache_path . $key))) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Seg2Cat: Write Cache enabled but could not find cache for: ' . self::$module_cache_path . $key);
            return false;
        }

        // Store the cache data in the StaticCache so we don't have
        // to read it off the disk again during this execution loop.
        $cache_data = self::serveCacheData($cache_data);
        StaticCache::set($key, $cache_data, true);

        return $cache_data;
    }

     /**
     * Set the data to be stored in the cache.
     * @param  string|array  $key  Either a string or array of items
     * @return boolean             Indicates whether the data was able to be saved
     */
    public static function set($key, $value)
    {
        if (!self::checkPath()) {
            return false;
        }

        ee()->load->helper('file');

        $key = self::createKey($key);
        $cache_data = self::prepCacheData($value);

        if (write_file(self::$module_cache_path . $key, $cache_data) === false) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Seg2Cat: Write Cache enabled but could not write cache file: ' . self::$module_cache_path . $key);

            return false;
        }

        // Try to change the folder permissions to EE's default write mode.
        @chmod(self::$module_cache_path . $key, FILE_WRITE_MODE);

        // Store the cache data in the StaticCache so we don't have
        // to read it off the disk again during this execution loop.
        // We use $value here because we don't need it in file format.
        StaticCache::set($key, $value, true);

        return true;
    }

    /**
     * Delete the cached file for a specific key for this module.
     * @return bool  Indicates if the key was successfully deleted.
     */
    public static function delete($key)
    {
        if (!self::checkPath()) {
            return false;
        }

        $key = self::createKey($key);

        if (is_file(self::$module_cache_path . $key)) {
            @unlink(self::$module_cache_path . $key);
        }

        // Remove any StaticCache we have for this key as well.
        StaticCache::delete($key, true);

        return true;
    }

    /**
     * Find out if a specific key exists in the file cache.
     * @param  string|array  $key            Either a string or an array if items
     * @param  boolean       $key_processed  Whether the key has already be SHA1'd
     * @return boolean                       Whether the key exists in the cache.
     */
    public static function has($key, $key_processed = false)
    {
        $key = $key_processed ? $key : self::createKey($key);
        return is_file(self::$module_cache_path . $key);
    }

    /**
     * Delete all the cached files that this module may have created.
     * @return bool  Indicates if the cache path existed and we attempted to clear it.
     */
    public static function clear()
    {
        if (!self::checkPath()) {
            return false;
        }

        foreach (glob(self::$module_cache_path . '*') as $key) {
            if (is_file($key)) {
                @unlink($key);
            }

            // Remove any StaticCache we have for this key as well.
            // We don't want to clear the StaticCache globally as
            // we may only be looking to clear the persistent cache
            // and leave the other things in the StaticCache alone.
            StaticCache::delete(pathinfo($key, PATHINFO_FILENAME), true);
        }

        return true;
    }


    /************************************************************
    PRIVATE FUNCTIONS
    ************************************************************/

    /**
     * Retain ordinary hashes and separate ambiguous compound key identities.
     *
     * @param string|array $key Public key or ordered key pieces.
     * @return string
     */
    private static function createKey($key)
    {
        if (!is_array($key)) {
            $key = array($key);
        }

        require_once __DIR__ . '/SerializedData.php';
        $localHash = SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'sha1');
        $localJoin = SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'implode');
        $localFilter = SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'array_filter');
        $keep = static function ($part) { return $part === 0 || $part === '0' || (bool) $part; };
        if ($localHash || $localJoin || $localFilter) {
            if ($localHash) {
                return sha1($localJoin
                    ? implode(':', $localFilter ? array_filter($key) : \array_filter($key, $keep))
                    : \implode(':', $localFilter ? array_filter($key) : \array_filter($key, $keep)));
            }

            return \sha1($localJoin
                ? implode(':', $localFilter ? array_filter($key) : \array_filter($key, $keep))
                : \implode(':', $localFilter ? array_filter($key) : \array_filter($key, $keep)));
        }

        $key = \array_filter($key, $keep);
        if (\count($key) > 1) {
            $parts = \array_map('strval', \array_values($key));
            foreach ($parts as $part) {
                if (\strpbrk($part, ':\\') !== false) {
                    return 'compound-' . \sha1(\serialize($parts));
                }
            }
            $key = $parts;
        }

        return \sha1(\implode(':', $key));
    }

    /**
     * Prep the data to be stored in the cache. Making this it's own
     * method so we can add to it if needed.
     * @param  array|object  $value Data to store in cache
     * @return string        Serialized string of values
     */
    private static function prepCacheData($value)
    {
        return serialize($value);
    }

    /**
     * Decode cached values with the selected malformed-storage miss contract.
     *
     * @param string $value Stored cache bytes.
     * @return mixed
     */
    private static function serveCacheData($value)
    {
        require_once __DIR__ . '/SerializedData.php';
        if (SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'unserialize')) {
            return unserialize($value);
        }

        $valid = null;
        return \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($value, $valid, true);
    }

    /**
     * Retain complete original mkdir expressions and quiet native fallback failures.
     *
     * @return bool Whether both native directories are available.
     * @throws \Throwable When an unexpected native or callback diagnostic handler fails.
     */
    private static function checkPath()
    {
        $mkdir_line = 0;
        $previous = null;
        $mkdirHandler = static function ($severity, $message, $file, $line) use (&$mkdir_line, &$previous) {
            if ($severity === \E_WARNING && $file === __FILE__ && $line === $mkdir_line &&
                \preg_match('/\Amkdir\(\): (?:Permission denied|File exists|Not a directory|No such file or directory|Too many levels of symbolic links|Read-only file system|File name too long)\z/', $message)) {
                return true;
            }
            if ($previous !== null) {
                return $previous(...\func_get_args());
            }
            return false;
        };

        // Keep both original call expressions: native function, constant and reference caches differ by site.
        // Add a trailing slash to the path if needed
        self::$cache_path = preg_replace("/(.+?)\/*$/", "\\1/", self::$cache_path);

        // If the generic cache folder doesn't exist, try to create it.
        if (! @is_dir(self::$cache_path)) {
            require_once __DIR__ . '/SerializedData.php';
            $nativeMkdir = !SerializedData::hasLocal(__METHOD__ . '#generic', __NAMESPACE__, 'mkdir');
            if ($nativeMkdir) {
                $previous = \set_error_handler($mkdirHandler, \E_ALL);
            }
            try {
                $mkdir_line = __LINE__ + 1;
                $created = @mkdir(self::$cache_path, DIR_WRITE_MODE);
            } finally {
                if ($nativeMkdir) {
                    \restore_error_handler();
                }
            }
            if (!$created) {
                ee()->load->library('logger');
                ee()->logger->developer('Low Seg2Cat: Write Cache enabled but could not create cache folder: ' . self::$cache_path);
                return false;
            }

            // Try to change the folder permissions to EE's default write mode.
            @chmod(self::$cache_path, DIR_WRITE_MODE);
        }

        // Make sure the generic cache folder is writable.
        if (!is_writable(self::$cache_path) || (\is_string(self::$cache_path) && \DIRECTORY_SEPARATOR === '/' && (\realpath(self::$cache_path) !== false || \strncasecmp(self::$cache_path, 'file://', 7) === 0) && !\is_executable(self::$cache_path))) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Seg2Cat: Write Cache enabled but cache path not writable: ' . self::$cache_path);
            return false;
        }

        // If the module-specific cache folder doesn't exist, try to create it.
        if (!@is_dir(self::$module_cache_path)) {
            require_once __DIR__ . '/SerializedData.php';
            $nativeMkdir = !SerializedData::hasLocal(__METHOD__ . '#module', __NAMESPACE__, 'mkdir');
            if ($nativeMkdir) {
                $previous = \set_error_handler($mkdirHandler, \E_ALL);
            }
            try {
                $mkdir_line = __LINE__ + 1;
                $created = @mkdir(self::$module_cache_path, DIR_WRITE_MODE);
            } finally {
                if ($nativeMkdir) {
                    \restore_error_handler();
                }
            }
            if (!$created) {
                ee()->load->library('logger');
                ee()->logger->developer('Low Seg2Cat: Write Cache enabled but could not create module cache folder: ' . self::$module_cache_path);
                return false;
            }

            // Try to change the folder permissions to EE's default write mode.
            @chmod(self::$module_cache_path, DIR_WRITE_MODE);
        }

        // Make sure the module-specific cache folder is writable.
        if (!is_writable(self::$module_cache_path) || (\is_string(self::$module_cache_path) && \DIRECTORY_SEPARATOR === '/' && (\realpath(self::$module_cache_path) !== false || \strncasecmp(self::$module_cache_path, 'file://', 7) === 0) && !\is_executable(self::$module_cache_path))) {
            // If the path is wrong we'll turn off caching
            ee()->load->library('logger');
            ee()->logger->developer('Low Seg2Cat: Write Cache enabled but module cache path not writable: ' . self::$module_cache_path);
            return false;
        }

        return true;
    }
}
