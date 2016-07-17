<?php

namespace mpyw\Co;

class CURLException extends \RuntimeException
{
    private $handle;

    /**
     *  Constructor.
     * @param string   $message
     * @param int      $code
     * @param resource $handle
     */
    public function __construct($message, $code, $handle)
    {
        parent::__construct($message, $code);
        $this->handle = $handle;
    }

    /**
     * Get cURL handle.
     * @return resource $handle
     */
    public function getHandle()
    {
        return $this->handle;
    }
}
