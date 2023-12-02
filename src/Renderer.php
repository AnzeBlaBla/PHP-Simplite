<?php

namespace AnzeBlaBla\Simplite;

class Renderer
{
    static function css($file)
    {
        return '<link rel="stylesheet" href="/assets/css/' . $file . '.css">';
    }
    
    static function js($file)
    {
        return '<script src="/assets/js/' . $file . '.js"></script>';
    }
    
    static $components_root = null;
    static $component_index = 0; // Used for unique component IDs
    static function component($file, $data = []): string
    {
        if (!self::$components_root) {
            self::$components_root = Application::getInstance()->getConfig('root_directory') . '/' . Application::getInstance()->getConfig('components_folder');
        }

        $path = self::$components_root . '/' . $file . '.php';
        
        if (file_exists($path)) {
            // Extract data
            extract($data);

            $app = Application::getInstance();

            // Get component ID
            $COMPONENT_ID = 'component_' . self::$component_index;
            self::$component_index++;
            
            ob_start();
            // Include component
            include $path;
            // Get output
            $output = ob_get_contents();
            ob_end_clean();

            return $output;
        } else {
            return "ERROR: Component not found: " . $file;
        }
    }
}
