<?php

namespace mpyw\Co\Internal;

class ControlException extends \RuntimeException
{
    private $value;

    /**
     * Constructor.
     * @param string   $message Dummy.
     * @param int      $code    Dummy.
     * @param mixed    $value   Value to be retrived.
     */
    public function __construct($message, $code, $value)
    {
        parent::__construct($message, $code);
        $this->value = $value;
    }

    /**
     * Get value.
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
