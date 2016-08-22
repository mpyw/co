<?php

namespace mpyw\Co;

class AllFailedException extends \RuntimeException
{
    private $reasons;

    /**
     * Constructor.
     * @param string   $message Dummy.
     * @param int      $code    Dummy.
     * @param mixed    $value   Value to be retrived.
     */
    public function __construct($message, $code, $reasons)
    {
        parent::__construct($message, $code);
        $this->reasons = $reasons;
    }

    /**
     * Get value.
     * @return mixed
     */
    public function getReasons()
    {
        return $this->reasons;
    }
}
