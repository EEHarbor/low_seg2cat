<?php
/**
 * Main EE class
 *
 * At this point we get our hands dirty for the greater good.
 *
 * @package         EE
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

// We can declare ee() in the global namespace here:
namespace {

    if (!function_exists('ee')) {
        function ee()
        {
            return get_instance();
        }
    }
}

namespace Low\Seg2Cat\FluxCapacitor\Bridge{

    use Low\Seg2Cat\FluxCapacitor\Bridge\EE2;
    use Low\Seg2Cat\FluxCapacitor\Bridge\EE3;
    use Low\Seg2Cat\FluxCapacitor\Bridge\EE4;
    use Low\Seg2Cat\FluxCapacitor\Bridge\EE5;
    use Low\Seg2Cat\FluxCapacitor\Bridge\EE6;

    if (! defined('APP_VER')) {
        define('APP_VER', ee()->config->item('app_version'));
    }

    $ee_version = substr(APP_VER, 0, 1);

    if ($ee_version === '2') {
        class EE extends EE2
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    } elseif ($ee_version === '3') {
        class EE extends EE3
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    } elseif ($ee_version === '4') {
        class EE extends EE4
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    } elseif ($ee_version === '5') {
        class EE extends EE5
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    } elseif ($ee_version === '6') {
        class EE extends EE6
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    } else {
        class EE extends EE6
        {
            public function __construct($params = null)
            {
                parent::__construct($params);
            }
        }
    }
}
