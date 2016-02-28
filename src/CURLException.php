<?php

namespace mpyw\Co;

/**
 * Asynchronus cURL executor simply based on resource and Generator.
 * http://github.com/mpyw/co
 *
 * @author mpyw
 * @license MIT
 */

class CURLException extends \RuntimeException
{
    private $handle;

    /**
     *  Constructor.
     *
     * @access public
     * @param string $message
     * @param int $code
     * @param resource<cURL> $handle
     */
    public function __construct($message, $code, $handle)
    {
        parent::__construct($message, $code);
        $this->handle = $handle;
    }

    /**
     * Get cURL handle.
     *
     * @access public
     * @return resource<cURL> $handle
     */
    public function getHandle()
    {
        return $this->handle;
    }
}
