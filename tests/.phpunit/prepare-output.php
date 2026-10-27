<?php

$source = __DIR__ . '/phpunit-9.phar';
$target = __DIR__ . '/phpunit-9-output.phar';
$stamp = $target . '.json';
$expected = '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7';
$member = 'phpunit/Framework/TestCase.php';
$anchor = '        $this->output = ob_get_contents();';
$hook = ' \\LowSeg2CatNativeInvocation::caseOutput($this, $this->output, \'original\');';
$callback = '            $this->output = (string) call_user_func($this->outputCallback, $this->output);';
$callbackHook = ' \\LowSeg2CatNativeInvocation::caseOutput($this, \\ob_get_contents(), \'callback\');';
if (is_link($source) || hash_file('sha256', $source) !== $expected) {
    throw new RuntimeException('Pinned PHPUnit source checksum mismatch.');
}
$recipe = hash_file('sha256', __FILE__);
if (!file_exists($target) && !file_exists($stamp)) {
    if (!copy($source, $target) || hash_file('sha256', $target) !== $expected) {
        throw new RuntimeException('Cannot copy the complete pinned PHPUnit tool.');
    }
    $archive = new Phar($target);
    $before = $archive[$member]->getContent();
    if (substr_count($before, $anchor) !== 1 || substr_count($before, $callback) !== 1) {
        throw new RuntimeException('Pinned PHPUnit output boundary changed.');
    }
    $baseline = array();
    foreach (new RecursiveIteratorIterator($archive) as $entry) {
        $path = substr($entry->getPathname(), strlen('phar://' . $target . '/'));
        $baseline[$path] = array('sha256' => hash('sha256', $entry->getContent()),
            'mode' => $entry->getPerms() & 0777, 'compression' => $entry->isCompressed(),
            'metadata' => $entry->getMetadata());
    }
    $envelope = array($archive->getStub(), $archive->getAlias(), $archive->getSignature()['hash_type'], $archive->getMetadata());
    $after = str_replace(array($anchor, $callback), array($anchor . $hook, $callback . $callbackHook), $before);
    $archive[$member] = $after;
    foreach ($baseline as $path => $identity) {
        $entry = $archive[$path];
        if (hash('sha256', $entry->getContent()) !== ($path === $member ? hash('sha256', $after) : $identity['sha256'])
            || ($entry->getPerms() & 0777) !== $identity['mode'] || $entry->isCompressed() !== $identity['compression']
            || $entry->getMetadata() !== $identity['metadata']) {
            throw new RuntimeException('Derived PHPUnit changed another member: ' . $path);
        }
    }
    if ($envelope !== array($archive->getStub(), $archive->getAlias(), $archive->getSignature()['hash_type'], $archive->getMetadata())) {
        throw new RuntimeException('Derived PHPUnit envelope differs.');
    }
    unset($archive);
    $record = array('original_sha256' => $expected, 'recipe_sha256' => $recipe, 'member' => $member,
        'member_before_sha256' => hash('sha256', $before), 'member_after_sha256' => hash('sha256', $after),
        'snapshot_stages' => array('original', 'callback'),
        'original_member_count' => count($baseline), 'original_manifest_sha256' => hash('sha256', serialize($baseline)),
        'artifact_sha256' => hash_file('sha256', $target));
    if (file_put_contents($stamp, json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Cannot retain the derived tool identity.');
    }
}
if (is_link($target) || is_link($stamp) || !is_file($target) || !is_file($stamp)) {
    throw new RuntimeException('Derived PHPUnit tool identity is incomplete.');
}
$record = json_decode(file_get_contents($stamp), true, 512, JSON_THROW_ON_ERROR);
$archive = isset($archive) ? $archive : new Phar($target);
$after = $archive[$member]->getContent();
$before = str_replace(array($anchor . $hook, $callback . $callbackHook), array($anchor, $callback), $after);
if ($record['original_sha256'] !== $expected || $record['recipe_sha256'] !== $recipe || $record['member'] !== $member
    || $record['artifact_sha256'] !== hash_file('sha256', $target) || $record['original_member_count'] !== count($archive)
    || $record['snapshot_stages'] !== array('original', 'callback')
    || substr_count($after, $anchor . $hook) !== 1 || substr_count($after, $callback . $callbackHook) !== 1
    || $record['member_before_sha256'] !== hash('sha256', $before) || $record['member_after_sha256'] !== hash('sha256', $after)) {
    throw new RuntimeException('Preserve the previous derived tool before changing its recipe.');
}
echo json_encode($record, JSON_THROW_ON_ERROR), "\n";
