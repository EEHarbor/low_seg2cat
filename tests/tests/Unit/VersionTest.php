<?php

namespace Tests;

use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;
use Low\Seg2Cat\FluxCapacitor\Conduit\Version;

class VersionTest extends TestCase
{
    /**
     * Preserve unrelated footer markup and reject a nonmatching development flag.
     *
     * @return void
     */
    public function testCheckPreservesOtherFooterMarkupAndRequiresTheDevelopmentFlagValue()
    {
        $originalGet = $_GET;
        $_GET['ldev'] = '2';
        $this->app->foot = array('<script>existing();</script>');
        try {
            (new Version())->check();
            $this->assertCount(3, $this->app->foot);
            $this->assertSame('<script>existing();</script>', $this->app->foot[0]);
            $this->assertStringContainsString('&#104;&#116;&#116;&#112;&#115;', $this->app->foot[1]);
            $this->assertStringContainsString('<script type="text/javascript">', $this->app->foot[2]);
        } finally {
            $_GET = $originalGet;
        }
    }

    /**
     * Create and upgrade the license table using schema contracts and key validation.
     *
     * @return void
     */
    public function testLicenseSchemaCreationAndVerifiedPrimaryKey()
    {
        $this->app->db->data_cache['table_names'] = array('stale');
        $this->app->db->keyRows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        $version = new Version();
        $this->assertFalse($version->verifyLicenseTable());
        $this->assertArrayNotHasKey('table_names', $this->app->db->data_cache);
        $this->assertContains(array('create_table', array('eeharbor_licenses')), $this->app->dbforge->calls);
        $this->assertContains(array('add_key', array('license_key', true)), $this->app->dbforge->calls);
        $this->assertContains(array('add_key', array(array('site_id', 'addon'))), $this->app->dbforge->calls);
        $this->assertSame('add_column', end($this->app->dbforge->calls)[0]);
        $calls = count($this->app->db->calls);
        $this->assertFalse($version->verifyLicenseTable());
        $this->assertSame($calls, count($this->app->db->calls));
    }

    /**
     * Distinguish missing, composite, nonunique, and wrong-column license indexes.
     *
     * @dataProvider invalidPrimaryKeys
     * @param array $rows
     * @return void
     */
    public function testExistingSchemaWithInvalidPrimaryKeyIsFlagged(array $rows)
    {
        $this->app->db->tables = array('eeharbor_licenses');
        $this->app->db->fields = array('ignore_site');
        $this->app->db->keyRows = $rows;
        $version = new Version();
        $this->assertTrue($version->verifyLicenseTable());
        $this->assertSame(array(), $this->app->dbforge->calls);
    }

    /**
     * Supply each meaningful invalid index shape returned by MySQL.
     *
     * @return array
     */
    public static function invalidPrimaryKeys()
    {
        return array(
            array(array()),
            array(array(array('Column_name' => 'other', 'Non_unique' => '0'))),
            array(array(array('Column_name' => 'license_key', 'Non_unique' => '1'))),
            array(array(array('Column_name' => 'license_key', 'Non_unique' => '0'), array('Column_name' => 'site_id', 'Non_unique' => '0'))),
        );
    }

    /**
     * Insert absent license keys and update existing keys with site/add-on predicates.
     *
     * @return void
     */
    public function testLicenseKeyPersistenceUsesCurrentSiteAndAddon()
    {
        $version = new Version();
        $this->assertFalse($version->keyExistsForAddon());
        $this->assertFalse($version->keyExistsForAddon());
        $version->saveLicenseKey(array('license_key' => 'abc'));
        $this->assertSame(array('insert', array('eeharbor_licenses', array('license_key' => 'abc', 'site_id' => 1, 'addon' => 'Low\\Seg2Cat'))), end($this->app->db->calls));
        StaticCache::delete('keyExistsForAddon');
        $this->app->db->rowsByTable['eeharbor_licenses'] = array(array('license_key' => 'abc'));
        $this->assertTrue($version->keyExistsForAddon());
        $this->assertTrue($version->keyExistsForAddon());
        $version->saveLicenseKey(array('site_id' => 9, 'addon' => 'Explicit', 'license_key' => 'new'), 'Custom');
        $this->assertSame(array('update', array('eeharbor_licenses', array('site_id' => 9, 'addon' => 'Explicit', 'license_key' => 'new'))), end($this->app->db->calls));
        $this->assertContains(array('where', array('addon', 'Custom')), $this->app->db->calls);
        $version->deleteLicenseKey();
        $this->assertSame(array('delete', array('eeharbor_licenses')), end($this->app->db->calls));
    }

    /**
     * Sanitize stored key punctuation and preserve ignored-site status.
     *
     * @return void
     */
    public function testLicenseReadAndIgnoreStatusHandleEmptyAndExistingRows()
    {
        $version = new Version();
        $this->assertSame('', $version->getLicenseKey());
        $this->assertFalse($version->getIgnoreSite());
        $this->app->db->rowsByTable['eeharbor_licenses'] = array(array('license_key' => 'a<script> b_1-2', 'ignore_site' => '1'));
        $this->assertSame('ascriptb1-2', $version->getLicenseKey());
        $this->assertSame('1', $version->getIgnoreSite());
    }

    /**
     * Emit a real encrypted update pixel once per page without sending HTTP requests.
     *
     * @return void
     */
    public function testCheckBuildsEncryptedPixelAndAvoidsDuplicateFooter()
    {
        $version = new Version();
        $this->app->db->rowsByTable['modules'] = array();
        $this->app->config->items['base_url'] = 'https://example.test/';
        $version->check();
        $this->assertCount(2, $this->app->foot);
        $this->assertStringContainsString('<div id="low_seg2cat__up"', $this->app->foot[0]);
        $this->assertStringContainsString('&#104;&#116;&#116;&#112;&#115;', $this->app->foot[0]);
        $this->assertMatchesRegularExpression('/\?[0-9a-f]{1024}/', $this->app->foot[0]);
        $this->assertTrue($version->check());
        $this->assertCount(2, $this->app->foot);
        $this->assertSame('', $this->invoke($version, 'spiderSafe', array('')));
    }

    /**
     * Use the local development pixel host and include an installed Pro module.
     *
     * @return void
     */
    public function testCheckUsesExplicitLocalDevelopmentPixel()
    {
        $originalGet = $_GET;
        $_GET['ldev'] = '1';
        try {
            $version = new Version();
            $this->app->db->rowsByTable['modules'] = array(array('module_version' => '2.0'));
            $this->app->config->items['site_license_key'] = 'example';
            $version->check();
            $this->assertStringContainsString('&#108;&#111;&#99;&#97;&#108;&#104;&#111;&#115;&#116;', $this->app->foot[0]);
        } finally {
            $_GET = $originalGet;
        }
    }
}
