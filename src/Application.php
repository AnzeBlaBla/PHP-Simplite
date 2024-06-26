<?php

namespace AnzeBlaBla\Simplite;

use AnzeBlaBla\Simplite\Router;

class BasicAuthConfig
{
    public $username;
    public $password;
    public $realm;
    public $message;

    public function __construct($username, $password, $realm = "My Realm", $message = "Text to send if user hits Cancel button")
    {
        $this->username = $username;
        $this->password = $password;
        $this->realm = $realm;
        $this->message = $message;
    }
}

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
            'models_folder' => 'models',
            'translations' => [],
            'component_comments' => false, // If true, components will be wrapped in '<!-- BEGIN COMPONENT -->' and '<!-- END COMPONENT -->'
            // db (optional) { host, dbname, username, password }
        ]);

        $this->validateConfig($config);

        // If debug is on, turn on error reporting
        if ($config['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }

        // Include all models in the models folder
        if (isset($config['models_folder'])) {
            $models_folder = $config['root_directory'] . '/' . $config['models_folder'];
            if (file_exists($models_folder)) {
                foreach (scandir($models_folder) as $file) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    // Only include php files
                    if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                        include_once $models_folder . '/' . $file; // TODO: this could be a vulnerability
                    }
                }
            }
        }

        $this->config = $config;

        if (isset($this->config['db'])) {
            $this->db = DB::getInstance();
        }

        // if running in cli, don't create request
        if (php_sapi_name() != 'cli') {
            $this->request = new Request();
        }
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
     * Adds basic auth to the page
     * @param BasicAuthConfig $config
     * @return void
     */
    public function addBasicAuth(BasicAuthConfig $config)
    {
        if (
            !isset($_SERVER['PHP_AUTH_USER'])
            || !isset($_SERVER['PHP_AUTH_PW'])
            || $_SERVER['PHP_AUTH_USER'] != $config->username
            || $_SERVER['PHP_AUTH_PW'] != $config->password
        ) {
            header('WWW-Authenticate: Basic realm="' . $config->realm . '"');
            header('HTTP/1.0 401 Unauthorized');
            echo $config->message;
            exit;
        }
    }

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
                // if debug is on, show error
                if ($this->config['debug']) {
                    $this->rendered_html = "Error: " . $th->getMessage() . "<br>" . $th->getTraceAsString();
                } else {
                    // if code is 200, set it to 500
                    if (http_response_code() == 200) {
                        http_response_code(500);
                    }
                    $this->rendered_html = "Error: " . $th->getMessage();
                }
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

            // If message is an array, use print_r
            if (is_array($message)) {
                echo "<pre>";
                print_r($message);
                echo "</pre>";
            } else {
                echo $message . "<br>";
            }
        }
    }
    #endregion

    #region Helpers for use in application code

    /**
     * Redirects to the given url
     * @param string $url
     * @return never
     */
    public function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
