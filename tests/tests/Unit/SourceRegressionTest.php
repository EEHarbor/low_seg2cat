<?php

namespace Tests;

class SourceRegressionTest extends TestCase
{
    /**
     * Avoid showing a saved-success alert when persisting settings fails.
     *
     * @return void
     */
    public function testFailedSettingsUpdateDoesNotShowSuccess()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->mutationResults['update'] = false;
        $this->invoke($extension, 'save_settings', array(array()));
        $success = array_filter($this->app->alerts->calls, function ($call) {
            return $call[0] === 'asSuccess';
        });
        $this->assertSame(array(), $success);
    }

    /**
     * Upgrade a legacy installation without aborting before its version is saved.
     *
     * @dataProvider legacyVersions
     * @param string $version
     * @param array $settings
     * @return void
     */
    public function testLegacyUpgradeCompletesWithoutMissingSettingsArgument($version, array $settings)
    {
        $extension = new \Low_seg2cat_ext();
        $extension->settings = $settings;
        $this->app->db->calls = array();
        $extension->update_extension($version);
        $updates = array_values(array_filter($this->app->db->calls, function ($call) {
            return $call[0] === 'update';
        }));
        $this->assertSame('4.1.1', end($updates)[1][1]['version']);
    }

    /**
     * Supply legacy single-site, already nested, and pre-hook-upgrade settings.
     *
     * @return array
     */
    public static function legacyVersions()
    {
        return array(
            'pre-MSM flat settings' => array('2.5.0', array('all_sites' => 'n')),
            'pre-MSM nested settings' => array('2.5.0', array(1 => array('all_sites' => 'n'))),
            'pre-template hook' => array('2.6.0', array('all_sites' => 'n')),
        );
    }

    /**
     * Honor the constructor's documented false settings input.
     *
     * @return void
     */
    public function testDocumentedFalseConstructorSettingsUseDefaults()
    {
        $extension = new \Low_seg2cat_ext(false);
        $this->assertSame('n', $extension->settings['all_sites']);
    }

    /**
     * Accept persisted null site settings without a runtime-dependent fatal error.
     *
     * @return void
     */
    public function testNullSiteSettingsFallBackToDefaults()
    {
        $extension = new \Low_seg2cat_ext(array(1 => null));
        $this->assertSame('n', $extension->settings['all_sites']);
    }

    /**
     * Keep last-segment placeholders valid when the URI exceeds the configured cap.
     *
     * @return void
     */
    public function testUriLongerThanMaximumDoesNotReadMissingLastSegment()
    {
        $this->app->config->items['max_url_segments'] = 2;
        $this->app->incomingUri = 'news/archive/unknown';
        $this->app->categories = array((object) array('cat_url_title' => 'news', 'cat_id' => 3, 'site_id' => 1,
            'group_id' => 1, 'parent_id' => 0, 'cat_name' => 'News', 'cat_description' => '', 'cat_image' => ''));
        $this->invoke(new \Low_seg2cat_ext(), 'add_vars');
        $this->assertSame('', $this->app->config->_global_vars['last_segment_category_id']);
    }
}
