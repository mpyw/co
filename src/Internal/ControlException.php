<?php

namespace mpyw\Co\Internal;

class ControlException extends \Exception
{
    private $value;

    /**
     * Constructor.
     * @param mixed    $value   Value to be retrived.
     */
    public function __construct($value)
    {
        parent::__construct();
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
