<?php

namespace Low\Seg2Cat\FluxCapacitor\Bridge;

use Low\Seg2Cat\FluxCapacitor\Bridge\AbstractBridge;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

/**
 * EEHarbor EE3 version of the foundation
 *
 * Bridges the functionality gaps between EE versions.
 *
 * @package         EE3
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class EE3 extends AbstractBridge
{
    private $module;
    private $module_name;
    private $ee_major_version;
    private $app_settings;

    /**
     * Foundation function
     * Determines EE version to:
     *  - Set up right nav or sidebar
     *  - Determine base_url from raw or CP/URL service
     *  - Set view folder
     * Includes any JS or CSS from themes.
     */
    public function __construct($info)
    {
        $this->module      = $info['module'];
        $this->module_name = $info['module_name'];
    }

    public function instantiate($which)
    {
        ee()->legacy_api->instantiate($which);
    }

    public function getBaseURL($method = '', $extra = '')
    {
        if ($method == '/') {
            $method = '';
        } elseif ($method) {
            $method = '/' . $method;
        }

        return ee('CP/URL', 'addons/settings/' . $this->module . $method . $extra);
    }

    /**
     * Render local or absolute URLs and preserve the active text title.
     *
     * @param array $nav_items Navigation labels keyed by method.
     * @param array $buttons Header action buttons.
     * @param array $active_map Active method aliases.
     * @return void
     */
    public function getNav($nav_items = array(), $buttons = array(), $active_map = array())
    {
        $sidebar      = ee('CP/Sidebar')->make();
        $last_segment = ee()->uri->segment_array();
        $last_segment = end($last_segment);
        $activeTitle = null;
        $titles = array();

        foreach ($nav_items as $method => $title) {
            if ($method == '/') {
                $method = 'index';
            }

            if (!preg_match('#^https?://#i', $method)) {
                $url = $this->getBaseURL($method);
            } else {
                $url = $method;
            }

            $titles[$method] = $title;
            $nav_items[$method] = $sidebar->addHeader($title)->withUrl($url);

            // This is to add buttons to a nav.
            if (!empty($buttons[$method])) {
                foreach ($buttons[$method] as $url => $text) {
                    $nav_items[$method]->withButton($text, $this->moduleURL($url));
                }
            }

            if ($last_segment == $method || ($method == 'index' && $last_segment == $this->module)) {
                $activeTitle = $title;
                $nav_items[$method]->isActive();
            }
        }

        if (!empty($active_map[$last_segment]) && !empty($nav_items[$active_map[$last_segment]])) {
            $activeTitle = $titles[$active_map[$last_segment]];
            $nav_items[$active_map[$last_segment]]->isActive();
        }

        if ($activeTitle) {
            ee()->view->cp_page_title = $activeTitle;
            ee()->cp->set_breadcrumb($this->moduleURL('index'), 'Low\Seg2Cat');
        }
    }

    public function cpURL($path, $mode = '', $variables = array())
    {
        if ($mode) {
            $mode = '/' . $mode;
        }

        if ($path == 'listing') {
            $path = 'publish';
        }

        if ($path == 'publish') {
            if ($mode == '/create' && isset($variables['channel_id'])) {
                $mode .= '/' . $variables['channel_id'];
                unset($variables['channel_id']);
            } elseif ($mode == '/edit' && isset($variables['entry_id'])) {
                $mode .= '/entry/' . $variables['entry_id'];
                unset($variables['entry_id']);
            }
        }

        $url = ee('CP/URL')->make($path . $mode, $variables);

        return $url;
    }

    public function moduleURL($method = 'index', $variables = array())
    {
        $url = ee('CP/URL')->make('addons/settings/' . $this->module . '/' . $method, $variables);

        return $url;
    }

    public function view($view, $vars = array(), $return = false, $string = false)
    {
        // Add our global vars into the available view vars.
        $vars['ee_ver'] = $this->getEEVersion();

        if (!isset($vars['base_url'])) {
            $vars['base_url'] = $this->getBaseURL();
        }

        if (!isset($vars['cp_page_title'])) {
            $vars['cp_page_title'] = ee()->view->cp_page_title;
        }

        if ($string) {
            $view_content = ee('View')->makeFromString($string)->render($vars);
        } else {
            $view_content = ee('View')->make($this->module . ':' . $view)->render($vars);
        }

        return array(
            'heading'    => ee()->view->cp_page_title,
            'breadcrumb' => array(
                ee('CP/URL', 'addons/settings/' . $this->module . '/')->compile() => $this->module_name,
            ),
            'body'       => $view_content,
        );
    }

    public function getCurrentPage($options = array())
    {
        //TODO: implement the passed $options variable (if it exists)
        if (ee()->input->get('per_page', 1)) {
            return ee()->input->get('per_page', 1);
        } elseif (ee()->input->get('page', 1)) {
            return ee()->input->get('page', 1);
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
        // Pagination object
        $pagination = ee('CP/Pagination', $options['total_rows'])
            ->perPage($options['per_page'])
            ->currentPage($options['current_page']);

        // Set extra stuff that is optional
        if (isset($options['page_links'])) {
            $pagination->displayPageLinks($options['page_links']);
        }

        if (isset($options['page_query_string'])) {
            $pagination->queryStringVariable($options['page_query_string']);
        }

        // Render the pagination
        return $pagination->render($options['base_url']);
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

        // Use our own instance of the query builder so we don't stomp over any in-progress queries.
        $settings_db = ee('db');

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
        // Returns EE's native cache function for EE3.
        switch ($mode) {
            case 'get':
                if ($persistent) {
                    return ee()->cache->get('/' . $this->module . '/' . $key);
                } else {
                    return ee()->session->cache($this->module, $key, false);
                }
                break;

            case 'set':
                if ($persistent) {
                    return ee()->cache->save('/' . $this->module . '/' . $key, $data);
                } else {
                    return ee()->session->set_cache($this->module, $key, $data);
                }
                break;

            case 'delete':
            case 'clear':
                return ee()->cache->delete('/' . $this->module . '/' . $key);
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
        $alert = ee('CP/Alert')->make($title);

        // set alert type based on name
        if ($type === "message_error" || $type === "message_failure") {
            $alert->asIssue();
        } elseif ($type === "message_notice") {
            $alert->asWarning();
        } else {
            $alert->asSuccess();
        }

        // Set the alert title and body
        $alert->withTitle($title)
            ->addToBody($body);

        // default to allowing alerts to close
        $alert->canClose();

        // if there are custom parameters, call them at the end
        foreach ($extra_parameters as $extra) {
            // make sure the method exists, then call it
            if (method_exists($alert, $extra)) {
                $alert->$extra();
            }
        }

        // defer alert so it actually shows up on page
        $alert->defer();
    }

    /**
     * Gets the directory for the addon's theme files
     * @return [string] [path of directory]
     */
    public function getAddonThemesDir()
    {
        $theme_folder_url = (defined('URL_THIRD_THEMES') ? URL_THIRD_THEMES : ee()->config->slash_item('theme_folder_url') . 'user/') . $this->module . '/';
        return $theme_folder_url;
    }

    /**
     * Replace loaded facade values and register unloaded services.
     *
     * @param string $class Service name.
     * @param mixed $data Replacement service.
     * @return void
     */
    public function overwriteEEClass($class, $data = '')
    {
        if (ee()->has($class)) {
            ee()->remove($class);
        }
        ee()->set($class, $data);
    }

    /**
     * Remove any native EE Classes.
     * EE3 uses EE's remove() method.
     *
     * @param object $class    The EE class object you want to overwrite
     **/
    public function removeEEClass($class)
    {
        ee()->remove($class);
    }

    /**
     * XSS protection for user input
     * @param  String or Array $input xss_clean accepts a string or array as input.
     * @return Sanitized string or array
     */
    public function xss_clean($input)
    {
        return ee('Security/XSS')->clean($input);
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
     * Assign tag variables
     * @param  String $str Template tag data
     * @return array       Array of individual variables.
     */
    public function assign_variables($str)
    {
        return ee()->functions->assign_variables($str);
    }

    /**
     * Prep URL with scheme.
     * @param  string $url Adds the http:// part if no scheme is included
     * @return string      Updated URL
     */
    public function prep_url($str)
    {
        return prep_url($str);
    }

    /**
     * Read available URL segments without missing-index diagnostics.
     *
     * @param string|null $options Optional information key.
     * @return array|string|null
     */
    public function getCurrentUrlInfo($options = null)
    {
        $url                  = ee()->uri->uri_string();
        $segments             = explode("/", $url);
        $url_info['full']     = $url;
        $url_info['cp']       = (($segments[0] ?? null) === "cp");
        $url_info['segments'] = $segments;
        $url_info['module']   = $segments[3] ?? null;
        $url_info['method']   = $segments[4] ?? "index";

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
            $cache_path = PATH_CACHE;
        }

        return $cache_path;
    }

    public function getFooter()
    {
        return ee()->cp->get_foot();
    }

    /**
     * Call the EE method for removing double slashes. Is specific to the EE version.
     * @return string result
     */
    public function reduce_double_slashes($string)
    {
        ee()->load->helper('string');
        return reduce_double_slashes($string);
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

        // Use our own instance of the query builder so we don't stomp over any in-progress queries.
        $settings_db = ee('db');

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
        return ee()->addons_model->module_installed($module);
    }

    public function assign_parameters($str, $defaults = array())
    {
        return ee()->functions->assign_parameters($str, $defaults);
    }
}
