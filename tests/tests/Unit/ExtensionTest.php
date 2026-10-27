<?php

namespace Tests;

class ExtensionTest extends TestCase
{
    /**
     * Merge site-specific settings with defaults while keeping other sites separate.
     *
     * @return void
     */
    public function testConstructorMergesOnlyCurrentSiteSettings()
    {
        $extension = new \Low_seg2cat_ext(array(1 => array('all_sites' => 'y'), 2 => array('uri_pattern' => '#other#')));
        $this->assertSame('4.1.1', $extension->version);
        $this->assertSame('Low Seg2Cat', $extension->name);
        $this->assertSame('y', $extension->settings['all_sites']);
        $this->assertSame('', $extension->settings['uri_pattern']);
        $this->assertSame(array(), $extension->settings['category_groups']);
        $this->assertSame('y', $extension->settings['set_all_segments']);
    }

    /**
     * Use defaults when a different site owns all persisted settings.
     *
     * @return void
     */
    public function testConstructorUsesDefaultsForMissingSite()
    {
        $extension = new \Low_seg2cat_ext(array(2 => array('all_sites' => 'y')));
        $this->assertSame('n', $extension->settings['all_sites']);
        $this->assertSame('n', $extension->settings['parse_file_paths']);
        $this->assertSame('n', $extension->settings['ignore_pagination']);
    }

    /**
     * Render the settings controls with sorted groups belonging to this site.
     *
     * @return void
     */
    public function testSettingsFormRendersFieldTypesAndSortedSiteGroups()
    {
        $this->app->groups = array(
            (object) array('site_id' => 1, 'group_id' => 9, 'group_name' => 'Zoo'),
            (object) array('site_id' => 2, 'group_id' => 3, 'group_name' => 'Other site'),
            (object) array('site_id' => 1, 'group_id' => 4, 'group_name' => 'Alpha'),
        );
        $extension = new \Low_seg2cat_ext();
        $this->assertSame('rendered:low_seg2cat:settings', $extension->settings_form(array()));
        list($view, $vars) = $this->app->rendered[0];
        $this->assertSame('low_seg2cat:settings', $view);
        $this->assertSame('cp://addons/settings/low_seg2cat', (string) $vars['base_url']);
        $this->assertSame('Low Seg2Cat', $vars['cp_page_title']);
        $fields = $vars['sections'][0];
        $this->assertSame('yes_no', $fields[0]['fields']['all_sites']['type']);
        $this->assertSame('checkbox', $fields[1]['fields']['category_groups']['type']);
        $this->assertSame(array(0), $fields[1]['fields']['category_groups']['value']);
        $this->assertSame(array(0 => 'all_groups', 4 => 'Alpha', 9 => 'Zoo'), $fields[1]['fields']['category_groups']['choices']);
        $this->assertSame('text', $fields[2]['fields']['uri_pattern']['type']);
        $this->assertStringContainsString("all_sites", $this->app->foot[0]);
        $this->assertStringContainsString(".on('change', toggle)", $this->app->foot[0]);
    }

    /**
     * Persist posted settings under the current site and preserve other sites.
     *
     * @return void
     */
    public function testPostedSettingsFilterEmptyGroupsAndPreserveOtherSites()
    {
        $_POST = array('all_sites' => 'y', 'category_groups' => array('', '0', '7', 9, false), 'uri_pattern' => '#news#');
        $extension = new \Low_seg2cat_ext();
        try {
            $extension->settings_form(array(2 => array('all_sites' => 'n')));
            $this->fail('The EE redirect returned to settings rendering.');
        } catch (\Tests\Support\RedirectTermination $termination) {
            $this->assertSame('cp://addons/settings/low_seg2cat', $termination->getMessage());
        }
        $updates = array_values(array_filter($this->app->db->calls, function ($call) {
            return $call[0] === 'update';
        }));
        $settings = unserialize($updates[0][1][1]['settings']);
        $this->assertSame(array('all_sites' => 'n'), $settings[2]);
        $this->assertSame(array('7', 9), $settings[1]['category_groups']);
        $this->assertSame('y', $settings[1]['all_sites']);
        $this->assertSame('#news#', $settings[1]['uri_pattern']);
        $this->assertSame('y', $settings[1]['set_all_segments']);
        $this->assertSame(array('cp://addons/settings/low_seg2cat'), $this->app->redirects);
        $this->assertSame(array('makeInline', array('shared-form')), $this->app->alerts->calls[0]);
        $this->assertSame(array('addToBody', array('Low Seg2Cat settings saved.')), $this->app->alerts->calls[3]);
        $this->assertSame(array(), $this->app->rendered);
        $this->assertSame(array(), $this->app->foot);
    }

    /**
     * Preserve selected group controls when settings are already configured.
     *
     * @return void
     */
    public function testSettingsFormPreservesSelectedGroups()
    {
        (new \Low_seg2cat_ext())->settings_form(array(1 => array('category_groups' => array('4'))));
        $fields = $this->app->rendered[0][1]['sections'][0];
        $this->assertSame(array('4'), $fields[1]['fields']['category_groups']['value']);
    }

    /**
     * Avoid model work for requests with no segments and no requested placeholders.
     *
     * @return void
     */
    public function testNoSegmentsGuardPreservesGlobalVariables()
    {
        $this->app->config->_global_vars = array('keep' => 'value');
        $extension = new \Low_seg2cat_ext(array(1 => array('set_all_segments' => 'n')));
        $this->invoke($extension, 'add_vars');
        $this->assertSame(array('keep' => 'value'), $this->app->config->_global_vars);
        $this->assertSame(array(), $this->app->model->queries);
    }

    /**
     * Populate all default placeholders even when the request has no categories.
     *
     * @return void
     */
    public function testEmptyRequestCreatesTwelvePlaceholdersUsingFallbackMaximum()
    {
        $this->app->config->items['max_url_segments'] = 0;
        $extension = new \Low_seg2cat_ext();
        $this->invoke($extension, 'add_vars');
        $vars = $this->app->config->_global_vars;
        $this->assertCount(80, $vars);
        $this->assertSame('', $vars['segment_12_category_id']);
        $this->assertArrayNotHasKey('segment_13_category_id', $vars);
        $this->assertSame('', $vars['last_segment_category_id']);
        $this->assertSame(array(), $this->app->model->queries);
    }

    /**
     * Stop a mismatching URI pattern before creating global variables.
     *
     * @return void
     */
    public function testUriPatternRejectsOtherSections()
    {
        $this->app->incomingUri = 'blog/news';
        $extension = new \Low_seg2cat_ext(array(1 => array('uri_pattern' => '#^store/#')));
        $this->invoke($extension, 'add_vars');
        $this->assertSame(array(), $this->app->config->_global_vars);
        $this->assertSame(array(), $this->app->model->queries);
    }

    /**
     * Match category URL titles while restricting both site and selected groups.
     *
     * @return void
     */
    public function testCategoriesApplyFiltersFormattingPathsAndOrderedIds()
    {
        $this->app->incomingUri = 'missing/news/products';
        $this->app->uri->segments = array(1 => 'existing');
        $this->app->categories = array(
            $this->category('news', 11, 1, 4, 'News & notes'),
            $this->category('products', 12, 1, 4, 'Products'),
            $this->category('missing', 99, 2, 4, 'Other site'),
            $this->category('missing', 98, 1, 8, 'Other group'),
        );
        $this->app->config->_global_vars = array('keep' => 'ok', 'segment_2_category_id' => 'stale');
        $extension = new \Low_seg2cat_ext(array(1 => array('category_groups' => array(0, 4), 'set_all_segments' => 'n', 'parse_file_paths' => 'y', 'uri_pattern' => '#news#')));
        $this->invoke($extension, 'add_vars');
        $vars = $this->app->config->_global_vars;
        $this->assertSame('', $vars['segment_1_category_id']);
        $this->assertSame(11, $vars['segment_2_category_id']);
        $this->assertSame('News &amp; notes', $vars['segment_2_category_name']);
        $this->assertSame('/images/category.jpg', $vars['segment_2_category_image']);
        $this->assertSame(12, $vars['last_segment_category_id']);
        $this->assertSame('11&12', $vars['segment_category_ids']);
        $this->assertSame('11|12', $vars['segment_category_ids_piped']);
        $this->assertSame('ok', $vars['keep']);
        $this->assertSame(array('typography'), $this->app->loaded);
        $this->assertSame(array('News & notes', 'Products'), $this->app->formatCalls);
        $this->assertSame(array(1 => 'existing'), $this->app->uri->segments);
        $this->assertSame(array('group_id', 'IN', array(1 => 4)), $this->app->model->queries[0][1]->filters[2]);
    }

    /**
     * Treat a persisted null group selection as unrestricted within the current site.
     *
     * @return void
     */
    public function testNullGroupSelectionKeepsTheCurrentSiteFilter()
    {
        $this->app->incomingUri = 'news';
        $this->app->categories = array(
            $this->category('news', 42, 1, 9),
            $this->category('news', 99, 2, 9),
        );
        $extension = new \Low_seg2cat_ext(array(1 => array('category_groups' => null)));
        $this->invoke($extension, 'add_vars');
        $this->assertNull($extension->settings['category_groups']);
        $this->assertSame(42, $this->app->config->_global_vars['segment_1_category_id']);
        $this->assertSame(42, $this->app->config->_global_vars['last_segment_category_id']);
        $this->assertSame('42', $this->app->config->_global_vars['segment_category_ids']);
        $this->assertSame(array(
            array('cat_url_title', 'IN', array(1 => 'news')),
            array('site_id', 1),
        ), $this->app->model->queries[0][1]->filters);
    }

    /**
     * Search other sites and preserve unformatted values when configured.
     *
     * @return void
     */
    public function testAllSitesIgnoresGroupSelectionAndCanDisableFormatting()
    {
        $this->app->incomingUri = 'news';
        $this->app->categories = array($this->category('news', 42, 2, 9, 'News & notes'));
        $extension = new \Low_seg2cat_ext(array(1 => array('all_sites' => 'y', 'category_groups' => array(4))));
        $this->setProperty($extension, 'format', false);
        $this->invoke($extension, 'add_vars');
        $this->assertSame(42, $this->app->config->_global_vars['segment_1_category_id']);
        $this->assertSame('News & notes', $this->app->config->_global_vars['segment_1_category_name']);
        $this->assertSame(array(), $this->app->loaded);
        $this->assertCount(1, $this->app->model->queries[0][1]->filters);
    }

    /**
     * Parse image paths even when character formatting is disabled.
     *
     * @return void
     */
    public function testFilePathParsingLoadsTypographyWithoutNameFormatting()
    {
        $this->app->incomingUri = 'news';
        $this->app->categories = array($this->category('news', 1));
        $extension = new \Low_seg2cat_ext(array(1 => array('parse_file_paths' => 'y')));
        $this->setProperty($extension, 'format', false);
        $this->invoke($extension, 'add_vars');
        $this->assertSame(array('typography'), $this->app->loaded);
        $this->assertSame(array(), $this->app->formatCalls);
        $this->assertSame('/images/category.jpg', $this->app->config->_global_vars['segment_1_category_image']);
    }

    /**
     * Remove either supported pagination suffix before looking up categories.
     *
     * @dataProvider paginationSuffixes
     * @param string $suffix
     * @return void
     */
    public function testPaginationSuffixDoesNotBecomeLastCategorySegment($suffix)
    {
        $this->app->incomingUri = 'news/' . $suffix;
        $this->app->categories = array($this->category('news', 3));
        $extension = new \Low_seg2cat_ext(array(1 => array('ignore_pagination' => 'y')));
        $this->invoke($extension, 'add_vars');
        $this->assertSame(3, $this->app->config->_global_vars['last_segment_category_id']);
        $this->assertSame(array('cat_url_title', 'IN', array(1 => 'news')), $this->app->model->queries[0][1]->filters[0]);
    }

    /**
     * Supply the pagination segment forms understood by EE.
     *
     * @return array
     */
    public static function paginationSuffixes()
    {
        return array(array('P20'), array('C10'));
    }

    /**
     * Leave unmatched category variables empty without loading typography.
     *
     * @return void
     */
    public function testNoMatchingCategoriesKeepsEmptyValues()
    {
        $this->app->incomingUri = 'unknown';
        $extension = new \Low_seg2cat_ext();
        $this->invoke($extension, 'add_vars');
        $this->assertSame('', $this->app->config->_global_vars['segment_1_category_id']);
        $this->assertSame('', $this->app->config->_global_vars['segment_category_ids']);
        $this->assertSame(array(), $this->app->loaded);
        $this->assertCount(2, $this->app->model->queries[0][1]->filters);
    }

    /**
     * Respect a numeric-string URL segment maximum when creating placeholders.
     *
     * @return void
     */
    public function testNumericStringMaximumSetsExactPlaceholderCount()
    {
        $this->app->config->items['max_url_segments'] = '2';
        $this->invoke(new \Low_seg2cat_ext(), 'add_vars');
        $this->assertArrayHasKey('segment_2_category_id', $this->app->config->_global_vars);
        $this->assertArrayNotHasKey('segment_3_category_id', $this->app->config->_global_vars);
    }

    /**
     * Return the hook's latest chained value and add variables at most once.
     *
     * @return void
     */
    public function testTemplateHookRunsOnceAndPreservesChainedResults()
    {
        $extension = new \Low_seg2cat_ext();
        $row = array('template_data' => 'original');
        $this->assertSame($row, $extension->template_fetch_template($row));
        $this->assertArrayHasKey('segment_1_category_id', $this->app->config->_global_vars);
        $this->app->config->_global_vars['segment_1_category_id'] = 'keep on second call';
        $this->app->extensions->last_call = array('template_data' => 'previous extension');
        $this->assertSame($this->app->extensions->last_call, $extension->template_fetch_template($row));
        $this->assertSame('keep on second call', $this->app->config->_global_vars['segment_1_category_id']);
    }

    /**
     * Preserve the legacy session hook result without any side effects.
     *
     * @return void
     */
    public function testLegacySessionHookReturnsItsInput()
    {
        $extension = new \Low_seg2cat_ext();
        $session = (object) array('member_id' => 12);
        $this->assertSame($session, $extension->sessions_end($session));
        $this->assertNull($extension->sessions_end(null));
    }

    /**
     * Register every configured hook with site-specific serialized settings.
     *
     * @return void
     */
    public function testActivationRegistersConfiguredHooks()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->calls = array();
        $extension->activate_extension();
        $this->assertCount(1, $this->app->db->calls);
        $data = $this->app->db->calls[0][1][1];
        $this->assertSame('template_fetch_template', $data['hook']);
        $this->assertSame('Low_seg2cat_ext', $data['class']);
        $this->assertSame(5, $data['priority']);
        $this->assertSame(array(1 => $extension->settings), unserialize($data['settings']));
        $extension->hooks = array();
        $extension->activate_extension();
        $this->assertCount(1, $this->app->db->calls);
    }

    /**
     * Stop upgrades when no version or the current version is supplied.
     *
     * @return void
     */
    public function testCurrentOrMissingVersionDoesNotWrite()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->calls = array();
        $this->assertFalse($extension->update_extension());
        $this->assertFalse($extension->update_extension('4.1.1'));
        $this->assertSame(array(), $this->app->db->calls);
    }

    /**
     * Remove the retired hook before updating a post-2.7 extension.
     *
     * @return void
     */
    public function testUpgradeRemovesObsoleteHookAndUpdatesVersion()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->calls = array();
        $extension->update_extension('2.8.0');
        $this->assertSame(array('delete', array('extensions', array('class' => 'Low_seg2cat_ext', 'hook' => 'sessions_end'))), $this->app->db->calls[0]);
        $this->assertSame(array('update', array('extensions', array('version' => '4.1.1'))), $this->app->db->calls[2]);
    }

    /**
     * Update modern extension versions without deleting hooks again.
     *
     * @return void
     */
    public function testModernUpgradeOnlyUpdatesVersion()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->calls = array();
        $extension->update_extension('4.0.0');
        $this->assertCount(2, $this->app->db->calls);
        $this->assertSame('where', $this->app->db->calls[0][0]);
        $this->assertSame('update', $this->app->db->calls[1][0]);
    }

    /**
     * Delete only this extension's records when it is disabled.
     *
     * @return void
     */
    public function testDisableScopesDeletionToThisExtension()
    {
        $extension = new \Low_seg2cat_ext();
        $this->app->db->calls = array();
        $extension->disable_extension();
        $this->assertSame(array(array('where', array('class', 'Low_seg2cat_ext')), array('delete', array('extensions'))), $this->app->db->calls);
    }

    /**
     * Build a realistic EE Category record.
     *
     * @param string $slug
     * @param int $id
     * @param int $site
     * @param int $group
     * @param string $name
     * @return object
     */
    private function category($slug, $id, $site = 1, $group = 4, $name = 'News')
    {
        return (object) array('cat_url_title' => $slug, 'cat_id' => $id, 'site_id' => $site, 'group_id' => $group,
            'parent_id' => 0, 'cat_name' => $name, 'cat_description' => 'Description', 'cat_image' => '{filedir_1}category.jpg');
    }
}
