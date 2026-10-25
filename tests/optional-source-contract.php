<?php

$archive = $argv[1];
$module = $argv[2];
$namespace = $argv[3];
$kind = $argv[4];
$readable = $argv[5] === 'readable';
$root = sys_get_temp_dir() . '/optional-source-' . bin2hex(random_bytes(8));
define('PATH_THIRD', $root . '/third/');
$addon = PATH_THIRD . $module . '/';
mkdir($addon . 'Conduit', 0700, true);
$file = $kind === 'navigation' ? $addon . 'Conduit/McpNav.php' : $addon . 'ext.' . $module . '.php';
file_put_contents($file, '<?php $GLOBALS["owned_optional_loaded"] = true;');
chmod($file, $readable ? 0600 : 0000);
try {
    $member = $kind === 'navigation' ? 'Conduit/McpNav' : 'Base/Upd';
    require 'phar://' . $archive . '/FluxCapacitor/' . $member . '.php';
    $result = true;
    if ($kind === 'updater') {
        $class = $namespace . '\\FluxCapacitor\\Base\\Upd';
        $updater = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $updater->version = '2.0';
        $updater->module_name = 'OwnedAbsent';
        $result = $updater->update('1.0');
    }
    echo json_encode(array('readable' => is_readable($file), 'optional_loaded' => isset($GLOBALS['owned_optional_loaded']), 'result' => $result)), "\n";
} finally {
    chmod($file, 0600);
    unlink($file);
    rmdir($addon . 'Conduit');
    rmdir($addon);
    rmdir(PATH_THIRD);
    rmdir($root);
}
