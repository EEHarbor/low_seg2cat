<?php if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Seg2Cat Extension class
 *
 * @package        low_seg2cat
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-seg2cat
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */

include_once "addon.setup.php";
use Low\Seg2Cat\FluxCapacitor\Base\Ext;

class Low_seg2cat_ext extends Ext
{

    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * Extension settings
     *
     * @access      public
     * @var         array
     */
    public $settings = array();

    /**
     * Version (still needed)
     *
     * @access      public
     * @var         string
     */
    public $version;

    // --------------------------------------------------------------------

    /**
     * Name of this package
     *
     * @access      private
     * @var         string
     */
    private $package = 'low_seg2cat';

    /**
     * This add-on's info based on setup file
     *
     * @access      private
     * @var         object
     */
    private $info;

    /**
     * URI instance
     *
     * @access      private
     * @var         object
     */
    private $uri;

    /**
     * Current site id
     *
     * @access      private
     * @var         int
     */
    private $site_id;

    /**
     * Format category name?
     *
     * @access      private
     * @var         bool
     */
    private $format = true;

    /**
     * Hooks used
     *
     * @access      private
     * @var         array
     */
    public $hooks = array(
        'template_fetch_template'
    );

    /**
     * Default settings
     *
     * @access      private
     * @var         array
     */
    private $default_settings = array(
        'all_sites'        => 'n',
        'category_groups'  => array(),
        'uri_pattern'      => '',
        'set_all_segments' => 'y',
        'ignore_pagination'=> 'n',
        'parse_file_paths' => 'n'
    );

    /**
     * Category fields to set
     *
     * @access      public
     * @var         array
     */
    private $fields = array(
        'cat_id'          => 'category_id',
        'parent_id'       => 'category_parent_id',
        'group_id'        => 'category_group_id',
        'cat_name'        => 'category_name',
        'cat_description' => 'category_description',
        'cat_image'       => 'category_image'
    );

    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @access      public
     * @param       mixed     Array with settings or FALSE
     * @return      null
     */
    public function __construct($settings = array())
    {
        parent::__construct();

        // Set the info
        $this->info = ee('App')->get($this->package);

        // And version
        $this->version = $this->info->getVersion();

        // Get site id
        $this->site_id = ee()->config->item('site_id');

        // Set settings
        $this->settings = $this->get_site_settings($settings);
    }

    // --------------------------------------------------------------------

    /**
     * Settings form
     *
     * @access      public
     * @param       array     Current settings
     * @return      string
     */
    public function settings_form($current)
    {
        // --------------------------------------
        // The base URL for this add-on
        // --------------------------------------

        $base_url = ee('CP/URL', 'addons/settings/'.$this->package);

        // --------------------------------------
        // Save when posted
        // --------------------------------------

        if (! empty($_POST)) {
            $this->save_settings($current);

            // Redirect back, so we don't get the send POST vars msg on F5.
            ee()->functions->redirect($base_url);
        }

        // --------------------------------------
        // Get current settings for this site
        // --------------------------------------

        $current = $this->get_site_settings($current);

        // --------------------------------------
        // Make sure All Groups is active when none are selected
        // --------------------------------------

        if (empty($current['category_groups'])) {
            $current['category_groups'] = array(0);
        }

        // --------------------------------------
        // Get category groups
        // --------------------------------------

        $groups = ee('Model')
            ->get('CategoryGroup')
            ->filter('site_id', $this->site_id)
            ->all();

        $groups = $groups->sortBy('group_name');
        $groups = $groups->getDictionary('group_id', 'group_name');
        $groups = array(lang('all_groups')) + $groups;

        // --------------------------------------
        // Create form field for each setting
        // --------------------------------------

        $vars = array(
            'base_url' => $base_url,
            'cp_page_title' => $this->info->getName(),
            'save_btn_text' => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving'
        );

        //foreach ($this->default_settings as $key => $val)
        foreach ($current as $key => $val) {
            $row = array('title' => $key);

            switch ($key) {
                // Category Groups is a checkbox selection
                case 'category_groups':
                    $row['fields'] = array($key => array(
                        'type'    => 'checkbox',
                        'wrap'    => true,
                        'choices' => $groups,
                        'value'   => $val
                    ));
                break;

                // URI pattern is a regular text input
                case 'uri_pattern':
                    $row['fields'] = array($key => array(
                        'type'    => 'text',
                        'value'   => $val
                    ));
                break;

                // The rest are yes/no booleans
                default:
                    $row['fields'] = array($key => array(
                        'type'    => 'yes_no',
                        'value'   => $val
                    ));
                break;
            }

            // There's only one section here
            $vars['sections'][0][] = $row;
        }

        // --------------------------------------
        // Add JS
        // --------------------------------------

        ee()->cp->add_to_foot($this->js());

        // --------------------------------------
        // Load view
        // --------------------------------------

        return ee('View')->make($this->package.':settings')->render($vars);
    }

    // --------------------------------------------------------------------

    /**
     * Save extension settings
     *
     * @return      void
     */
    private function save_settings($settings)
    {
        // --------------------------------------
        // Loop through default settings, check
        // for POST values, fallback to default
        // --------------------------------------

        foreach ($this->default_settings as $key => $val) {
            $val = ee('Request')->post($key, $val);

            if (is_array($val)) {
                $val = array_values(array_filter($val));
            }

            $settings[$this->site_id][$key] = $val;
        }

        // --------------------------------------
        // Add alert to page
        // --------------------------------------

        ee('CP/Alert')->makeInline('shared-form')
            ->asSuccess()
            ->withTitle(lang('settings_saved'))
            ->addToBody(sprintf(lang('settings_saved_desc'), $this->info->getName()))
            ->defer();

        // --------------------------------------
        // Save serialized settings
        // --------------------------------------

        ee()->db->where('class', __CLASS__);
        ee()->db->update('extensions', array('settings' => serialize($settings)));
    }

    // --------------------------------------------------------------------
    // HOOKS
    // --------------------------------------------------------------------

    /**
     * EE 2.4.0+ uses template_fetch_template to add vars
     */
    public function template_fetch_template($row)
    {
        // Get the latest version of $row
        if (ee()->extensions->last_call !== false) {
            $row = ee()->extensions->last_call;
        }

        // Remember if vars were added
        static $added;

        // If not yet added...
        if (! $added) {
            // ...add the vars...
            $this->add_vars();

            // ...only once
            $added = true;
        }

        // Play nice, return it
        return $row;
    }

    /**
     * Leave for upgrade compatibility
     */
    public function sessions_end($SESS)
    {
        return $SESS;
    }

    /**
     * Search URI segments for categories and add those to global variables
     *
     * @access      private
     * @return      null
     */
    private function add_vars()
    {
        // --------------------------------------
        // Only continue if request is a page
        // and we have segments to check
        // --------------------------------------

        if (empty(ee()->uri->segments) && $this->settings['set_all_segments'] == 'n') {
            return;
        }

        // --------------------------------------
        // Initiate uri instance
        // --------------------------------------

        $this->uri = new EE_URI;
        $this->uri->_fetch_uri_string();

        if ($this->settings['ignore_pagination'] == 'y') {
            // Get rid of possible pagination segment at the end
            $this->uri->uri_string = preg_replace('#/[PC]\d+$#', '', $this->uri->uri_string);
        }

        $this->uri->_explode_segments();
        $this->uri->_reindex_segments();

        // --------------------------------------
        // Suggestion by Leevi Graham:
        // check for pattern before continuing
        // --------------------------------------

        if (! empty($this->settings['uri_pattern']) && ! preg_match($this->settings['uri_pattern'], $this->uri->uri_string)) {
            return;
        }

        // --------------------------------------
        // Initiate some vars
        // $data is used to add to global vars
        // $ids is used to keep track of all category ids found
        // --------------------------------------

        $data = $ids = array();

        // Also initiate this single var to an empty string
        $data['segment_category_ids'] = '';
        $data['segment_category_ids_piped'] = '';

        // --------------------------------------
        // Number of segments to register
        // --------------------------------------

        $max = (int) (ee()->config->item('max_url_segments') ?: 12);

        $num_segs = ($this->settings['set_all_segments'] == 'y') ? $max : $this->uri->total_segments();

        // --------------------------------------
        // loop through segments and set data array thus: segment_1_category_id etc
        // --------------------------------------

        for ($nr = 1; $nr <= $num_segs; $nr++) {
            foreach ($this->fields as $field) {
                $data["segment_{$nr}_{$field}"] = '';
            }
        }

        // Initiate last segment vars
        foreach ($this->fields as $field) {
            $data["last_segment_{$field}"] = '';
        }

        // --------------------------------------
        // Execute the rest only if there are segments to check
        // --------------------------------------

        if ($segment_array = $this->uri->segment_array()) {
            // --------------------------------------
            // Use Model to get the categories
            // --------------------------------------

            $model = ee('Model')
                ->get('Category')
                ->filter('cat_url_title', 'IN', $segment_array)
                ->limit($num_segs);

            // --------------------------------------
            // Filter by site and its category groups
            // --------------------------------------

            if ($this->settings['all_sites'] == 'n') {
                $model->filter('site_id', $this->site_id);

                if (isset($this->settings['category_groups']) &&
                   ($groups = array_filter($this->settings['category_groups']))) {
                    $model->filter('group_id', 'IN', $groups);
                }
            }

            // --------------------------------------
            // Execute query and get results
            // --------------------------------------

            $cats = $model->all();

            // --------------------------------------
            // If we have matching categories, continue...
            // --------------------------------------

            if ($cats->count()) {
                // --------------------------------------
                // Associate the results
                // --------------------------------------

                $cats = $cats->indexBy('cat_url_title');

                // --------------------------------------
                // Load typography if private var is set
                // --------------------------------------

                if ($this->format || $this->settings['parse_file_paths'] == 'y') {
                    ee()->load->library('typography');
                }

                // --------------------------------------
                // loop through segments
                // --------------------------------------

                foreach ($segment_array as $n => $seg) {
                    // Skip non-matching segments
                    if (! isset($cats[$seg])) {
                        continue;
                    }

                    // Get the category row
                    $cat = $cats[$seg];

                    // Overwrite values in data array
                    foreach ($this->fields as $name => $field) {
                        // Value of this key
                        $val = $cat->$name;

                        // Format category name if private var is set
                        if ($name == 'cat_name' && $this->format) {
                            $val = ee()->typography->format_characters($val);
                        }

                        // Parse file paths
                        if ($name == 'cat_image' && $this->settings['parse_file_paths'] == 'y') {
                            $val = ee()->typography->parse_file_paths($val);
                        }

                        // Set value in for segment_x_yyy
                        $data["segment_{$n}_{$field}"] = $val;
                    }

                    // Add found id to cats array
                    $ids[] = $cat->cat_id;
                }

                // --------------------------------------
                // Set last_segment_category_x vars
                // --------------------------------------

                $last = $this->uri->total_segments();

                foreach ($this->fields as $name => $field) {
                    $data['last_segment_'.$field] = $data['segment_'.$last.'_'.$field];
                }

                // --------------------------------------
                // Create inclusive stack of all category ids present in segments
                // --------------------------------------

                $data['segment_category_ids'] = implode('&', $ids);
                $data['segment_category_ids_piped'] = implode('|', $ids);
            }
        }

        // --------------------------------------
        // Finally, add data to global vars
        // Swapping $data and existing global vars makes a difference in EE2.4+
        // --------------------------------------

        ee()->config->_global_vars = array_merge(ee()->config->_global_vars, $data);
        //ee()->config->_global_vars = array_merge($data, ee()->config->_global_vars);
    }

    // --------------------------------------------------------------------

    /**
     * Activate extension
     *
     * @access      public
     * @return      null
     */
    public function activate_extension()
    {
        foreach ($this->hooks as $hook) {
            $this->add_hook($hook);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Update extension
     *
     * @access      public
     * @param       string    Saved extension version
     * @return      null
     */
    public function update_extension($current = '')
    {
        if ($current == '' || $current == $this->info->getVersion()) {
            return false;
        }

        // init data array
        $data = array();

        // Update to MSM compatible extension settings
        if (version_compare($current, '2.6.0', '<')) {
            if (! isset($this->settings[$this->site_id])) {
                $data['settings'] = serialize(array($this->site_id => $this->settings));
            }
        }

        // Add template_fetch_template hook
        if (version_compare($current, '2.7.0', '<')) {
            $this->add_hook('template_fetch_template');
            $this->save_settings();
        }

        // Remove obsolete sessions_end hook
        if (version_compare($current, '3.0.0', '<')) {
            ee()->db->delete('extensions', array(
                'class' => __CLASS__,
                'hook' => 'sessions_end'
            ));
        }

        // Add version to data array
        $data['version'] = $this->info->getVersion();

        // Update records using data array
        ee()->db->where('class', __CLASS__);
        ee()->db->update('extensions', $data);
    }

    // --------------------------------------------------------------------

    /**
     * Disable extension
     *
     * @access      public
     * @return      null
     */
    public function disable_extension()
    {
        // Delete records
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    // --------------------------------------------------------------------
    // HELPER METHODS
    // --------------------------------------------------------------------

    /**
     * Get settings for this site
     *
     * @access      private
     * @return      mixed
     */
    private function get_site_settings(array $settings)
    {
        // Are there settings for this site?
        $settings = array_key_exists($this->site_id, $settings)
            ? $settings[$this->site_id]
            : array();

        // Always make sure all settings are set
        $settings = array_merge($this->default_settings, $settings);

        // And return it
        return $settings;
    }

    // --------------------------------------------------------------------

    /**
     * Add hook to table
     *
     * @access  private
     * @param   string
     * @return  void
     */
    private function add_hook($hook)
    {
        ee()->db->insert('extensions', array(
            'class'    => __CLASS__,
            'method'   => $hook,
            'hook'     => $hook,
            'settings' => serialize(array($this->site_id => $this->settings)),
            'priority' => 5,
            'version'  => $this->info->getVersion(),
            'enabled'  => 'y'
        ));
    }

    // --------------------------------------------------------------------

    /**
     * JavaScript for the settings page
     *
     * @access  private
     * @param   string
     * @return  void
     */
    private function js()
    {
        return <<<EOJS
        <script>
            (function($){
                var \$radio = \$('input[name="all_sites"]');
                var toggle  = function(){
                    var val = \$radio.filter(':checked').val();
                    \$('input[name="category_groups\[\]"]').attr('disabled', (val == 'y'));
                };
                \$radio.on('change', toggle);
                toggle();
            })(jQuery);
        </script>
EOJS;
    }
}
// END CLASS

/* End of file ext.low_seg2cat.php */
