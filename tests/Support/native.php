<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('LOW_SEG2CAT_ROOT', dirname(__DIR__, 2));
if (getenv('LOW_SEG2CAT_DIRECT_ACCESS') !== '1') {
    define('BASEPATH', LOW_SEG2CAT_ROOT . '/tests/');
}
define('PATH_THIRD', getenv('LOW_SEG2CAT_MISSING_PHAR') === '1'
    ? sys_get_temp_dir() . '/missing-third/' : LOW_SEG2CAT_ROOT . '/');
define('PATH_CACHE', sys_get_temp_dir() . '/low-seg2cat-tests-' . getmypid() . '-' . bin2hex(random_bytes(8)) . '/');
define('SYSPATH', getenv('LOW_SEG2CAT_FT_SYSTEM_PATH') ?: LOW_SEG2CAT_ROOT . '/tests/fixtures/');
define('APPPATH', getenv('LOW_SEG2CAT_FT_APP_PATH') ?: LOW_SEG2CAT_ROOT . '/tests/fixtures/');
define('APP_VER', getenv('LOW_SEG2CAT_EE_VERSION') ?: '7.5.0');
define('REQ', getenv('LOW_SEG2CAT_REQUEST_TYPE') ?: 'PAGE');
define('BASE', 'cp.php?S=0');
define('AMP', '&amp;');
define('FILE_WRITE_MODE', 0600);
define('DIR_WRITE_MODE', 0700);
require_once __DIR__ . '/Environment.php';
