<?php

namespace Low\Seg2Cat\FluxCapacitor\Bridge;

use Low\Seg2Cat\FluxCapacitor\Bridge\AbstractBridge;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

/**
 * EEHarbor EE2 version of the foundation
 *
 * Bridges the functionality gaps between EE versions.
 *
 * @package         EE2
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class EE2 extends AbstractBridge
{
    private $module;
    private $module_name;
    private $ee_major_version;
    private $app_settings;

    public function __construct($info)
    {
        $this->module      = $info['module'];
        $this->module_name = $info['module_name'];
    }

    public function instantiate($which)
    {
        ee()->api->instantiate($which);
    }

    /**
     * Build an EE2 URL with supported session version comparisons.
     *
     * @param string $method Controller method.
     * @param string $extra Additional query parameters.
     * @return string
     */
    public function getBaseURL($method = '', $extra = '')
    {
        if ($method == '/') {
            $method = '';
        } elseif ($method) {
            $method = AMP . 'method=' . $method;
        }

        $url = BASE . AMP . 'C=addons_modules' . AMP . 'M=show_module_cp' . AMP . 'module=' . $this->module . $method . $extra;

        if (version_compare(APP_VER, '2.6.0', '>=') && version_compare(APP_VER, '2.7.3', '<=')) {
            // Yay, workaround for EE 2.6.0 session bug
            $config_type = 'admin_session_type';
        } else {
            $config_type = 'cp_session_type';
        }

        $s = 0;
        switch (ee()->config->item($config_type)) {
            case 's':
                $s = ee()->session->userdata('session_id', 0);
                break;
            case 'cs':
                $s = ee()->session->userdata('fingerprint', 0);
                break;
        }

        // Test if our URL already has the session and directive.
        parse_str(parse_url(str_replace('&amp;', '&', $url), PHP_URL_QUERY), $url_test);

        if (!empty($s) && (!isset($url_test['S']) || empty($url_test['S']))) {
            $url .= AMP . 'S=' . $s;
        }

        if (!isset($url_test['D']) || empty($url_test['D'])) {
            $url .= AMP . 'D=cp';
        }

        return $url;
    }

    public function getNav($nav_items = array(), $buttons = array(), $active_map = array())
    {
        $nav = array();
        foreach ($nav_items as $method => $title) {
            if (strpos($method, 'http') === false) {
                $method = $this->getBaseURL($method);
            }

            $nav[$title] = $method;
        }

        ee()->cp->set_right_nav($nav);
    }

    public function cpURL($path, $mode = '', $variables = array())
    {
        switch ($path) {
            case 'listing':
                $path = 'content_edit';
                $mode = '';
                if (isset($variables['filter_by_channel'])) {
                    $variables['channel_id'] = $variables['filter_by_channel'];
                    unset($variables['filter_by_channel']);
                }
                break;

            case 'publish':
                $path = 'content_publish';
                if ($mode == 'create' || $mode == 'edit') {
                    $mode = 'entry_form';
                }
                break;

            case 'members':
                if ($mode == 'groups') {
                    $mode = 'member_group_manager';
                }
                break;

            case 'channels':
                $path = 'admin_content';
                if ($mode == 'create') {
                    $mode = 'channel_add';
                }
                break;

            case 'addons':
                $path = 'addons_modules';
                break;
        }

        $url = BASE . AMP . 'D=cp' . AMP . 'C=' . $path;

        if ($mode) {
            $url .= AMP . 'M=' . $mode;
        }

        foreach ($variables as $variable => $value) {
            $url .= AMP . $variable . '=' . $value;
        }

        return $url;
    }

    public function moduleURL($method = 'index', $variables = array())
    {
        $url = $this->getBaseURL() . AMP . 'method=' . $method;

        if (is_null($variables)) {
            $variables = array();
        }

        foreach ($variables as $variable => $value) {
            $url .= AMP . $variable . '=' . $value;
        }

        return $url;
    }

    public function view($view, $vars = array(), $return = false, $string = false)
    {
        // Add our global vars into the available view vars.
        $vars['ee_ver'] = $this->getEEVersion();

        if ($string && is_writable(PATH_THIRD . 'low_seg2cat/views')) {
            file_put_contents(PATH_THIRD . 'low_seg2cat/views/dynamic.php', $string);
            $view = 'dynamic';
        }

        // we cant do this (to load the license view from the phar)
        // ee()->load->add_package_path('phar://' . PATH_THIRD . 'low_seg2cat/low_seg2cat.phar');

        return ee()->load->view($view, $vars, $return);
    }

    public function getCurrentPage($options = array())
    {
        // If we have the per_page query variable, it's an offset.
        if (ee()->input->get('per_page', 1)) {
            $offset = (int) ee()->input->get('per_page', 1);
            return ($offset / $options['per_page']) + 1;
        } elseif (ee()->input->get('page', 1)) {
            return (int) ee()->input->get('page', 1);
        } else {
            return 1;
        }
    }

    public function getStartNum($options)
    {
        return ($options['current_page'] * $options['per_page']) - $options['per_page'];
    }

    public function pagination($options = array())
    {
        // Remap from normal logic to EE2 logic.
        if (isset($options['current_page'])) {
            $options['cur_page'] = $options['current_page'];
        }

        ee()->load->library('pagination');
        ee()->pagination->initialize($options);
        return ee()->pagination->create_links();
    }

    public function getSettings($asArray = false)
    {
        $this->initiateSettings();

        if ($asArray) {
            return $this->app_settings;
        }

        return (object) $this->app_settings;
    }

    public function getConfig($item)
    {
        $this->initiateSettings();

        return $this->app_settings[$item];
    }

    public function setConfig($item, $value)
    {
        $this->initiateSettings();

        $settings_db = clone ee()->db;

        // EE caches the list of DB tables, so unset the table_names var if it's set
        // otherwise table_exists could return a false negative if it was just created.
        if (isset($settings_db->data_cache['table_names'])) {
            unset($settings_db->data_cache['table_names']);
        }

        // Make sure the settings table exists.
        if ($settings_db->table_exists($this->module . '_settings')) {
            // Find out if the settings exist, if not, insert them.
            $settings_db->where('site_id', ee()->config->item('site_id'));
            $exists = $settings_db->count_all_results($this->module . '_settings');

            $data['site_id'] = ee()->config->item('site_id');
            $data[$item]     = $value;

            if ($exists) {
                $settings_db->where('site_id', ee()->config->item('site_id'));
                $settings_db->update($this->module . '_settings', $data);
            } else {
                $settings_db->insert($this->module . '_settings', $data);
            }
        }

        // Set variables
        $this->app_settings[$item] = $value;
        StaticCache::set('app_settings', $this->app_settings);
    }

    public function cache($mode, $key = false, $data = false, $persistent = true)
    {
        if (!isset(ee()->session->cache[$this->module])) {
            ee()->session->cache[$this->module] = array();
        }

        // Returns EE's native cache function for EE2.
        switch ($mode) {
            case 'get':
                if ($persistent && version_compare(APP_VER, '2.8.0', '>=')) {
                    return ee()->cache->get('/' . $this->module . '/' . $key);
                } elseif (isset(ee()->session->cache[$this->module][$key])) {
                    return ee()->session->cache[$this->module][$key];
                } else {
                    return false;
                }
                break;

            case 'set':
                if ($persistent && version_compare(APP_VER, '2.8.0', '>=')) {
                    return ee()->cache->save('/' . $this->module . '/' . $key, $data);
                } else {
                    return ee()->session->cache[$this->module][$key] = $data;
                }
                break;

            case 'delete':
            case 'clear':
                if ($key) {
                    unset(ee()->session->cache[$this->module][$key]);
                } else {
                    unset(ee()->session->cache[$this->module]);
                }
                break;

            default:
                return false;
        }
    }

    /**
     * Flash a message to the screen
     * @param  string $type             Type of message to display. [message_success, message_notice, message_error, message_failure]
     * @param  string $title            Title of flash message (Concatenated with body when EE2)
     * @param  string $body             Title of flash message (Concatenated with title when EE2)
     * @param  array  $extra_parameters Name of EE3 alert functions to call in addition to the default ones. (does nothing in EE2) ex. ['cannotClose']
     */
    public function flashData($type = 'message_success', $title = '', $body = '', $extra_parameters = array())
    {
        ee()->session->set_flashdata($type, $title . " " . $body);
    }

    /**
     * Gets the directory for the addon's theme files
     * @return [string] [path of directory]
     */
    public function getAddonThemesDir()
    {
        $theme_folder_url = (defined('URL_THIRD_THEMES') ? URL_THIRD_THEMES : ee()->config->slash_item('theme_folder_url') . 'third_party/') . $this->module . '/';
        return $theme_folder_url;
    }

    /**
     * Overwrite any native EE Classes.
     * EE2 uses direct assignment.
     *
     * @param object $class    The EE class object you want to overwrite
     * @param object $data     The optional data used to overwrite.
     **/
    public function overwriteEEClass($class, $data = '')
    {
        ee()->{$class} = $data;
    }

    /**
     * Remove any native EE Classes.
     * EE2 uses direct assignment.
     *
     * @param object $class    The EE class object you want to overwrite
     **/
    public function removeEEClass($class)
    {
        ee()->{$class} = '';
    }

    /**
     * XSS protection for user input
     * @param  String or Array $input xss_clean accepts a string or array as input.
     * @return Sanitized string or array
     */
    public function xss_clean($input)
    {
        return ee()->security->xss_clean($input);
    }

    /**
     * Convert text to url_title
     * @param String $text Text string to convert
     * @return String Converted url_title
     */
    public function url_title($text)
    {
        return  url_title(trim(strtolower($text)));
    }

    /**
     * Get information about the current page (in the CP)
     * @param  [string] $options option to only get a portion of the information rather than an array
     * @return [string or array]         full path info in array, or single element
     */
    public function getCurrentUrlInfo($options = null)
    {
        $url                  = @trim($_SERVER['QUERY_STRING'], "/");
        $segments             = @explode("/", $url);
        $url_info['full']     = $url;
        $url_info['cp']       = (@$segments[0] === "cp");
        $url_info['segments'] = $segments;
        $url_info['module']   = @$_GET["module"];
        $url_info['method']   = array_key_exists("method", $_GET) ? @$_GET["method"] : "index";

        if ($options && array_key_exists($options, $url_info)) {
            return $url_info[$options];
        }

        return $url_info;
    }

    /**
     * Returns the system cache path
     * @return string - path to cache
     */
    public function getCachePath()
    {
        $cache_path = ee()->config->item('cache_path');

        if (empty($cache_path)) {
            $cache_path = APPPATH . 'cache/';
        }

        return $cache_path;
    }

    public function getFooter()
    {
        return ee()->cp->footer_item;
    }

    /**
     * Call the EE method for removing double slashes. Is specific to the EE version.
     * @return string result
     */
    public function reduce_double_slashes($string)
    {
        if (version_compare(APP_VER, '2.6.0', '<')) {
            return ee()->functions->remove_double_slashes($string);
        } else {
            ee()->load->helper('string');
            return reduce_double_slashes($string);
        }
    }

    /**
     * Merge add-on metadata with stored site and fieldtype settings.
     *
     * @return bool
     */
    private function initiateSettings()
    {
        // Check if the settings are empty. If this was _ever_ run, it won't be
        // empty because we load the addon.setup file into it at the very least.
        $this->app_settings = StaticCache::get('app_settings');

        if (!empty($this->app_settings)) {
            return true;
        }

        $settings_db = clone ee()->db;

        // EE caches the list of DB tables, so unset the table_names var if it's set
        // otherwise table_exists could return a false negative if it was just created.
        if (isset($settings_db->data_cache['table_names'])) {
            unset($settings_db->data_cache['table_names']);
        }

        $dbSettings = array();

        if ($settings_db->table_exists($this->module . '_settings')) {
            $dbSettingsQuery = $settings_db->get_where($this->module . '_settings', array($this->module . '_settings.site_id' => ee()->config->item('site_id')));

            if ($this->module === "structure") {
                foreach ($dbSettingsQuery->result() as $row) {
                    $dbSettings[$row->var] = $row->var_value;
                }
            } else {
                $dbSettings = $dbSettingsQuery->row_array();
            }
        }

        // Fieldtype settings
        $ftSettingsQuery = $settings_db->select('settings')
            ->where('name', $this->module)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $payload = $ftSettingsQuery->row('settings');
            $decoded = is_string($payload) ? base64_decode($payload) : false;
            $ftSettings = is_string($decoded) && preg_match('/^(?:a:\d+:\{|b:[01];$)/', $decoded) ? \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($decoded) : array();

            // It is possible there is actually nothing in the FT settings. So if not, just return an empty array
            if (!$ftSettings) {
                $ftSettings = array();
            }
        } else {
            $ftSettings = array();
        }

        $addonSettings = require PATH_THIRD . 'low_seg2cat/addon.setup.php';

        $this->app_settings = array_merge(array_merge($ftSettings, $dbSettings), $addonSettings);

        StaticCache::set('app_settings', $this->app_settings);

        return true;
    }

    /**
     * Inject Javascript on the page
     */
    public function javascript_to_page($js)
    {
        ee()->cp->add_to_foot('<script type="text/javascript">' . $js . '</script>');
    }

    public function module_installed($module)
    {
        $settings_db = clone ee()->db;
        $exists = $settings_db->where('module_name', $module)->count_all_results('modules');

        if ($exists) {
            return true;
        } else {
            return false;
        }
    }

    public function assign_parameters($str, $defaults = array())
    {
        return ee()->functions->assign_parameters($str, $defaults);
    }

    /**
     * Assign tag variables
     * @param  String $str Template tag data
     * @return array       Array of individual variables.
     */
    public function assign_variables($str)
    {
        return ee()->functions->assign_variables($str);
    }
}
