<?php
/**
 * Low Seg2Cat Extension class
 *
 * @package         low-seg2cat-ee2_addon
 * @author          Lodewijk Schutte ~ Low <hi@gotolow.com>
 * @link            http://gotolow.com/addons/low-seg2cat
 * @license         http://creativecommons.org/licenses/by-sa/3.0/
 */

if ( ! defined('LOW_SEG2CAT_NAME'))
{
	define('LOW_SEG2CAT_NAME',       'Low Seg2Cat');
	define('LOW_SEG2CAT_CLASS_NAME', 'Low_seg2cat');
	define('LOW_SEG2CAT_VERSION',    '2.6.1');
	define('LOW_SEG2CAT_DOCS',       'http://gotolow.com/addons/low-seg2cat');
}

$config['name']    = LOW_SEG2CAT_NAME;
$config['version'] = LOW_SEG2CAT_VERSION;

$config['nsm_addon_updater']['versions_xml'] = LOW_SEG2CAT_DOCS.'feed/';