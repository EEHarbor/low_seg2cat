<?php
namespace Low\Seg2Cat\FluxCapacitor\Bridge;

/**
 * EEHarbor Abstract bridge class
 *
 * Bridges the functionality gaps between EE versions.
 *
 * @package         AbstractBridge
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

abstract class AbstractBridge
{
    private $ee_major_version;
    private $mb_available;
    public $shortname = "low_seg2cat";

    public function getEEVersion($major = true)
    {
        $this->ee_major_version = substr(APP_VER, 0, 1);

        if ($major == true) {
            return $this->ee_major_version;
        } else {
            return APP_VER;
        }
    }

    // Version compare helper functions
    public function ver_lt($version)
    {
        return version_compare($this->getEEVersion(), $version, '<');
    }

    public function ver_gt($version)
    {
        return version_compare($this->getEEVersion(), $version, '>');
    }

    public function ver_lte($version)
    {
        return version_compare($this->getEEVersion(), $version, '<=');
    }

    public function ver_gte($version)
    {
        return version_compare($this->getEEVersion(), $version, '>=');
    }

    public function is_ee2()
    {
        return version_compare($this->getEEVersion(), 2, '==');
    }

    public function is_ee3()
    {
        return version_compare($this->getEEVersion(), 3, '==');
    }

    public function is_ee4()
    {
        return version_compare($this->getEEVersion(), 4, '==');
    }

    public function is_ee5()
    {
        return version_compare($this->getEEVersion(), 5, '==');
    }

    // Force Extending class to define these methods
    abstract public function instantiate($which);
    abstract public function getBaseURL($method = '', $extra = '');
    abstract public function getNav($nav_items = array(), $buttons = array(), $active_map = array());
    abstract public function cpURL($path, $mode = '', $variables = array());
    abstract public function moduleURL($method = 'index', $variables = array());
    abstract public function view($view, $vars = array(), $return = false, $string = false);
    abstract public function getCurrentPage($options = array());
    abstract public function getStartNum($options);
    abstract public function pagination($options = array());
    abstract public function getSettings($asArray = false);
    abstract public function getConfig($item);
    abstract public function setConfig($item, $value);
    abstract public function cache($mode, $key = false, $data = false);
    abstract public function flashData($type = 'message_success', $title = '', $body = '', $extra_parameters = array());
    abstract public function getAddonThemesDir();
    abstract public function overwriteEEClass($class, $data = '');
    abstract public function removeEEClass($class);
    abstract public function xss_clean($input);
    abstract public function getCurrentUrlInfo($options = null);
    abstract public function getCachePath();
    abstract public function getFooter();
    abstract public function reduce_double_slashes($string);
    abstract public function javascript_to_page($js);
    abstract public function module_installed($module);
    abstract public function assign_parameters($str, $defaults = array());
    abstract public function assign_variables($str);
}
