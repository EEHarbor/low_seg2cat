<?php

namespace Tests;

use Tests\Support\Process;

class NativeStubHelperTest extends TestCase
{
    /**
     * Reject unusable temporary/session storage before extraction writes begin.
     *
     * @dataProvider unavailableSessionPaths
     * @param string $kind
     * @return void
     */
    public function testOriginalLoaderReportsUnavailableTemporaryStorage($kind)
    {
        $code = 'require LOW_SEG2CAT_ROOT . "/low_seg2cat/low_seg2cat.phar"; ' .
            '$root = sys_get_temp_dir(); putenv("TMPDIR=" . $root . "/missing-temp"); ';
        if ($kind === 'file') {
            $code .= 'file_put_contents($root . "/session-file", "owned"); $session = $root . "/session-file"; ';
        } else {
            $code .= '$session = $root . "/missing-session"; ';
        }
        if ($kind === 'prefixed') {
            $code .= '$session = "1;" . $session; ';
        }
        $result = Process::probe($code . 'session_save_path($session); \Extract_low_seg2cat::go(true); echo "continued";');
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('', $result['stderr']);
        $this->assertSame('Could not locate temporary directory to extract phar', $result['stdout']);
    }

    /**
     * Include missing, non-directory and supported semicolon-prefixed session paths.
     *
     * @return array
     */
    public static function unavailableSessionPaths()
    {
        return array('missing' => array('missing'), 'file' => array('file'), 'prefixed' => array('prefixed'));
    }

    /**
     * Decode the original manifest and compare every file with PHP's native PHAR reader.
     *
     * @return void
     */
    public function testOriginalManifestAndExtractionMatchTheNativeArchiveReader()
    {
        $code = <<<'CODE'
$path = LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
require $path;
$archive = new \Phar($path);
$fp = fopen($path, 'rb');
fseek($fp, \Extract_low_seg2cat::LEN);
$length = unpack('V', fread($fp, 4));
$manifest = fread($fp, $length[1]);
$info = \Extract_low_seg2cat::_unpack($manifest);
$start = ftell($fp);
$matches = array();
foreach ($info['m'] as $name => $entry) {
    if (substr($name, -1) === '/') {
        continue;
    }
    fseek($fp, $start + $entry[7]);
    $actual = \Extract_low_seg2cat::extractFile($name, $entry, $fp);
    $matches[$name] = hash('sha256', $actual) === hash('sha256', $archive[$name]->getContent());
}
fclose($fp);
echo json_encode(array('files' => count($matches), 'expected' => iterator_count(new \RecursiveIteratorIterator($archive)),
    'all_match' => !in_array(false, $matches, true), 'flags' => $info['c']));
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertGreaterThan(0, $data['files']);
        $this->assertSame($data['expected'], $data['files']);
        $this->assertTrue($data['all_match']);
    }

    /**
     * Return the configured temporary directory or the platform fallback when absent.
     *
     * @return void
     */
    public function testTemporaryDirectoryUsesEnvironmentThenPlatformFallback()
    {
        $code = <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$configured = getenv('TMPDIR');
$actual = \Extract_low_seg2cat::tmpdir();
putenv('TMPDIR');
echo json_encode(array('configured' => $configured, 'actual' => $actual,
    'exists' => is_dir($actual), 'fallback' => \Extract_low_seg2cat::tmpdir(), 'expected_fallback' => realpath('/tmp')));
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame($data['configured'], $data['actual']);
        $this->assertTrue($data['exists']);
        $this->assertSame($data['expected_fallback'], $data['fallback']);
    }

    /**
     * Extract valid empty, chunked, and compressed data using the original stub method.
     *
     * @return void
     */
    public function testFileExtractionPreservesEveryPayloadByte()
    {
        $payloads = self::filePayloads();
        $code = '$payloads=' . var_export($payloads, true) . '; ';
        $code .= <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$results = array();
foreach ($payloads as $name => $payload) {
    list($plain, $encoded, $flags) = $payload;
    $fp = tmpfile();
    fwrite($fp, $encoded);
    rewind($fp);
    $entry = array(strlen($plain), 0, strlen($encoded), sprintf('%u', crc32($plain) & 0xffffffff), $flags, 0);
    $actual = \Extract_low_seg2cat::extractFile('record', $entry, $fp);
    fclose($fp);
    $results[$name] = array('hash' => hash('sha256', $actual), 'length' => strlen($actual));
}
echo json_encode($results);
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame(array_keys($payloads), array_keys($data));
        foreach ($payloads as $name => $payload) {
            $this->assertSame(hash('sha256', $payload[0]), $data[$name]['hash'], $name);
            $this->assertSame(strlen($payload[0]), $data[$name]['length'], $name);
        }
    }

    /**
     * Supply plain and both supported compression formats, including the read boundary.
     *
     * @return array
     */
    public static function filePayloads()
    {
        $plain = str_repeat('record-data-', 1000);
        return array(
            'empty' => array('', '', 0),
            'short' => array('record', 'record', 0),
            'exact read boundary' => array(str_repeat('x', 8192), str_repeat('x', 8192), 0),
            'one past read boundary' => array(str_repeat('x', 8193), str_repeat('x', 8193), 0),
            'multiple chunks' => array($plain, $plain, 0),
            'gzip' => array($plain, gzdeflate($plain), 0x1000),
            'bzip2' => array($plain, bzcompress($plain), 0x2000),
        );
    }

    /**
     * Remove owned empty directories and files while restoring the caller's directory.
     *
     * @return void
     */
    public function testSimpleTemporaryTreesAreRemoved()
    {
        $kinds = array('empty', 'file', 'empty-directory');
        $code = '$kinds=' . var_export($kinds, true) . '; ';
        $code .= <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$results = array();
foreach ($kinds as $kind) {
    $root = sys_get_temp_dir() . '/stub-owned-' . $kind;
    mkdir($root, 0700);
    if ($kind === 'file') {
        file_put_contents($root . '/record', 'value');
    }
    if ($kind === 'empty-directory') {
        mkdir($root . '/empty', 0700);
    }
    $original = getcwd();
    \Extract_low_seg2cat::_removeTmpFiles($root, $original);
    $results[$kind] = array('removed' => !file_exists($root), 'restored' => getcwd() === $original);
}
echo json_encode($results);
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame($kinds, array_keys($data));
        foreach ($kinds as $kind) {
            $this->assertSame(array('removed' => true, 'restored' => true), $data[$kind], $kind);
        }
    }

    /**
     * Remove a populated subtree without a hidden warning before recursive descent.
     *
     * @return void
     */
    public function testPopulatedTemporarySubtreesAreRemovedWithoutWarnings()
    {
        $code = <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$root = sys_get_temp_dir() . '/stub-owned';
mkdir($root . '/nested', 0700, true);
file_put_contents($root . '/nested/record', 'value');
$original = getcwd();
\Extract_low_seg2cat::_removeTmpFiles($root, $original);
echo json_encode(array('removed' => !file_exists($root), 'restored' => getcwd() === $original));
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame(array('removed' => true, 'restored' => true), json_decode($result['stdout'], true));
    }

    /**
     * Stop extraction when the computed CRC does not match the manifest entry.
     *
     * @return void
     */
    public function testChecksumMismatchTerminatesWithTheIntegrityError()
    {
        $code = <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$fp = tmpfile();
fwrite($fp, 'record');
rewind($fp);
\Extract_low_seg2cat::extractFile('record', array(6, 0, 6, '0', 0, 0), $fp);
echo 'unexpected';
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stderr']);
        $this->assertSame('Invalid internal .phar file (checksum error)', $result['stdout']);
    }

    /**
     * Report a size mismatch without reading an undefined internal variable.
     *
     * @return void
     */
    public function testSizeMismatchTerminatesWithoutAnUndefinedVariableDiagnostic()
    {
        $code = <<<'CODE'
require LOW_SEG2CAT_ROOT . '/low_seg2cat/low_seg2cat.phar';
$fp = tmpfile();
fwrite($fp, 'record');
rewind($fp);
\Extract_low_seg2cat::extractFile('record', array(7, 0, 6, sprintf('%u', crc32('record') & 0xffffffff), 0, 0), $fp);
echo 'unexpected';
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $this->assertSame('Invalid internal .phar file (size error 6 != 7)', $result['stdout']);
    }
}
