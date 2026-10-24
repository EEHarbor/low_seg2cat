<?php

/**
 * Rebuild this add-on's distributed archive from its scoped Foundation sources.
 *
 * The processed sources and buildFromDirectory packaging follow PacketTide tools at
 * 11a2ebabc92280b424d7aac76e78ac689eb29df7. Keep the shipped validation script
 * and extraction stub so rebuilding does not change licensing or fallback IO.
 */
$root = dirname(__DIR__, 2);
$provenance = json_decode(file_get_contents(__DIR__ . '/provenance.json'), true);
$members = array_merge($provenance['members'], $provenance['new_members']);
$temporary = sys_get_temp_dir() . '/low_seg2cat-build-' . bin2hex(random_bytes(10));
mkdir($temporary, 0700);
mkdir($temporary . '/contents', 0700);
try {
    $source = __DIR__ . '/source/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isLink() || !$file->isFile() || $file->getExtension() !== 'php') {
            throw new RuntimeException('Unexpected Foundation build input: ' . $file->getPathname());
        }
        $relative = substr($file->getPathname(), strlen($source));
        $target = $temporary . '/contents/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        $bytes = file_get_contents($file->getPathname());
        if (strpos($bytes, '@@@') !== false) {
            throw new RuntimeException('Unresolved Foundation build token: ' . $relative);
        }
        file_put_contents($target, $bytes);
        chmod($target, $members[$relative]['permissions']);
    }
    $archive = $temporary . '/low_seg2cat.phar';
    $phar = new Phar($archive, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'low_seg2cat.phar');
    $phar->buildFromDirectory($temporary . '/contents');
    $stub = file_get_contents(__DIR__ . '/stub.php');
    if (!preg_match('/\bconst LEN = ([0-9]+);/', $stub, $length) || (int) $length[1] !== strlen($stub)) {
        throw new RuntimeException('Extraction stub manifest offset does not match its byte length.');
    }
    $phar->setStub($stub);
    $phar->setSignatureAlgorithm(Phar::SHA256);
    if (count($phar) !== count($members) || $phar->getAlias() !== $provenance['alias']) {
        throw new RuntimeException('Foundation archive layout changed unexpectedly.');
    }
    foreach ($members as $name => $member) {
        $expected = file_get_contents(__DIR__ . '/source/' . $name);
        if ($phar[$name]->getContent() !== $expected
            || ($phar[$name]->getPerms() & 0777) !== $member['permissions']
            || $phar[$name]->isCompressed() !== $member['compression']
            || $phar[$name]->getMetadata() !== $member['metadata']) {
            throw new RuntimeException('Foundation member verification failed: ' . $name);
        }
    }
    unset($phar);
    if (!rename($archive, $root . '/low_seg2cat/low_seg2cat.phar')) {
        throw new RuntimeException('Could not publish the built Low Seg2Cat archive.');
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
            continue;
        }
        unlink($file->getPathname());
    }
    rmdir($temporary);
}
echo "Built low_seg2cat/low_seg2cat.phar\n";
