<?php

namespace AnzeBlaBla\Simplite;

class Sanitizer
{
    static function sanitize($input)
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    static function url_encode($input)
    {
        return urlencode($input);
    }
}
