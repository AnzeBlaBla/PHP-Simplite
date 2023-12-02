<?php

// https://stackoverflow.com/a/2071048/1478566
set_error_handler(function ($code, $message, $filename, $lineno) {
    throw new ErrorException($message, $code, 0, $filename, $lineno);
});

// The above code is necessary to terminate program in all of the
// following cases:

// PHP Warning: strpos() expects at least 2 parameters, 0 given
strpos();

// PHP Warning: file_get_contents(/no/file): failed to open stream: No
// such file or directory
file_get_contents('/no/file');

// PHP Notice: Undefined variable: foobar
echo $foobar, PHP_EOL;

// PHP Notice: Notice: Undefined offset: 5
echo [1,2,3][5], PHP_EOL;