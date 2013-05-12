<?php

/**
 * Low Seg2Cat Extension class
 *
 * @package        low_seg2cat
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-seg2cat
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */

if ( ! defined('LOW_SEG2CAT_NAME'))
{
	define('LOW_SEG2CAT_NAME',    'Low Seg2Cat');
	define('LOW_SEG2CAT_PACKAGE', 'low_seg2cat');
	define('LOW_SEG2CAT_VERSION', '2.8.0');
	define('LOW_SEG2CAT_DOCS',    'http://gotolow.com/addons/low-seg2cat');
}

/**
 * < EE 2.6.0 backward compat
 */
if ( ! function_exists('ee'))
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

/**
 * NSM Addon Updater
 */
$config['name']    = LOW_SEG2CAT_NAME;
$config['version'] = LOW_SEG2CAT_VERSION;
$config['nsm_addon_updater']['versions_xml'] = LOW_SEG2CAT_DOCS.'/feed';