<?php

namespace Tests;

class ExtractionRootTest extends TestCase
{
    /**
     * Keep actual extraction inside its managed root when cache paths contain links.
     *
     * @dataProvider extractionPaths
     * @param string $kind Managed cache path shape.
     * @return void
     */
    public function testActualGoRetainsOutsideSentinels($kind): void
    {
        $code = '$archive = ' . var_export(PATH_THIRD . 'low_seg2cat/low_seg2cat.phar', true) . ';'
            . '$kind = ' . var_export($kind, true) . ';' . <<<'CODE'
require $archive;
$base = tempnam(sys_get_temp_dir(), 'low_seg2cat-extraction-root-');
unlink($base);
mkdir($base, 0700);
$base = realpath($base);
putenv('TMPDIR=' . $base);
mkdir($base . '/outside/low_seg2cat/nested', 0700, true);
file_put_contents($base . '/outside/low_seg2cat/nested/sentinel', 'owned outside target');
if ($kind === 'container' || $kind === 'broken-container') {
    symlink($base . ($kind === 'container' ? '/outside' : '/missing'), $base . '/pharextract');
} else {
    mkdir($base . '/pharextract', 0700);
    if ($kind === 'root') {
        symlink($base . '/outside/low_seg2cat', $base . '/pharextract/low_seg2cat');
    }
}
$returned = false;
register_shutdown_function(function () use ($base, &$returned) {
    clearstatcache();
    $result = array('outside_retained' => is_file($base . '/outside/low_seg2cat/nested/sentinel'),
        'outside_unwritten' => !is_file($base . '/outside/low_seg2cat/index.php'),
        'returned' => $returned, 'managed_extracted' => !is_link($base . '/pharextract')
            && !is_link($base . '/pharextract/low_seg2cat') && is_file($base . '/pharextract/low_seg2cat/index.php'));
    echo "\nOWNED_EXTRACTION_RESULT " . json_encode($result) . "\n";
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
            continue;
        }
        unlink($file->getPathname());
    }
    rmdir($base);
});
Extract_low_seg2cat::go(true);
$returned = true;
CODE;
        $run = \Tests\Support\Process::probe($code);
        list($exit, $stdout, $stderr) = array($run['status'], $run['stdout'], $run['stderr']);
        self::assertSame(0, $exit, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame(1, preg_match('/OWNED_EXTRACTION_RESULT (.+)/', $stdout, $match), $stdout);
        $result = json_decode($match[1], true);
        self::assertTrue($result['outside_retained'], $stdout);
        self::assertTrue($result['outside_unwritten'], $stdout);
        $rejected = strpos($kind, 'container') !== false;
        self::assertSame(!$rejected, $result['returned'], $stdout);
        self::assertSame(!$rejected, $result['managed_extracted'], $stdout);
        if ($rejected) {
            self::assertStringContainsString('Could not use linked phar extraction directory', $stdout);
        }
    }

    /**
     * Exercise normal extraction and both managed path identities through go itself.
     *
     * @return array
     */
    public static function extractionPaths(): array
    {
        return array('normal' => array('normal'), 'root link' => array('root'),
            'shared container link' => array('container'), 'dangling container link' => array('broken-container'));
    }
}
