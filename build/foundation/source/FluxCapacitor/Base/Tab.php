<?php
namespace Low\Seg2Cat\FluxCapacitor\Base;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;
use Low\Seg2Cat\FluxCapacitor\Conduit\Version;

/**
 * EEHarbor tab parent class
 *
 * @package         EEHarbor Tab Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

class Tab
{
    public $flux;
    public $version;

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->version = $this->flux->getConfig('version');

        if (defined('REQ') && REQ === 'CP') {
            $version = new Version;
            $version->check();
        }
    }

    // New display function
    public function display($channel_id, $entry_id = '')
    {
        return $this->publish_tabs($channel_id, $entry_id);
    }

    // Old display function
    public function publish_tabs($channel_id, $entry_id = '')
    {
        return array();
    }

    // New save function
    public function save($channel_entry, $params)
    {
        return $this->publish_data_db($params, $channel_entry);
    }

    // Old save function
    public function publish_data_db($params, $channel_entry=null)
    {
        return true;
    }

    public function getEntryVariables($channel_entry, $params)
    {
        $variables = array();

        if (isset($channel_entry)) {
            $variables['entry_id'] = $channel_entry->entry_id;
            $variables['channel_id'] = $channel_entry->channel_id;
            $variables['site_id'] = $channel_entry->site_id;
        } elseif (isset($params['entry_id'])) {
            $variables['entry_id'] = $params['entry_id'];
            $variables['channel_id'] = $params['meta']['channel_id'];
            $variables['site_id'] = $params['meta']['site_id'];
        } else {
            $variables['entry_id'] = 0;
            $variables['channel_id'] = 0;
            $variables['site_id'] = 0;
        }

        return $variables;
    }
}
