<?php

namespace mpyw\Co;

class AllFailedException extends \RuntimeException
{
    private $reasons;

    /**
     * Constructor.
     * @param string   $message Dummy message.
     * @param int      $code    Always zero.
     * @param mixed    $reasons Reasons why jobs are failed.
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
