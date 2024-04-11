<?php

namespace AnzeBlaBla\Simplite;

use finfo;


define("EXTENSIONS_MAP", [
    "html" => "text/html",
    "css" => "text/css",
    "js" => "text/javascript",
    "json" => "application/json",
    "xml" => "application/xml"
]);

class Router
{
    private $app;

    private $url_parts;
    private $current_path;
    private $root_directory;
    private $router_subfolder;

    private array $render_list;
    private $dynamic_data = [];

    private static $LAYOUT_FILENAME = "_layout.php";

    public function __construct($app, $root_directory, $router_subfolder)
    {
        $this->app = $app;
        $this->root_directory = $root_directory;
        $this->router_subfolder = $router_subfolder;
    }
    /**
     * Renders the page
     * @param $url_parts - array of url parts
     * @param $take_return - if `true`, uses return value of file (API), otherwise uses it's output (page - HTML)
     */
    public function render($url_parts, $take_return = false)
    {
        // HACK: search the subfolder for layout, otherwise it's not done, because it looks only for the exact file or dir
        // adds $router_subfolder to the beginning of the url_parts
        array_unshift($url_parts, $this->router_subfolder);
        $this->url_parts = $url_parts;
        $this->current_path = $this->root_directory;
        $this->render_list = [];


        $render_list = $this->getRenderList();

        if (count($render_list) == 0) {
            http_response_code(500);
            throw new \Exception("No files to render");
        }

        $output = null;

        $inner_most_file = $render_list[count($render_list) - 1];

        // if not php file, only render last
        if (strpos($inner_most_file, '.php') !== strlen($inner_most_file) - 4) {
            $output = file_get_contents($inner_most_file);

            $extension = pathinfo($inner_most_file, PATHINFO_EXTENSION);

            if (isset(EXTENSIONS_MAP[$extension])) {
                $mime_type = EXTENSIONS_MAP[$extension];
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($inner_most_file);
            }

            header("Content-Type: $mime_type");
        } else {
            $output = $this->renderRenderList($render_list, $take_return);
        }

        return $output;
    }

    /**
     * Renders the list of files
     * @param $render_list - list of files to render
     * @param $take_return - if `true`, uses return value of file (API), otherwise uses it's output (page - HTML)
     */
    private function renderRenderList($render_list, $take_return = false)
    {
        $rendered_parts = [];

        $content = $this->getPlaceholder();
        
        foreach ($render_list as $path) {

            // Pass dynamic data to the file            
            extract($this->dynamic_data);            

            // Pass app instance to the file
            $app = $this->app;

            if ($take_return) {
                $output = require $path;
            } else {
                ob_start();
                require $path;
                $output = ob_get_clean();
            }

            $rendered_parts[] = $output;
        }

        
        // replace placeholders with content (starting from the inner-most file)
        $content = $rendered_parts[0];
        for ($i = 1; $i < count($rendered_parts); $i++) {
            $content = str_replace($this->getPlaceholder(), $rendered_parts[$i], $content);
        }

        return $content;

    }

    /**
     * Generates a unique placeholder for dynamic content.
     * It's always the same for the same request to decrease the chance of conflicts.
     * @return string
     * @throws \Exception
     */
    public function getPlaceholder()
    {
        $time_float = $_SERVER['REQUEST_TIME_FLOAT'];
        $time_int = intval($time_float * 1000);

        return '<CONTENT_PLACEHOLDER_' . $time_int . '>';
    }

    public static function make404($error = "Page not found")
    {
        http_response_code(404);
        throw new \Exception($error);
    }

    /**
     * Returns the list of files to render. First file in the list is the top-most file in the hierarchy (for example, the outer-most layout)
     * @return array
     * @throws \Exception
     */
    private function getRenderList(): array
    {
        // handle exit condition
        if (count($this->url_parts) == 0 || (count($this->url_parts) == 1 && $this->url_parts[0] == '')) {

            // if last is directory, add index.php
            if (is_dir($this->current_path)) {
                if (file_exists($this->current_path . '/index.php')) {
                    $this->render_list[] = $this->current_path . '/index.php';
                } else {
                    Router::make404("Index file not found");
                }
            }

            return $this->render_list;
        }

        $current_url_part = $this->url_parts[0];
        array_shift($this->url_parts);

        // if directory exists
        if (is_dir($this->current_path . '/' . $current_url_part)) { // if we have layout, add it

            if (file_exists($this->current_path . '/' . $current_url_part . '/' . self::$LAYOUT_FILENAME)) {
                $this->render_list[] = $this->current_path . '/' . $current_url_part . '/' . self::$LAYOUT_FILENAME;
            }

            $this->current_path .= '/' . $current_url_part;
            return $this->getRenderList();
        } else if (file_exists($this->current_path . '/' . $current_url_part)) { // if exact file exists

            $this->render_list[] = $this->current_path . '/' . $current_url_part;

            $this->current_path .= '/' . $current_url_part;

            return $this->getRenderList();
        } else if (file_exists($this->current_path . '/' . $current_url_part . '.php')) { // if exact file exists (PHP)

            $this->render_list[] = $this->current_path . '/' . $current_url_part . '.php';

            $this->current_path .= '/' . $current_url_part;

            return $this->getRenderList();

        } else if ($dynamic_obj = $this->getDynamic($this->current_path)) { // if dynamic part exists

            if ($dynamic_obj["type"] == "file") {
                $this->render_list[] = $this->current_path . '/' . $dynamic_obj["full_name"];
                $this->dynamic_data[$dynamic_obj["name"]] = $current_url_part;

                $this->current_path .= '/' . $dynamic_obj["full_name"];

                return $this->getRenderList();
            } else {
                // if we have layout, add it
                if (file_exists($this->current_path . '/' . $dynamic_obj["full_name"] . '/' . self::$LAYOUT_FILENAME)) {
                    $this->render_list[] = $this->current_path . '/' . $dynamic_obj["full_name"] . '/' . self::$LAYOUT_FILENAME;
                }
                $this->dynamic_data[$dynamic_obj["name"]] = $current_url_part;
                $this->current_path .= '/' . $dynamic_obj["full_name"];
                return $this->getRenderList();
            }
        } else {
            Router::make404();
        }
    }

    /**
     * Returns the dynamic file if it exists
     * Throws an error if it does not exist or if there are multiple dynamic files
     */
    private function getDynamic($folder_path)
    {
        // must be directory
        if (!is_dir($folder_path)) {
            return false;
        }

        $files = scandir($folder_path);

        $dynamic_objects = [];

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $folder_path . '/' . $file;
            if (is_dir($path) && strpos($file, '[') === 0 && strpos($file, ']') === strlen($file) - 1) {
                $dynamic_objects[] = $file;
            } else if (is_file($path)) {
                // must be PHP file
                if (strpos($file, '.php') !== strlen($file) - 4) {
                    continue;
                }

                if (strpos($file, '[') === 0 && strpos($file, ']') === strlen($file) - 5) {
                    $dynamic_objects[] = $file;
                }
            }
        }

        if (count($dynamic_objects) == 0) {
            return false;
        } else if (count($dynamic_objects) == 1) {
            $dynamic_path = $dynamic_objects[0];


            $type = is_file($folder_path . '/' . $dynamic_path) ? "file" : "directory";

            // remove extension (if file)
            if ($type == "file") {
                $dynamic_name = str_replace('.php', '', $dynamic_path);
            } else {
                $dynamic_name = $dynamic_path;
            }

            // remove brackets
            $dynamic_name = substr($dynamic_name, 1, strlen($dynamic_name) - 2);

            return [
                "name" => $dynamic_name,
                "full_name" => $dynamic_path,
                "type" => $type
            ];
        } else {
            http_response_code(500);
            throw new \Exception("Multiple dynamic files found");
        }
    }
}
