<?php

namespace Tests\Support {
    class Environment
    {
        public $config;
        public $uri;
        public $db;
        public $extensions;
        public $functions;
        public $cp;
        public $load;
        public $typography;
        public $view;
        public $model;
        public $alerts;
        public $request;
        public $TMPL;
        public $input;
        public $cache;
        public $session;
        public $legacy_api;
        public $addons_model;
        public $logger;
        public $dbforge;
        public $api;
        public $security;
        public $pagination;
        public $table;
        public $services = array();
        public $builders = array();
        private $databasePrototype;
        private $legacyLoaded = array();
        public $assigned = array();
        public $removed = array();
        public $rendered = array();
        public $foot = array();
        public $redirects = array();
        public $loaded = array();
        public $formatCalls = array();
        public $pathCalls = array();
        public $incomingUri = '';
        public $categories = array();
        public $groups = array();
        public $channelEntries = array();

        /**
         * Build collaborators matching the EE APIs used by the extension.
         *
         * @return void
         */
        public function __construct()
        {
            $this->config = new Config();
            $this->uri = (object) array('segments' => array());
            $this->extensions = (object) array('last_call' => false);
            $this->view = (object) array('cp_page_title' => 'Low Seg2Cat');
            $this->db = new Database();
            $this->databasePrototype = $this->db;
            $this->model = new ModelService($this);
            $this->alerts = new Recorder();
            $this->logger = new Recorder();
            $this->dbforge = new Recorder();
            $this->request = new CallbackService(array('post' => function ($key, $default) {
                return array_key_exists($key, $_POST) ? $_POST[$key] : $default;
            }));
            $this->functions = new CallbackService(array('redirect' => function ($url) {
                $this->redirects[] = (string) $url;
            }, 'clear_caching' => function ($type) {
                $this->loaded[] = 'clear:' . $type;
            }, 'assign_variables' => function ($input) {
                return array('variables' => $input);
            }, 'assign_parameters' => function ($input, $defaults) {
                return array($input, $defaults);
            }));
            $this->cp = new CallbackService(array('add_to_foot' => function ($html) {
                $this->foot[] = $html;
            }, 'get_foot' => function () {
                return $this->foot;
            }));
            $this->load = new CallbackService(array('library' => function ($library) {
                $this->loaded[] = $library;
            }, 'helper' => function ($helper) {
                $this->loaded[] = $helper;
            }, 'dbforge' => function () {
                $this->loaded[] = 'dbforge';
            }, 'view' => function ($view, $vars, $return) {
                return array($view, $vars, $return);
            }));
            $this->typography = new CallbackService(array('format_characters' => function ($value) {
                $this->formatCalls[] = $value;
                return str_replace('&', '&amp;', $value);
            }, 'parse_file_paths' => function ($value) {
                $this->pathCalls[] = $value;
                return str_replace('{filedir_1}', '/images/', $value);
            }));
            foreach (array('config', 'uri', 'db', 'extensions', 'functions', 'cp', 'load', 'typography', 'view', 'logger', 'dbforge') as $name) {
                $this->legacyLoaded[$name] =& $this->$name;
            }
        }

        /**
         * Reject every diagnostic, including @-hidden nested/generated diagnostics.
         *
         * @return void
         */
        public static function installDiagnostics()
        {
            require __DIR__ . '/diagnostics.php';
        }

        /**
         * Resolve an explicitly supported EE service.
         *
         * @param string|null $service
         * @param array $arguments
         * @return mixed
         * @throws \LogicException
         */
        public function service($service, array $arguments)
        {
            if ($service === null) {
                return $this;
            }
            if (array_key_exists($service, $this->services)) {
                return is_callable($this->services[$service]) ? call_user_func_array($this->services[$service], $arguments) : $this->services[$service];
            }
            if ($service === 'App') {
                return new CallbackService(array('get' => function ($name) {
                    if ($name !== 'low_seg2cat') {
                        throw new \LogicException('Unexpected add-on: ' . $name);
                    }
                    return new CallbackService(array('getVersion' => function () { return '4.1.1'; },
                        'getName' => function () { return 'Low Seg2Cat'; }));
                }));
            }
            if ($service === 'db') {
                $builder = clone $this->databasePrototype;
                $builder->calls = array();
                $builder->data_cache = array();
                $builder->resetQueryState();
                $this->builders[] = $builder;
                return $builder;
            }
            if ($service === 'Model') {
                return $this->model;
            }
            if ($service === 'Request') {
                return $this->request;
            }
            if ($service === 'CP/Alert') {
                return $this->alerts;
            }
            if ($service === 'CP/URL') {
                if ($arguments) {
                    return new Url('cp://' . $arguments[0]);
                }
                return new CallbackService(array('make' => function ($path, $variables = array()) {
                    return new Url('cp://' . $path . ($variables ? '?' . http_build_query($variables) : ''));
                }));
            }
            if ($service === 'View') {
                return new CallbackService(array('make' => function ($view) {
                    return new CallbackService(array('render' => function ($vars) use ($view) {
                        $this->rendered[] = array($view, $vars);
                        return 'rendered:' . $view;
                    }));
                }));
            }
            throw new \LogicException('Unexpected EE service: ' . $service);
        }

        /**
         * Register an unloaded legacy instance using EE's facade contract.
         *
         * @param string $class
         * @param mixed $value
         * @return void
         * @throws \RuntimeException
         */
        public function set($class, $value)
        {
            if ($this->has($class)) {
                throw new \RuntimeException('Cannot overwrite ' . $class . ' on the loader.');
            }
            if (property_exists($this, $class)) {
                $this->$class = $value;
                $this->legacyLoaded[$class] =& $this->$class;
            } else {
                $this->legacyLoaded[$class] = $value;
            }
            $this->assigned[$class] = $value;
            if ($class === 'session') {
                $value->setSessionCookies();
            }
        }

        /**
         * Remove an EE container instance.
         *
         * @param string $class
         * @return void
         */
        public function remove($class)
        {
            unset($this->legacyLoaded[$class]);
            if (property_exists($this, $class)) {
                unset($this->$class);
            }
            $this->removed[] = $class;
        }

        /**
         * Read a loaded legacy instance independently of named DI services.
         *
         * @param string $class
         * @return mixed
         * @throws \InvalidArgumentException
         */
        public function get($class)
        {
            if (!$this->has($class)) {
                throw new \InvalidArgumentException("No such property: '" . $class . "' on " . get_called_class());
            }
            return $this->legacyLoaded[$class];
        }

        /**
         * Detect loaded null values using the facade's array-key semantics.
         *
         * @param string $class
         * @return bool
         */
        public function has($class)
        {
            return array_key_exists($class, $this->legacyLoaded);
        }

        /**
         * Resolve newly registered properties through the legacy registry.
         *
         * @param string $class
         * @return mixed
         */
        public function __get($class)
        {
            return $this->get($class);
        }

        /**
         * Match facade isset semantics for loaded legacy entries.
         *
         * @param string $class
         * @return bool
         */
        public function __isset($class)
        {
            return $this->has($class);
        }
    }

    class Url
    {
        private $value;

        /**
         * Retain a compiled control-panel URL.
         *
         * @param string $value
         * @return void
         */
        public function __construct($value)
        {
            $this->value = $value;
        }

        /**
         * Compile the URL as EE's URL object does.
         *
         * @return string
         */
        public function compile()
        {
            return $this->value;
        }

        /**
         * Render the URL in a string context.
         *
         * @return string
         */
        public function __toString()
        {
            return $this->value;
        }
    }

    class Config
    {
        public $items = array('site_id' => 1, 'max_url_segments' => 12);
        public $_global_vars = array();

        /**
         * Read a configured item with EE's missing-key false fallback.
         *
         * @param string $key
         * @return mixed
         */
        public function item($key)
        {
            return isset($this->items[$key]) ? $this->items[$key] : false;
        }

        /**
         * Read a URL configuration with a trailing slash.
         *
         * @param string $key
         * @return string
         */
        public function slash_item($key)
        {
            return rtrim($this->item($key), '/') . '/';
        }
    }

    class CallbackService
    {
        private $callbacks;

        /**
         * Register supported methods.
         *
         * @param array $callbacks
         * @return void
         */
        public function __construct(array $callbacks)
        {
            $this->callbacks = $callbacks;
        }

        /**
         * Execute an expected collaborator call.
         *
         * @param string $method
         * @param array $arguments
         * @return mixed
         * @throws \LogicException
         */
        public function __call($method, array $arguments)
        {
            if (!isset($this->callbacks[$method])) {
                throw new \LogicException('Unexpected collaborator method: ' . $method);
            }
            return call_user_func_array($this->callbacks[$method], $arguments);
        }
    }

    class Recorder
    {
        public $calls = array();

        /**
         * Record the fluent alert API.
         *
         * @param string $method
         * @param array $arguments
         * @return self
         */
        public function __call($method, array $arguments)
        {
            $this->calls[] = array($method, $arguments);
            return $this;
        }

        /**
         * Provide a real optional alert method for method_exists checks.
         *
         * @return self
         */
        public function cannotClose()
        {
            return $this->__call('cannotClose', array());
        }
    }

    class Database
    {
        public $calls = array();
        public $data_cache = array();
        public $rows = array();
        public $rowsByTable = array();
        public $tables = array();
        public $fields = array();
        public $keyRows = array();
        public $mutationResults = array('insert' => true, 'update' => true, 'delete' => true);
        private $from;

        /**
         * Start a fresh builder without an inherited legacy query.
         *
         * @return void
         */
        public function resetQueryState()
        {
            $this->from = null;
        }

        /**
         * Record Active Record operations and return deterministic result objects.
         *
         * @param string $method
         * @param array $arguments
         * @return mixed
         * @throws \LogicException
         */
        public function __call($method, array $arguments)
        {
            $this->calls[] = array($method, $arguments);
            if ($method === 'table_exists') {
                return in_array($arguments[0], $this->tables, true);
            }
            if ($method === 'field_exists') {
                return in_array($arguments[0], $this->fields, true);
            }
            if ($method === 'query') {
                return new QueryResult($this->keyRows);
            }
            if ($method === 'from') {
                $this->from = $arguments[0];
                return $this;
            }
            if ($method === 'count_all_results') {
                return count(isset($this->rowsByTable[$arguments[0]]) ? $this->rowsByTable[$arguments[0]] : $this->rows);
            }
            if ($method === 'get' || $method === 'get_where') {
                $table = isset($arguments[0]) ? $arguments[0] : $this->from;
                return new QueryResult(isset($this->rowsByTable[$table]) ? $this->rowsByTable[$table] : $this->rows);
            }
            if (array_key_exists($method, $this->mutationResults)) {
                return $this->mutationResults[$method];
            }
            if (in_array($method, array('where', 'select'), true)) {
                return $this;
            }
            throw new \LogicException('Unexpected database method: ' . $method);
        }
    }

    class QueryResult
    {
        private $rows;
        private $objects;
        private $rowData;
        public $current_row = 0;
        public $num_rows;

        /**
         * Store a database result snapshot.
         *
         * @param array $rows
         * @return void
         */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
            $this->objects = array_map(function ($row) { return (object) $row; }, $rows);
            $this->num_rows = count($rows);
        }

        /**
         * Count rows using the EE query-result API.
         *
         * @return int
         */
        public function num_rows()
        {
            return count($this->rows);
        }

        /**
         * Match EE's column lookup and numeric row-selection behavior.
         *
         * @param int|string $index
         * @param string $type
         * @return mixed
         */
        public function row($index = 0, $type = 'object')
        {
            if (!is_numeric($index)) {
                if (!is_array($this->rowData)) {
                    $this->rowData = $this->row_array(0);
                }
                if (array_key_exists($index, $this->rowData)) {
                    return $this->rowData[$index];
                }
                $index = 0;
            }
            return $type === 'object' ? $this->row_object($index) : $this->row_array($index);
        }

        /**
         * Return EE's current row, retaining it for an out-of-range index.
         *
         * @param int|string $index
         * @return object|array
         */
        public function row_object($index = 0)
        {
            if (!$this->objects) {
                return array();
            }
            if ($index != $this->current_row && isset($this->objects[$index])) {
                $this->current_row = $index;
            }
            return $this->objects[$this->current_row];
        }

        /**
         * Select a result using EE's array row wrapper.
         *
         * @param int|string $index
         * @return array
         */
        public function row_array($index = 0)
        {
            return (array) $this->row_object($index);
        }

        /**
         * Return result rows as objects.
         *
         * @param string $type
         * @return array
         */
        public function result($type = 'object')
        {
            return $type === 'array' ? $this->rows : $this->objects;
        }
    }

    class ModelService
    {
        private $app;
        public $queries = array();

        /**
         * Bind the model service to current category fixtures.
         *
         * @param Environment $app
         * @return void
         */
        public function __construct(Environment $app)
        {
            $this->app = $app;
        }

        /**
         * Create a query for a supported EE model.
         *
         * @param string $name
         * @return ModelQuery
         * @throws \LogicException
         */
        public function get($name)
        {
            if (!in_array($name, array('Category', 'CategoryGroup', 'ChannelEntry'), true)) {
                throw new \LogicException('Unexpected model: ' . $name);
            }
            $rows = $name === 'Category' ? $this->app->categories : $this->app->groups;
            if ($name === 'ChannelEntry') {
                $rows = $this->app->channelEntries;
            }
            $query = new ModelQuery($rows);
            $this->queries[] = array($name, $query);
            return $query;
        }
    }

    class ModelQuery
    {
        private $rows;
        private $maximum;
        private $start = 0;
        public $filters = array();

        /**
         * Retain source rows for actual filter and limit evaluation.
         *
         * @param array $rows
         * @return void
         */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        /**
         * Apply EE's two-argument equality or three-argument IN filter.
         *
         * @param string $property
         * @param mixed $operator
         * @param mixed $value
         * @return self
         */
        public function filter($property, $operator, $value = false)
        {
            $this->filters[] = func_get_args();
            $membership = func_num_args() === 3;
            $this->rows = array_values(array_filter($this->rows, function ($row) use ($property, $operator, $value, $membership) {
                return $membership ? in_array($row->$property, $value) : $row->$property == $operator;
            }));
            return $this;
        }

        /**
         * Set the maximum result count.
         *
         * @param int $maximum
         * @return self
         */
        public function limit($maximum)
        {
            $this->maximum = $maximum;
            return $this;
        }

        /**
         * Materialize the filtered query as an EE-like collection.
         *
         * @return Collection
         */
        public function all()
        {
            return new Collection(array_slice($this->rows, $this->start, $this->maximum));
        }

        /**
         * Set the query's result offset.
         *
         * @param int $start
         * @return self
         */
        public function offset($start)
        {
            $this->start = $start;
            return $this;
        }

        /**
         * Return the first matching model or null when there is no result.
         *
         * @return object|null
         */
        public function first()
        {
            return isset($this->rows[0]) ? $this->rows[0] : null;
        }
    }

    class Collection
    {
        private $rows;

        /**
         * Retain ordered records.
         *
         * @param array $rows
         * @return void
         */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        /**
         * Return the record count.
         *
         * @return int
         */
        public function count()
        {
            return count($this->rows);
        }

        /**
         * Index records by a unique model property.
         *
         * @param string $property
         * @return array
         */
        public function indexBy($property)
        {
            $indexed = array();
            foreach ($this->rows as $row) {
                $indexed[$row->$property] = $row;
            }
            return $indexed;
        }

        /**
         * Sort records by one model property.
         *
         * @param string $property
         * @return self
         */
        public function sortBy($property)
        {
            usort($this->rows, function ($left, $right) use ($property) {
                return strcmp($left->$property, $right->$property);
            });
            return $this;
        }

        /**
         * Project records into a key/value dictionary.
         *
         * @param string $key
         * @param string $value
         * @return array
         */
        public function getDictionary($key, $value)
        {
            $dictionary = array();
            foreach ($this->rows as $row) {
                $dictionary[$row->$key] = $row->$value;
            }
            return $dictionary;
        }
    }
}

namespace {
    /**
     * Resolve services through the per-test EE application fixture.
     *
     * @param string|null $service
     * @param mixed ...$arguments
     * @return mixed
     */
    function ee($service = null, ...$arguments)
    {
        return $GLOBALS['low_seg2cat_app']->service($service, $arguments);
    }

    /**
     * Translate the language strings used by settings UI.
     *
     * @param string $key
     * @return string
     */
    function lang($key)
    {
        return $key === 'settings_saved_desc' ? '%s settings saved.' : $key;
    }

    class EE_URI
    {
        public $uri_string = '';
        public $segments = array();

        /**
         * Fetch the request URI without changing the existing EE URI instance.
         *
         * @return void
         */
        public function _fetch_uri_string()
        {
            $this->uri_string = ee()->incomingUri;
        }

        /**
         * Explode nonempty URI segments in their original order.
         *
         * @return void
         */
        public function _explode_segments()
        {
            $this->segments = array_values(array_filter(explode('/', trim($this->uri_string, '/')), 'strlen'));
        }

        /**
         * Match EE's one-based segment indexing.
         *
         * @return void
         */
        public function _reindex_segments()
        {
            array_unshift($this->segments, null);
            unset($this->segments[0]);
        }

        /**
         * Return the one-based URI segment map.
         *
         * @return array
         */
        public function segment_array()
        {
            return $this->segments;
        }

        /**
         * Count URI segments.
         *
         * @return int
         */
        public function total_segments()
        {
            return count($this->segments);
        }

        /**
         * Return the current URI string using EE's public accessor.
         *
         * @return string
         */
        public function uri_string()
        {
            return $this->uri_string;
        }
    }

    if (getenv('LOW_SEG2CAT_REAL_FIELDTYPE') === '1') {
        require_once getenv('LOW_SEG2CAT_FT_TRAIT_FILE');
    } else {
        class EE_Fieldtype
        {
            public $settings = array();
            public $contentId = 0;

            /**
             * Return the entry identifier exposed by EE's fieldtype base contract.
             *
             * @return int
             */
            public function content_id()
            {
                return $this->contentId;
            }
        }

    }

    /**
     * Return the request URI through the legacy global helper.
     *
     * @return string
     */
    function uri_string()
    {
        return ee()->incomingUri;
    }
}

namespace {
    /**
     * Read a cache file using EE's file helper contract.
     *
     * @param string $path
     * @return string|false
     */
    function read_file($path)
    {
        return is_file($path) ? file_get_contents($path) : false;
    }

    /**
     * Write a cache file using EE's file helper contract.
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    function write_file($path, $contents)
    {
        return file_put_contents($path, $contents) !== false;
    }
}

namespace Tests\Support {
    class ViewRenderer
    {
        public $embedded = array();

        /**
         * Render a shipped template with an isolated variable scope and buffer.
         *
         * @param string $path
         * @param array $variables
         * @return string
         */
        public function render($path, array $variables = array())
        {
            extract($variables);
            ob_start();
            try {
                include $path;
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        }

        /**
         * Record the requested embedded EE form partial.
         *
         * @param string $name
         * @return void
         */
        public function embed($name)
        {
            $this->embedded[] = $name;
            echo '<form id="shared-form"></form>';
        }
    }
}

namespace {
    /**
     * Create the opening form marker used by the license templates.
     *
     * @param string $url
     * @param array $attributes
     * @return string
     */
    function form_open($url, array $attributes = array())
    {
        return '<form action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Escape the input value with EE's form-helper output contract.
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    function form_input($name, $value)
    {
        return '<input name="' . $name . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Render a checkbox with its selected state.
     *
     * @param string $name
     * @param string $value
     * @param bool $selected
     * @return string
     */
    function form_checkbox($name, $value, $selected)
    {
        return '<input name="' . $name . '" value="' . $value . '"' . ($selected ? ' checked="checked"' : '') . '>';
    }

    /**
     * Close the license form.
     *
     * @return string
     */
    function form_close()
    {
        return '</form>';
    }

    /**
     * Match the string helper's URL-preserving slash normalization.
     *
     * @param string $string
     * @return string
     */
    function reduce_double_slashes($string)
    {
        return preg_replace('#([^/:])/+#', '\\1/', $string);
    }

    /**
     * Produce a slug through the legacy URL helper contract.
     *
     * @param string $string
     * @return string
     */
    function url_title($string)
    {
        return str_replace(' ', '-', $string);
    }

    /**
     * Add a scheme through the legacy URL helper contract.
     *
     * @param string $string
     * @return string
     */
    function prep_url($string)
    {
        return 'https://' . $string;
    }
}

namespace Tests\Support {
    class LegacySession extends Recorder
    {
        public $cache = array();

        /**
         * Return deterministic legacy authentication identifiers.
         *
         * @param string $key
         * @param mixed $default
         * @return string
         */
        public function userdata($key, $default)
        {
            return $key === 'session_id' ? 'session-id' : 'fingerprint';
        }
    }

    class LegacyControlPanel extends Recorder
    {
        public $footer_item = array();

        /**
         * Append footer markup through the legacy control-panel API.
         *
         * @param string $html
         * @return void
         */
        public function add_to_foot($html)
        {
            $this->footer_item[] = $html;
        }
    }
}
