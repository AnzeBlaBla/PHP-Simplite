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

    /**
     * Generates a unique component ID
     * 
     * @return string The component ID
     */
    private static function nextComponentId()
    {
        // Convert request time to hex (so it's the same for all components on the same request)
        $timeHex = dechex($_SERVER['REQUEST_TIME_FLOAT']);        

        return 'SIMPLITE_' . $timeHex . self::$component_index++;
    }


    /**
     * Renders a component and returns the HTML output
     * 
     * @param string $file The component file name
     * @param array $data The data to pass to the component
     * @return string The HTML output
     */
    static function component($file, $data = []): string
    {
        return self::_renderComponent($file, $data)['output'];
    }

    /**
     * Renders a component, echoing the HTML output and returning the COMPONENT_ID
     * 
     * @param string $file The component file name
     * @param array $data The data to pass to the component
     * @return string The COMPONENT_ID
     */
    static function componentEcho($file, $data = []): string
    {
        $rendered = self::_renderComponent($file, $data);
        echo $rendered['output'];
        return $rendered['COMPONENT_ID'];
    }


    /**
     * Renders a component, returning it's rendered HTML and the COMPONENT_ID (Note: not meant to be called directly, use Renderer::component() instead)
     * 
     * @param string $file The component file name
     * @param array $data The data to pass to the component
     * 
     * @return array An array containing the rendered HTML and the COMPONENT_ID
     */
    static function _renderComponent($file, $data = []): array
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
            $COMPONENT_ID = self::nextComponentId();
            
            ob_start();
            // Include component
            include $path;
            // Get output
            $output = ob_get_contents();
            ob_end_clean();

            // If wrapping is enabled, wrap the component in comments
            if ($app->getConfig('wrap_components')) {
                $output = "<!-- BEGIN COMPONENT: $file (ID: $COMPONENT_ID) -->\n" . $output . "\n<!-- END COMPONENT: $file (ID: $COMPONENT_ID) -->";
            }

            return [
                'output' => $output,
                'COMPONENT_ID' => $COMPONENT_ID
            ];
        } else {
            return [
                'output' => "ERROR: Component not found: " . $file,
                'COMPONENT_ID' => null
            ];
        }
    }
}
