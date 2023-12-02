<?php

use AnzeBlaBla\Simplite\Application;

class I18N
{
    static $translations = null;
    private static function getTranslations()
    {
        if (self::$translations === null) {
            self::$translations = Application::getInstance()->getConfig("translations");
        }
        return self::$translations;
    }
    public static function translate($key, $params = [])
    {
        self::getTranslations();
        if (isset(self::$translations[$key])) {
            $text = self::$translations[$key];
            foreach ($params as $param => $value) {
                $text = preg_replace("/\?/", $value, $text, 1);
            }
            return $text;
        } else {
            return $key;
        }
    }

    // alias for translate
    public static function t($key, $params = [])
    {
        return self::translate($key, $params);
    }

    // Capitalize first letter of each word
    public static function title($key)
    {
        return ucwords(self::translate($key));
    }

    // Json tranlations string to pass to JS
    public static function json()
    {
        return json_encode(self::getTranslations());
    }
}
