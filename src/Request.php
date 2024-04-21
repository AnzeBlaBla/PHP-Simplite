<?php

namespace AnzeBlaBla\Simplite;

class Request
{
    /**
     * @var string Request method
     */
    public $method;
    /**
     * @var string Request URI
     */
    public $uri;
    /**
     * @var array Query parameters
     */
    public $query;
    /**
     * @var array Request body
     */
    private $body;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->query = $_GET;
        // TODO: could cause a vuln where attacker sends JSON where it's not expected
        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
            $this->body = json_decode(file_get_contents('php://input'), true);
        } else {
            $this->body = $_POST;
        }
    }

    /**
     * Gets request data (body)
     * @param array $required_keys
     * @return array
     */
    public function body($required_keys = [])
    {
        if (count($required_keys) > 0) {
            foreach ($required_keys as $key) {
                if (!isset($this->body[$key])) {
                    throw new \Exception("Missing required data: $key");
                }
            }
        }
        return $this->body;
    }

    /**
     * Checks if request has body data
     * @param string|array $key
     * @return bool
     */
    public function hasBody($key = null)
    {
        if ($key) {
            // if array
            if (is_array($key)) {
                foreach ($key as $k) {
                    if (!isset($this->body[$k])) {
                        return false;
                    }
                }
                return true;
            }
            return isset($this->body[$key]);
        }
        return count($this->body) > 0;
    }
}
