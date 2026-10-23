<?php

/**
 * Inventory named methods and closures without executing a source unit.
 *
 * @param string $source
 * @return array
 */
function inventoryFunctions($source)
{
    $functions = array();
    $tokens = token_get_all($source);
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $function = '{closure}';
        for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_STRING) {
                $function = $tokens[$cursor][1];
                break;
            }
            if ($tokens[$cursor] === '(') {
                break;
            }
        }
        $functions[] = array('name' => $function, 'line' => $token[2]);
    }
    return $functions;
}

$root = dirname(__DIR__, 2);
$directory = $root . '/low_seg2cat';
$files = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Symlinks are not accepted as production inventory.');
    }
    if ($file->getExtension() === 'php') {
        $files[$file->getPathname()] = substr($file->getPathname(), strlen($root) + 1);
    }
}
$archive = new Phar($directory . '/low_seg2cat.phar');
foreach (new RecursiveIteratorIterator($archive) as $file) {
    if (substr($file->getPathname(), -4) === '.php') {
        $files[$file->getPathname()] = 'low_seg2cat/low_seg2cat.phar::' . substr($file->getPathname(), strpos($file->getPathname(), '.phar/') + 6);
    }
}
ksort($files);
$inventory = array();
foreach ($files as $path => $name) {
    $source = file_get_contents($path);
    $inventory[$name] = array('sha256' => hash('sha256', $source), 'bytes' => strlen($source), 'functions' => inventoryFunctions($source));
}
$stub = $archive->getStub();
$inventory['low_seg2cat/low_seg2cat.phar::{stub}'] = array('sha256' => hash('sha256', $stub), 'bytes' => strlen($stub),
    'archive_sha256' => hash_file('sha256', $directory . '/low_seg2cat.phar'), 'functions' => inventoryFunctions($stub),
    'note' => 'Executable PHAR stub is also in scope.');
echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
