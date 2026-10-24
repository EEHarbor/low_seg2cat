<?php
namespace Low\Seg2Cat\FluxCapacitor\Base;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;
use Low\Seg2Cat\FluxCapacitor\Conduit\Version;
use Low\Seg2Cat\FluxCapacitor\Conduit\GlobalFieldSettings;

if (@file_exists(SYSPATH . 'ee/legacy/fieldtypes/EE_Fieldtype.php')) {
    require_once SYSPATH . 'ee/legacy/fieldtypes/EE_Fieldtype.php';
} elseif (@file_exists(APPPATH . 'fieldtypes/EE_Fieldtype.php')) {
    require_once APPPATH . 'fieldtypes/EE_Fieldtype.php';
} elseif (defined('APPPATH') && strpos(APPPATH, 'installer') !== false) {
    $realAppPath = str_replace('installer', 'expressionengine', APPPATH);

    if (@file_exists($realAppPath . 'fieldtypes/EE_Fieldtype.php')) {
        require_once $realAppPath . 'fieldtypes/EE_Fieldtype.php';
    }
}

if (!defined('Low\Seg2Cat\VERSION')) {
    $addonJson = json_decode(file_get_contents(PATH_THIRD . 'low_seg2cat/addon.json'));
    define('Low\Seg2Cat\VERSION', $addonJson->version);
}

/**
 * EEHarbor ft parent class
 *
 * @package         EEHarbor Fieldtype Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

abstract class Ft extends \EE_Fieldtype
{
    public $flux;
    public $rows = array();
    public $content_types = array('channel');
    public $GlobalFieldSettings;
    public $version;

    public $info = array(
        'name'    => 'Low Seg2Cat',
        'version' => \Low\Seg2Cat\VERSION
    );

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->GlobalFieldSettings = new GlobalFieldSettings;
        $this->version = $this->flux->getConfig('version');

        if (defined('REQ') && REQ === 'CP') {
            $version = new Version;
            $version->check();
        }
    }

    public function update($version='')
    {
        return true;
    }

    public function accepts_content_type($name)
    {
        return in_array($name, $this->content_types);
    }

    /**
     * Resolve the channel from settings, request, entry or create route.
     *
     * @return int|string|false
     */
    public function getChannelId()
    {
        // If ee2, this is the Channel ID
        if ($this->flux->is_ee2()) {
            return ee()->input->get_post('channel_id');
        }

        // Get the entry_id so we know if we can have a channel yet
        $entry_id = $this->content_id();

        // Check the settings, and the get/post for the channel_id
        $channel_id = (isset($this->settings['field_channel_id']))
             ? $this->settings['field_channel_id']
             : ee()->input->get_post('channel_id');

        // If we still dont have it, but we do have a channel entry, use that to get the channel_id
        if (! $channel_id && $entry_id) {
            $channel_entry = ee('Model')->get('ChannelEntry')->filter('entry_id', $entry_id)->first();
            $channel_id = $channel_entry ? $channel_entry->channel_id : false;
        }

        // This is most likely due to being on the create entry screen
        if (! $channel_id) {
            // We are on the publish/create page
            if (strpos(uri_string(), 'cp/publish/create/') !== false) {
                $channel_id = (int) str_replace("cp/publish/create/", "", uri_string());
            }
        }

        return $channel_id;
    }

    public function displaySettingsRow($title, $data, $desc = '', $wide = false)
    {
        // different things for different versions of ee
        if ($this->flux->is_ee2()) {
            if (!$wide) {
                ee()->table->add_row($title, $data);
            } else {
                ee()->table->add_row(array(
                    'colspan' => '2',
                    'data'    => $title . $data,
                ));
            }
        } else {
            $this->rows[] = array(
                'title'  => $title,
                'desc'   => $desc,
                'wide'   => $wide,
                'fields' => array(
                    array(
                        'type'    => 'html',
                        'content' => $data,
                    ),
                ),
            );
        }
    }

    public function displaySettingsReturn($field_options=null, $group=null, $label = 'field_options')
    {
        $field_options = $field_options ?: 'field_options_' . $this->flux->shortname;
        $group = $group ?: $this->flux->shortname;

        if ($this->flux->is_ee2()) {
            return true;
        } else {
            return array(
                $field_options => array(
                    'label'    => $label,
                    'group'    => $group,
                    'settings' => $this->rows,
                ),
            );
        }
    }

    // This usage is deprecated
    public function _display_settings_add_row($title, $data, $desc = '', $wide = false)
    {
        return $this->displaySettingsRow($title, $data, $desc, $wide);
    }

    // This usage is deprecated
    public function _package_display_settings($field_options=null, $group=null, $label = 'field_options')
    {
        return $this->displaySettingsReturn($field_options, $group, $label);
    }
}
