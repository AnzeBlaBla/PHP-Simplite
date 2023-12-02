<?php

namespace AnzeBlaBla\Simplite;

use AnzeBlaBla\Simplite\Router;

class Application
{
    private static $instance;
    public static function getInstance()
    {
        if (!self::$instance) {
            throw new \Exception("Application not instantiated");
        }
        return self::$instance;
    }

    private static function merge_config($config, $default_config)
    {
        $merged_config = $default_config;

        foreach ($config as $key => $value) {
            $merged_config[$key] = $value;
        }

        return $merged_config;
    }


    private $config;

    private $rendered_html = null;
    private $api_endpoint = null;

    public DB $db;
    public Request $request;

    /**
     * Additional context available to all components
     * @var array $context
     */
    public $context = null;

    public function __construct($config = [])
    {
        if (self::$instance) {
            throw new \Exception("Application already instantiated");
        }
        self::$instance = $this;

        session_start(); // TODO: maybe shouldn't be here?

        $config = self::merge_config($config, [
            'debug' => false,
            'root_directory' => dirname(debug_backtrace()[0]['file']),
            'router_folder' => 'pages',
            'api_folder' => 'api',
            'components_folder' => 'components',
            'translations' => [],
            // db (optional) { host, dbname, username, password }
        ]);

        $this->validateConfig($config);

        $this->config = $config;

        if (isset($this->config['db'])) {
            $this->db = DB::getInstance();
        }

        $this->request = new Request();
    }

    private function validateConfig($config)
    {
        if (!isset($config['root_directory'])) {
            throw new \Exception("Root directory not set (please set it in the Application config)");
        }

        // Root directory must exist
        if (!file_exists($config['root_directory'])) {
            throw new \Exception("Root directory does not exist (please create $config[root_directory])");
        }

        // Router folder must exist
        if (!file_exists($config['root_directory'] . '/' . $config['router_folder'])) {
            throw new \Exception("Router folder does not exist (please create $config[root_directory]/$config[router_folder])");
        }

        // Api folder must exist
        if (!file_exists($config['root_directory'] . '/' . $config['api_folder'])) {
            throw new \Exception("Api folder does not exist (please create $config[root_directory]/$config[api_folder])");
        }

        // Components folder must exist
        if (!file_exists($config['root_directory'] . '/' . $config['components_folder'])) {
            throw new \Exception("Components folder does not exist (please create $config[root_directory]/$config[components_folder])");
        }

        // If db is set, it must be an array
        if (isset($config['db']) && !is_array($config['db'])) {
            throw new \Exception("DB config must be an array");
        }

        // If db is set, it must have host, dbname, username and password
        if (isset($config['db']) && (!isset($config['db']['host']) || !isset($config['db']['dbname']) || !isset($config['db']['username']) || !isset($config['db']['password']))) {
            throw new \Exception("DB config must have host, dbname, username and password");
        }
    }

    public function getConfig($key = null)
    {
        if ($key) {
            return $this->config[$key];
        } else {
            return $this->config;
        }
    }

    #region Config

    public function setDB($db_config): Application
    {
        $this->config['db'] = $db_config;
        $this->db = DB::getInstance();
        return $this;
    }

    public function setDebug($debug): Application
    {
        $this->config['debug'] = $debug;
        return $this;
    }

    public function setRootDirectory($root_directory): Application
    {
        $this->config['root_directory'] = $root_directory;
        return $this;
    }

    public function setRouterFolder($router_folder): Application
    {
        $this->config['router_folder'] = $router_folder;
        return $this;
    }

    public function setApiFolder($api_folder): Application
    {
        $this->config['api_folder'] = $api_folder;
        return $this;
    }

    public function setComponentsFolder($components_folder): Application
    {
        $this->config['components_folder'] = $components_folder;
        return $this;
    }

    public function setTranslations($translations): Application
    {
        $this->config['translations'] = $translations;
        return $this;
    }

    public function setContext($context): Application
    {
        // warn if context is already set
        if ($this->context) {
            trigger_error("Context already set", E_USER_WARNING);
        }
        $this->context = $context;
        return $this;
    }

    public function addContext($context): Application
    {
        if (!$this->context) {
            $this->context = [];
        }
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    #endregion

    /**
     * Renders the page
     * @return void
     * @throws \Exception
     */
    public function render()
    {
        $request_uri = $_SERVER['REQUEST_URI'];

        $parsed_url = parse_url($request_uri);

        $url_path = $parsed_url['path'];
        //$query = $parsed_url['query'];

        $url_path = trim($url_path, '/');
        $url_parts = explode('/', $url_path);

        // remove .php from last part
        if (count($url_parts) > 0 && strpos($url_parts[count($url_parts) - 1], '.php') !== false) {
            $url_parts[count($url_parts) - 1] = str_replace('.php', '', $url_parts[count($url_parts) - 1]);
        }

        // api or normal routing
        if (count($url_parts) > 0 && $url_parts[0] == 'api') { // TODO: hardcoded
            $router = new Router($this, $this->config['root_directory'], $this->config['api_folder']);

            try {
                // remove api from url
                array_shift($url_parts);
                $this->api_endpoint = $router->render($url_parts, true);
            } catch (\Throwable $th) {
                $this->api_endpoint = function ($app) use ($th) {
                    // if code is 200, set it to 500
                    if (http_response_code() == 200) {
                        http_response_code(500);
                    }
                    return [
                        'success' => false,
                        'message' => $th->getMessage(),
                    ];
                };
            }
        } else {
            $router = new Router($this, $this->config['root_directory'], $this->config['router_folder']);

            try {
                $this->rendered_html = $router->render($url_parts);
            } catch (\Throwable $th) {
                $this->rendered_html = "Error: " . $th->getMessage() . "<br>" . $th->getTraceAsString();
            }
        }

        if ($this->rendered_html) {
            echo $this->rendered_html;
        } else if ($this->api_endpoint) {

            // Render object can either be an object of ["GET" => function($app) { ... }, "POST" => function($app) { ... }]
            // or a function($app) { ... }
            // or just a string

            if (is_callable($this->api_endpoint)) {
                $api_response = ($this->api_endpoint)($this); // Call the function
            } else if (is_array($this->api_endpoint)) {
                if (isset($this->api_endpoint[$this->request->method])) {
                    $api_response = $this->api_endpoint[$this->request->method]($this); // Call the function
                } else {
                    http_response_code(405);
                    $api_response = [
                        'success' => false,
                        'message' => 'Method not allowed'
                    ];
                }
            } else {
                // Treat as string
                $api_response = $this->api_endpoint;
            }

            // if api response is not an array, just echo it, otherwise encode it as json
            if (!is_array($api_response)) {
                echo $api_response;
            } else {
                header('Content-Type: application/json');
                echo json_encode($api_response);
            }
        }
    }



    #region Debug
    private $DEBUG = false;
    public function debug($message)
    {
        if ($this->DEBUG) {
            echo $message . '<br>';
        }
    }
    #endregion

    #region Helpers for use in application code

    /**
     * Redirects to the given url
     * @param string $url
     * @return void
     */
    public function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
