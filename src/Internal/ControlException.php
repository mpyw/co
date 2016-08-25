<?php

namespace mpyw\Co\Internal;

class ControlException extends \Exception
{
    private $value;
    private $cancel;

    /**
     * Constructor.
     * @param mixed    $value   Value to be retrived.
     * @param bool     $cancel  Cancel uncompleted promises if possible.
     */
    public function __construct($value, $cancel = false)
    {
        parent::__construct();
        $this->value = $value;
        $this->cancel = $cancel;
    }

    /**
     * Get value.
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Return if canceler.
     * @return bool
     */
    public function isCanceler()
    {
        return $this->cancel;
    }
}
