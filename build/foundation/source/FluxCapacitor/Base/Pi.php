<?php
namespace Low\Seg2Cat\FluxCapacitor\Base;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;

/**
 * EEHarbor Pi parent class
 *
 * @package         EEHarbor plugin Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

class Pi
{
    public $flux;
    public $version;

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->version = $this->flux->getConfig('version');
    }

    /**
     * Return the plugin metadata through a fresh bridge.
     *
     * @return array
     */
    public static function info()
    {
        $flux = new FluxCapacitor;

        return array(
            'pi_name'           => $flux->getConfig('name'),
            'pi_version'        => $flux->getConfig('version'),
            'pi_author'         => 'EEHarbor',
            'pi_author_url'     => 'https://eeharbor.com',
            'pi_description'    => $flux->getConfig('description')
        );
    }
}
