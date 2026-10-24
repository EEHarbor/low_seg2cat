<?php

namespace Low\Seg2Cat\FluxCapacitor;

use Low\Seg2Cat\FluxCapacitor\Bridge\EE;

/**
 * Flux Capacitor
 *
 * @package         Flux Capacitor
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class FluxCapacitor extends EE
{
    const L = true;

    public function __construct()
    {
        $params = array("module" => "low_seg2cat", "module_name" => "Low Seg2Cat");
        parent::__construct($params);
    }
}
