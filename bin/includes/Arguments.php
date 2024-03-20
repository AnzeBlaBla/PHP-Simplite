<?php

define('TRUE_VALUES', ['true', '1', 'yes', 'on', 'y', 't', 'enabled', 'enable']);
define('FALSE_VALUES', ['false', '0', 'no', 'off', 'n', 'f', 'disabled', 'disable']);


class Arguments
{

    public $arguments;
    private $consumedNumber = 0;
    private $options = [];

    public function __construct($arguments)
    {
        $this->arguments = $arguments;

        $this->parseOptions();
    }

    /**
     * Consumes next argument
     * @return string
     * @throws Exception
     */
    public function consume()
    {
        $this->consumedNumber++;

        if ($this->consumedNumber > count($this->arguments)) {
            throw new Exception("Not enough arguments");
        }

        return $this->arguments[$this->consumedNumber - 1];
    }


    /**
     * Returns true if there are more arguments
     * @return bool
     */
    public function hasMore()
    {
        return $this->consumedNumber < count($this->arguments);
    }

    /**
     * Returns true if the argument is present
     * @param string $option
     * @return bool
     */
    public function hasOption($option)
    {
        return isset($this->options[$option]);
    }

    /**
     * Returns the value of the option
     * @param string $option
     * @return string
     */
    public function getOption($option)
    {
        return $this->options[$option] ?? null;
    }

    /**
     * Parses options from the arguments
     * @return void
     */
    private function parseOptions()
    {
        $this->options = [];

        for ($i = 0; $i < count($this->arguments); $i++) {
            $arg = $this->arguments[$i];

            if (substr($arg, 0, 2) === '--') {
                $arg = substr($arg, 2);
                $parts = explode('=', $arg);
                $key = $parts[0];
                $value = $parts[1] ?? true;

                if (in_array(strtolower($value), TRUE_VALUES)) {
                    $value = true;
                } else if (in_array(strtolower($value), FALSE_VALUES)) {
                    $value = false;
                }
                $this->options[$key] = $value;
            }
        }
    }
}
