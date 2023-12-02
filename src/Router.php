<?php

namespace AnzeBlaBla\Simplite;


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
        // a hack to search the subfolder for layout, otherwise it's not done, because it looks only for the exact file or dir
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

        // reverse list
        $render_list = array_reverse($render_list);

        $output = null;

        foreach ($render_list as $path) {

            extract($this->dynamic_data);

            // Pass children content to parent (for use in layouts)
            if (isset($output)) {
                $content = $output;
            }

            $app = $this->app;

            if ($take_return) {
                $output = require $path;
            } else {
                ob_start();
                require $path;
                $output = ob_get_clean();
            }
        }
        return $output;
    }

    private function getRenderList(): array
    {
        // handle exit condition
        if (count($this->url_parts) == 0 || (count($this->url_parts) == 1 && $this->url_parts[0] == '')) {

            // if last is directory, add index.php
            if (is_dir($this->current_path)) {
                if (file_exists($this->current_path . '/index.php')) {
                    $this->render_list[] = $this->current_path . '/index.php';
                } else {
                    http_response_code(404);
                    throw new \Exception("Index file not found");
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
            
        } else if (file_exists($this->current_path . '/' . $current_url_part . '.php')) { // if exact file exists

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
            http_response_code(404);
            throw new \Exception("Page not found");
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
                // ignore non php
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
