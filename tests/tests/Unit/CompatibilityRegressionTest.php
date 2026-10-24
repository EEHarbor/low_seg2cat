<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\PersistentCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

class CompatibilityRegressionTest extends TestCase
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
     * Persist the documented false initial settings passed through the EE settings controller.
     *
     * @return void
     */
    public function testPostedFalseSettingsUseTheCurrentSiteDefaults()
    {
        $_POST = array('uri_pattern' => '#news#');
        $extension = new \Low_seg2cat_ext(false);
        try {
            $extension->settings_form(false);
            self::fail('Expected the ordinary redirect after saving.');
        } catch (\Tests\Support\RedirectTermination $termination) {
            self::assertSame('cp://addons/settings/low_seg2cat', $termination->getMessage());
        }
        $updates = array_values(array_filter($this->app->db->calls, static function ($call) {
            return $call[0] === 'update';
        }));
        $stored = unserialize($updates[0][1][1]['settings']);
        self::assertSame('#news#', $stored[1]['uri_pattern']);
        self::assertSame('y', $stored[1]['set_all_segments']);
    }


    /**
     * Keep old hook/version records retryable when a required migration insert fails.
     *
     * @return void
     */
    public function testFailedMigrationHookDoesNotAdvanceVersionOrDeleteOldHook()
    {
        $extension = new \Low_seg2cat_ext(array(1 => array('uri_pattern' => '#saved#')));
        $this->app->db->calls = array();
        $this->app->db->mutationResults['insert'] = false;
        self::assertFalse($extension->update_extension('2.5.0'));
        self::assertSame(array('insert'), array_column($this->app->db->calls, 0));
        self::assertSame(array(), $this->app->alerts->calls);
    }

    /**
     * Retain all stored sites and legacy values while adding a migration hook.
     *
     * @dataProvider legacySettings
     * @param string $version Original extension version.
     * @param array $settings Original persisted settings.
     * @param array $expected Complete settings map for the new hook.
     * @return void
     */
    public function testUpgradeKeepsPersistedSettingsWithoutFormInput($version, array $settings, array $expected)
    {
        $_POST = array('all_sites' => 'n', 'uri_pattern' => 'unrelated form');
        $extension = new \Low_seg2cat_ext($settings);
        $this->app->db->calls = array();
        $extension->update_extension($version);
        $inserts = array_values(array_filter($this->app->db->calls, static function ($call) {
            return $call[0] === 'insert';
        }));
        self::assertCount(1, $inserts);
        self::assertSame('template_fetch_template', $inserts[0][1][1]['hook']);
        self::assertSame($expected, unserialize($inserts[0][1][1]['settings']));
        self::assertSame(array(), $this->app->alerts->calls);
        foreach ($this->app->db->calls as $call) {
            if ($call[0] === 'update' && isset($call[1][1]['settings'])) {
                self::assertSame($expected, unserialize($call[1][1]['settings']));
            }
        }
    }

    /**
     * Include the original flat format and complete existing multisite maps.
     *
     * @return array
     */
    public static function legacySettings()
    {
        $flat = array('all_sites' => 'y', 'uri_pattern' => '#0#', 'category_groups' => array('0', '7'), 'unknown' => false);
        $mapped = array(1 => $flat, 2 => array('uri_pattern' => '#other#', 'unknown' => 0), 0 => array('all_sites' => 'y'));
        return array('single site' => array('2.5.0', $flat, array(1 => $flat)),
            'existing MSM map' => array('2.5.0', $mapped, $mapped), 'new hook only' => array('2.6.0', $mapped, $mapped));
    }

    /**
     * Keep an ordinary key distinct from a public key containing its digest.
     *
     * @return void
     */
    public function testPersistentMemoryUsesExactlyOneLogicalKeyHash()
    {
        try {
            self::assertTrue(PersistentCache::set('entry', 'logical'));
            self::assertTrue(PersistentCache::set(sha1('entry'), 'digest'));
            self::assertSame('logical', PersistentCache::get('entry'));
            self::assertSame('digest', PersistentCache::get(sha1('entry')));
            StaticCache::clear();
            self::assertSame('logical', PersistentCache::get('entry'));
            self::assertSame('digest', PersistentCache::get(sha1('entry')));
        } finally {
            PersistentCache::delete('entry');
            PersistentCache::delete(sha1('entry'));
        }
    }

    /**
     * Avoid cross-domain aliases through real cache writers in either insertion order.
     *
     * @dataProvider cacheInsertionOrders
     * @param bool $reverse Whether to write the compound key first.
     * @return void
     */
    public function testScalarAndCompoundCachesStayIndependent($reverse)
    {
        $scalar = 'a\\:b:c';
        $compound = array('a:b', 'c');
        $entries = array(array($scalar, 'scalar'), array($compound, 'compound'));
        if ($reverse) {
            $entries = array_reverse($entries);
        }
        foreach ($entries as $entry) {
            StaticCache::set($entry[0], $entry[1]);
        }
        self::assertSame('scalar', StaticCache::get($scalar));
        self::assertSame('compound', StaticCache::get($compound));
        StaticCache::delete($compound);
        self::assertSame('scalar', StaticCache::get($scalar));
        try {
            foreach ($entries as $entry) {
                self::assertTrue(PersistentCache::set($entry[0], $entry[1]));
            }
            StaticCache::clear();
            self::assertSame('scalar', PersistentCache::get($scalar));
            self::assertSame('compound', PersistentCache::get($compound));
            self::assertTrue(PersistentCache::delete($compound));
            StaticCache::clear();
            self::assertSame('scalar', PersistentCache::get($scalar));
        } finally {
            PersistentCache::delete($scalar);
            PersistentCache::delete($compound);
        }
    }

    /**
     * Exercise both possible writer orderings.
     *
     * @return array
     */
    public static function cacheInsertionOrders()
    {
        return array(array(false), array(true));
    }
}
