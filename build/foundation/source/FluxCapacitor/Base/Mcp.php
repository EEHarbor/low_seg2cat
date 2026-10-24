<?php

namespace Low\Seg2Cat\FluxCapacitor\Base;

// This is the Addon McpNav, not the flux capacitor one
use Low\Seg2Cat\Conduit\McpNav as AddonNav;
// These are the Flux Capacitor classes
use Low\Seg2Cat\FluxCapacitor\Conduit\Version;
use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;

/**
 * EEHarbor mcp parent class
 *
 * @package         EEHarbor MCP Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

class Mcp
{
    public $flux;
    public $version;
    public $nav;

    public function __construct()
    {
        $this->flux = new FluxCapacitor();
        $this->version = $this->flux->getConfig('version');

        // If the addon has the nav class, lets generate a nav
        if ($this->canIncludeNav()) {
            $this->nav = new AddonNav();
        }
    }

    public function index()
    {
        return ee()->functions->redirect($this->flux->moduleURL('license'));
    }

    public function license()
    {
        $data = array();
        $data['ee_ver'] = substr(APP_VER, 0, 1);
        $data['action_url'] = $this->flux->moduleURL('save_license');

        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\Seg2Cat');
        $license_info = ee()->db->get('eeharbor_licenses', 1)->row();

        if (!empty($license_info)) {
            $data['license_key'] = $license_info->license_key;
            $data['ignore_site'] = intval($license_info->ignore_site);

            if ($data['ignore_site'] === 1) {
                $data['license_key'] = '';
            }
        } else {
            $data['license_key'] = '';
            $data['ignore_site'] = false;
        }

        return $this->flux->view('license', $data, true);//, $view_content);
    }

    public function save_license()
    {
        $version = new Version();

        $data['site_id'] = ee()->config->item('site_id');
        $data['addon'] = 'Low\Seg2Cat';
        $data['license_key'] = ee()->input->post('license_key', true);
        $data['license_key'] = preg_replace('/[^a-z0-9-]/i', '', $data['license_key']);
        $data['ignore_site'] = intval(ee()->input->post('ignore_site', true));

        if ($data['ignore_site'] === 1) {
            $data['license_key'] = str_replace('_', '-', 'low_seg2cat-ignore-site-') . $data['site_id'];
        }

        if (strlen($data['license_key']) > 50) {
            $this->flux->flashData('message_error', 'License Key Too Long');
            ee()->functions->redirect($this->flux->moduleURL('license'));
        }

        // Does this key already exist?
        ee()->db->where('license_key', $data['license_key']);
        $license_info = ee()->db->get('eeharbor_licenses')->row();

        // If the license key exists, check if it's for a different site or a different add-on.
        if (!empty($license_info)) {
            // Check if the site_id is the same, if not, redirect with an error.
            $site_id = $license_info->site_id;

            if ($site_id !== ee()->config->item('site_id')) {
                $msm_site_info = ee()->db->get_where('sites', array('site_id' => $site_id), 1)->row();
                $msm_site_name = 'Error: Could not locate MSM Site name.<p>If this MSM site is no longer installed, please remove your license key from the eeharbor_licenses database table.</p>';

                if (!empty($msm_site_info)) {
                    $msm_site_name = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $msm_site_info->site_label;
                }

                $this->flux->flashData('message_error', 'That License Key is already in use on one of your other MSM sites:<br /><br />' . $msm_site_name);
                ee()->functions->redirect($this->flux->moduleURL('license'));
                exit;
            }

            // If the license key is for another add-on, redirect with an error.
            if ($license_info->addon != 'Low\Seg2Cat') {
                // Format the namespaced name into a normal name.
                $addon_name = ucwords(preg_replace('/([A-Z])/', ' $1', str_replace('EEHarbor\\', '', $license_info->addon)));

                $this->flux->flashData('message_error', 'This license key is already being used for another installed add-on: ' . $addon_name);
                ee()->functions->redirect($this->flux->moduleURL('license'));
                exit;
            }
        }

        if ($version->keyExistsForAddon()) {
            $currentLicenseKey = $version->getLicenseKey();

            // If we're no longer ignoring the site but still have the ignored site key, delete the record from the DB.
            if ($data['ignore_site'] === 0 && empty($data['license_key']) && strpos($currentLicenseKey, '-ignore-site-') !== false) {
                $version->deleteLicenseKey();

                $this->flux->flashData('message_success', 'License Key Updated');
                ee()->functions->redirect($this->flux->moduleURL('license'));
            }
        }

        // If it exists, update it, otherwise, insert it.
        $exists = $version->saveLicenseKey($data);

        $this->flux->flashData('message_success', 'License Key Updated');
        ee()->functions->redirect($this->flux->moduleURL('license'));
        exit;
    }

    public function paginateModel($model, $url = null, $per_page = 25, $page_links = 5, $page_query_string = 'page')
    {
        // Current Page (or page = 1)
        $page = (int) ee()->input->get($page_query_string) ?: 1;

        // Check if the PHP version is less than 5.3.6.
        if (PHP_VERSION_ID < 50306) {
            $backtrace = debug_backtrace(false);
        } else {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        $lastUrl = false;

        if (!empty($backtrace) && is_array($backtrace) && !empty($backtrace[1]) && !empty($backtrace[1]['function'])) {
            $lastUrl = $this->flux->moduleURL($backtrace[1]['function']);
        }

        // Allow url to be null. This isnt able to handle parameters
        $url = $url ?: ($lastUrl ?: '');

        $newModel = clone $model;
        $total = $newModel->all()->count();

        // Set the pagination object to go back to the view
        $this->data['pagination'] = $this->flux->pagination(array(
            'per_page' => $per_page,
            'current_page' => $page,
            'total_rows' => $total,
            'base_url' => $url,
            'page_links' => $page_links,
            'page_query_string' => $page_query_string,
        ));

        return $model->limit($per_page)->offset($per_page * ($page - 1));
    }

    /**
     * Determines if we can include the sidenav. Some requests happen too
     * early and the required methods are not instantiated yet.
     *
     * @return boolean true/false
     */
    private function canIncludeNav()
    {
        if (defined('REQ') && constant('REQ') === 'CP') {
            if (file_exists(PATH_THIRD . 'low_seg2cat/Conduit/McpNav.php')) {
                @include_once PATH_THIRD . 'low_seg2cat/Conduit/McpNav.php';
            }
            if (class_exists("Low\Seg2Cat\Conduit\McpNav", true)) {
                return true;
            }
        }


        return false;
    }
}
