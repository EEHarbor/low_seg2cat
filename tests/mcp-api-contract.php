<?php
require_once __DIR__ . '/Support/diagnostics.php';
require 'phar://' . $argv[1] . '/FluxCapacitor/Base/Mcp.php';
function ee() { return $GLOBALS['native_api_ee']; }
$GLOBALS['native_api_ee'] = (object) array('input' => new class { public function get($key) { return 2; } });
$base = 'Low\\Seg2Cat\\FluxCapacitor\\Base\\Mcp';
$kind = $argv[2];
if ($kind === 'bare') { $class = $base; }
if ($kind === 'public') { eval('class NativeApiConsumer extends \\'.$base.' { public $data = array("seed" => true); public function inspect() { return $this->data; } }'); $class = 'NativeApiConsumer'; }
if ($kind === 'protected') { eval('class NativeApiConsumer extends \\'.$base.' { protected $data = array("seed" => true); public function inspect() { return $this->data; } }'); $class = 'NativeApiConsumer'; }
if ($kind === 'private') { eval('class NativeApiConsumer extends \\'.$base.' { private $data = array("seed" => true); public function inspect() { return $this->data; } }'); $class = 'NativeApiConsumer'; }
if ($kind === 'magic') { eval('class NativeApiConsumer extends \\'.$base.' { private $store = array("data" => array("seed" => true)); public $calls = array(); public function &__get($name) { $this->calls[] = "get:".$name; return $this->store[$name]; } public function __set($name,$value) { $this->calls[] = "set:".$name; $this->store[$name]=$value; } public function inspect() { return $this->store["data"]; } }'); $class = 'NativeApiConsumer'; }
if ($kind === 'array_access') { eval('class NativeApiConsumer extends \\'.$base.' { protected $data; public function initialize() { $this->data = new \\ArrayObject(array("seed" => true)); } public function inspect() { return $this->data; } }'); $class = 'NativeApiConsumer'; }
$object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
if (method_exists($object, 'initialize')) { $object->initialize(); }
$object->flux = new class { public function moduleURL($method) { return 'module/'.$method; } public function pagination($options) { return 'links'; } };
$model = new class { public $calls = array(); public function all() { return new ArrayObject(array(1,2,3)); } public function limit($limit) { $this->calls[] = array('limit',$limit); return $this; } public function offset($offset) { $this->calls[] = array('offset',$offset); return $this; } };
$error = null;
try { $object->paginateModel($model,'explicit',10); } catch (Throwable $caught) { $error = array(get_class($caught),$caught->getMessage()); }
$data = method_exists($object,'inspect') ? $object->inspect() : $object->data;
echo json_encode(array('error'=>$error,'data_type'=>is_object($data)?get_class($data):gettype($data),'data'=>$data,'calls'=>$model->calls,'magic'=>property_exists($object,'calls')?$object->calls:array()),JSON_THROW_ON_ERROR),"\n";
