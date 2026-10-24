<?php
namespace Low\Seg2Cat\FluxCapacitor\Base;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;

/**
 * EEHarbor mod parent class
 *
 * @package         EEHarbor Module Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

class Mod
{
    public $flux;
    public $version;

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->version = $this->flux->getConfig('version');
    }

    /**
     * Access template params with a fallback
     * @param $parameter
     * @param string $fallback
     * @return mixed|string
     * @internal param $key
     * @internal param string $default_value
     */
    protected function getParameter($parameter, $fallback = null, $trim=true)
    {
        $val = ee()->TMPL->fetch_param($parameter);

        // Trim the whitespaces on the parameter, to prevent a lot of potential headache
        if($trim) {
            $val = trim(str_replace(array('&nbsp;', '&#32;'), array(' ', ' '), $val));
        }

        if (! $val) {
            return $fallback;
        }

        return $val;
    }
}
