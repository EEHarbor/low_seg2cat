<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\Environment;

abstract class TestCase extends BaseTestCase
{
    protected $app;
    private $originalPost;
    private $originalIncludePath;
    private $originalDirectory;

    /**
     * Install a fresh EE service graph and strict source diagnostics.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->originalPost = $_POST;
        $this->originalIncludePath = get_include_path();
        $this->originalDirectory = getcwd();
        $_POST = array();
        Environment::installDiagnostics();
        $this->app = new Environment();
        $GLOBALS['low_seg2cat_app'] = $this->app;
        require_once LOW_SEG2CAT_ROOT . '/low_seg2cat/autoload.php';
        \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
        require_once LOW_SEG2CAT_ROOT . '/low_seg2cat/ext.low_seg2cat.php';
    }

    /**
     * Restore process state after each case.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        \Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache::clear();
        $_POST = $this->originalPost;
        set_include_path($this->originalIncludePath);
        chdir($this->originalDirectory);
        unset($GLOBALS['low_seg2cat_app']);
        restore_error_handler();
    }

    /**
     * Invoke a private collaborator seam without changing shipped code.
     *
     * @param object $object
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    protected function invoke($object, $method, array $arguments = array())
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        return $reflection->invokeArgs($object, $arguments);
    }

    /**
     * Set an internal configuration option for a supported behavior variant.
     *
     * @param object $object
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected function setProperty($object, $property, $value)
    {
        $reflection = new \ReflectionProperty($object, $property);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($object, $value);
    }
}
