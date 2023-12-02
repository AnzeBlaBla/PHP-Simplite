<?php

namespace AnzeBlaBla\Simplite;

class Request
{
    public $method;
    public $path;
    public $query;
    private $body;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = $_SERVER['REQUEST_URI'];
        $this->query = $_GET;
        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
            $this->body = json_decode(file_get_contents('php://input'), true);
        } else {
            $this->body = $_POST;
        }
    }

    /**
     * Gets request data (body)
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
