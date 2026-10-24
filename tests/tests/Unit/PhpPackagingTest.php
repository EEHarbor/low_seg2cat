<?php

namespace Tests;

class PhpPackagingTest extends TestCase
{
    /**
     * Preserve shipped packaging and the original license flag through the scoped build.
     *
     * @return void
     */
    public function testOriginalPackagingAndProcessedSource(): void
    {
        $build = dirname(__DIR__, 3) . '/build/foundation/';
        $original = json_decode(file_get_contents($build . 'provenance.json'), true);
        $archive = new \Phar(PATH_THIRD . 'low_seg2cat/low_seg2cat.phar');
        $members = array_merge($original['members'], $original['new_members']);
        self::assertSame($original['alias'], $archive->getAlias());
        self::assertSame($original['metadata'], $archive->getMetadata());
        self::assertSame($original['signature']['hash_type'], $archive->getSignature()['hash_type']);
        self::assertCount(count($members), $archive);
        self::assertSame(file_get_contents($build . 'stub.php'), $archive->getStub());
        preg_match('/const LEN = ([0-9]+);/', $archive->getStub(), $length);
        self::assertSame(strlen($archive->getStub()), (int) $length[1]);
        self::assertTrue(\Low\Seg2Cat\FluxCapacitor\FluxCapacitor::L);
        $modes = $this->manifestModes(PATH_THIRD . 'low_seg2cat/low_seg2cat.phar', strlen($archive->getStub()));
        foreach ($members as $name => $member) {
            self::assertSame(file_get_contents($build . 'source/' . $name), $archive[$name]->getContent());
            self::assertSame($member['permissions'], $modes[$name], $name);
            self::assertSame($member['compression'], $archive[$name]->isCompressed(), $name);
            self::assertSame($member['metadata'], $archive[$name]->getMetadata(), $name);
        }
        self::assertSame($original['members']['FluxCapacitor/Conduit/Version.php']['sha256'], hash('sha256', $archive['FluxCapacitor/Conduit/Version.php']->getContent()));
    }
    /**
     * Read raw permission flags without Phar's read-only write-bit masking.
     *
     * @param string $path Distributed archive path.
     * @param int $stubLength Exact executable stub byte length.
     * @return array Member names mapped to all nine stored permission bits.
     */
    private function manifestModes($path, $stubLength): array
    {
        $bytes = file_get_contents($path);
        $cursor = $stubLength + 4;
        $count = unpack('V', substr($bytes, $cursor, 4))[1];
        $cursor += 10;
        $aliasLength = unpack('V', substr($bytes, $cursor, 4))[1];
        $cursor += 4 + $aliasLength;
        $metadataLength = unpack('V', substr($bytes, $cursor, 4))[1];
        $cursor += 4 + $metadataLength;
        $modes = array();
        for ($index = 0; $index < $count; $index++) {
            $length = unpack('V', substr($bytes, $cursor, 4))[1];
            $cursor += 4;
            $name = substr($bytes, $cursor, $length);
            $cursor += $length;
            $flags = unpack('V', substr($bytes, $cursor + 16, 4))[1];
            $metadataLength = unpack('V', substr($bytes, $cursor + 20, 4))[1];
            $cursor += 24 + $metadataLength;
            $modes[$name] = $flags & 0777;
        }
        return $modes;
    }

    /**
     * Rebuild in a complete owned directory without changing the distributed artifact.
     *
     * @return void
     */
    public function testOwnedBuildRetainsAllDistributedMembers(): void
    {
        $base = sys_get_temp_dir() . '/low-seg2cat-build-control-' . bin2hex(random_bytes(8));
        $source = LOW_SEG2CAT_ROOT . '/build/foundation';
        mkdir($base . '/build/foundation', 0700, true);
        mkdir($base . '/low_seg2cat', 0700);
        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $target = $base . '/build/foundation/' . substr($file->getPathname(), strlen($source) + 1);
                if ($file->isDir()) {
                    mkdir($target, 0700);
                    continue;
                }
                copy($file->getPathname(), $target);
            }
            $result = \Tests\Support\Process::execute(array(PHP_BINARY, '-d', 'phar.readonly=0',
                '-d', 'auto_prepend_file=' . LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php', $base . '/build/foundation/build.php'));
            self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            self::assertSame('', $result['stderr']);
            $code = '<?php require ' . var_export(LOW_SEG2CAT_ROOT . '/tests/Support/diagnostics.php', true) . '; '
                . '$archive = new Phar(' . var_export($base . '/low_seg2cat/low_seg2cat.phar', true) . '); $hashes = array(); '
                . 'foreach (new RecursiveIteratorIterator($archive) as $file) { $name = substr($file->getPathname(), strlen("phar://" . $archive->getPath() . "/")); $hashes[$name] = hash("sha256", $file->getContent()); } '
                . 'echo json_encode(array("members" => $hashes, "alias" => $archive->getAlias(), "signature" => $archive->getSignature()["hash_type"], "stub_length" => strlen($archive->getStub())));';
            file_put_contents($base . '/verify.php', $code);
            $result = \Tests\Support\Process::execute(array(PHP_BINARY, $base . '/verify.php'));
            self::assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            self::assertSame('', $result['stderr']);
            $built = json_decode($result['stdout'], true);
            self::assertSame('low_seg2cat.phar', $built['alias']);
            self::assertSame('SHA-256', $built['signature']);
            $provenance = json_decode(file_get_contents($source . '/provenance.json'), true);
            $members = array_merge($provenance['members'], $provenance['new_members']);
            self::assertCount(count($members), $built['members']);
            $modes = $this->manifestModes($base . '/low_seg2cat/low_seg2cat.phar', $built['stub_length']);
            foreach ($members as $name => $member) {
                self::assertSame(hash_file('sha256', $source . '/source/' . $name), $built['members'][$name], $name);
                self::assertSame($member['permissions'], $modes[$name], $name);
            }
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                    continue;
                }
                unlink($file->getPathname());
            }
            rmdir($base);
        }
    }

}
