<?php

namespace Tests;

use Tests\Support\Process;

class NativeCompilationTest extends TestCase
{
    /**
     * Compile every shipped PHP unit in a new interpreter under E_ALL.
     *
     * @dataProvider shippedSources
     * @param string $file
     * @param string $hash
     * @return void
     */
    public function testEveryShippedSourceCompilesWithoutDiagnostics($file, $hash)
    {
        $this->assertSame($hash, hash_file('sha256', $file), 'Shipped source differs from pinned inventory.');
        $temporary = tempnam(sys_get_temp_dir(), 'low-seg2cat-compile-');
        try {
            copy($file, $temporary);
            $this->assertSame($hash, hash_file('sha256', $temporary), 'Compilation copy must be byte-identical.');
            $result = Process::execute(array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-l', $temporary));
            $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
            $this->assertSame('', $result['stderr']);
            $this->assertStringContainsString('No syntax errors detected', $result['stdout']);
        } finally {
            unlink($temporary);
        }
    }

    /**
     * Derive sources from the committed hash inventory, including archived files/stub.
     *
     * @return array
     */
    public static function shippedSources()
    {
        $root = dirname(__DIR__, 3);
        $inventory = json_decode(file_get_contents($root . '/tests/SOURCE_INVENTORY.json'), true);
        $cases = array();
        foreach ($inventory as $name => $entry) {
            if (strpos($name, '::') === false) {
                $cases[$name] = array($root . '/' . $name, $entry['sha256']);
                continue;
            }
            list($archive, $member) = explode('::', $name, 2);
            $cases[$name] = array($member === '{stub}' ? $root . '/' . $archive : 'phar://' . $root . '/' . $archive . '/' . $member,
                $member === '{stub}' ? $entry['archive_sha256'] : $entry['sha256']);
        }
        return $cases;
    }

    /**
     * Prove @-hidden user deprecations fail the diagnostic harness.
     *
     * @return void
     */
    public function testDiagnosticHarnessDetectsSuppressedDeprecation()
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('diagnostic sentinel');
        @trigger_error('diagnostic sentinel', E_USER_DEPRECATED);
    }
}
