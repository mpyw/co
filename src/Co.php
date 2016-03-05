<?php

namespace mpyw\Co;

/**
 * Asynchronous cURL executor simply based on resource and Generator.
 * http://github.com/mpyw/co
 *
 * @author mpyw
 * @license MIT
 */

class Co
{

    /**
     * Special constants used for Generator yielding keys.
     *
     * @const Co::RETURN_WITH  Treat yielded value as returned value.
     *                         This is for PHP 5.5 ~ 5.6.
     * @const Co::UNSAFE       Allow current yield to throw Exceptions.
     * @const Co::SAFE         Forbid current yield to throw Exceptions.
     *                         Exceptions are just to be returned.
     */
    const RETURN_WITH = '__RETURN_WITH__';
    const RETURN_ = '__RETURN_WITH__'; // alias
    const RET = '__RETURN_WITH__'; // alias
    const RTN = '__RETURN_WITH__'; // alias
    const UNSAFE = '__UNSAFE__';
    const SAFE = '__SAFE__';

    /**
     * Static default options.
     */
    private static $defaults = array(
        'throw' => true, // Throw CURLExceptions?
        'pipeline' => false, // Use HTTP/1.1 pipelining?
        'multiplex' => true, // Use HTTP/2 multiplexing?
        'interval' => 0.5, // curl_multi_select() timeout
        'concurrency' => 6, // Limit of TCP connections
    );

    /**
     * Execution instance is stored here.
     */
    private static $self;

    /**
     * Instance properties
     *
     * *Stack ID* means...
     *   - Generator ID
     *   - "wait" (Co::wait calls)
     *   - "async" (Co::async calls)
     */
    private $options = array();
    private $tree = array();              // array<*Stack ID*, mixed>
    private $pool;

    private $values = array();            // array<*Stack ID*|*cURL ID*, Generator|resource>
    private $value_to_parent = array();   // array<*Stack ID*|*cURL ID*, *Stack ID*>
    private $value_to_children = array(); // array<*Stack ID*, array<*Stack ID*|*cURL ID*, true>>
    private $value_to_keylist = array();  // array<*Stack ID*|*cURL ID*, array<mixed>>

    /**
     * Override or get default settings.
     *
     * @access public
     * @static
     * @param array $options
     */
    public static function setDefaultOptions(array $options)
    {
        self::$defaults = self::validateOptions($options);
    }
    public static function getDefaultOptions()
    {
        return self::$defaults;
    }

    /**
     * Wait all cURL requests to be completed.
     * Options override static defaults.
     *
     * @access public
     * @static
     * @param mixed $value
     * @param array $options
     * @see self::__construct()
     */
    public static function wait($value, array $options = array())
    {
        $options = self::validateOptions($options) + self::$defaults;
        // This function call must be atomic.
        try {
            if (self::$self) {
                throw new \BadMethodCallException(
                    'Co::wait() is already running. Use Co::async() instead.'
                );
            }
            self::$self = new self($options);
            if (self::$self->initialize($value, 'wait')) {
                self::$self->run();
            }
            $result = self::$self->tree['wait'];
            self::$self = null;
            return $result;
        } catch (\Throwable $e) { } catch (\Exception $e) { } // For both PHP7+ and PHP5
        self::$self = null;
        throw $e;
    }

    /**
     * Parallel execution along with Co::async().
     * This method is mainly expected to be used in CURLOPT_WRITEFUNCTION callback.
     *
     * @access public
     * @static
     * @param mixed $value
     * @see self::__construct()
     */
    public static function async($value)
    {
        // This function must be called along with Co::wait().
        if (!self::$self) {
            throw new \BadMethodCallException(
                'Co::async() must be called along with Co::wait(). ' .
                'This method is mainly expected to be used in CURLOPT_WRITEFUNCTION callback.'
            );
        }
        self::$self->initialize($value, 'async');
    }

    /**
     * Internal constructor.
     *
     * @access private
     * @param array $options
     * @see self::initialize(), self::run()
     */
    private function __construct(array $options)
    {
        $this->mh = curl_multi_init();
        if (function_exists('curl_multi_setopt')) {
            $flags = ($options['pipeline'] ? 1 : 0) | ($options['multiplex'] ? 2 : 0);
            curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, $flags);
        }
        $this->options = $options;
    }

    /**
     * Set or overwrite tree of return values.
     *
     * @access private
     * @param mixed $value mixed
     * @param string $parent_hash *Stack ID*
     * @param array $keylist Queue of keys for its hierarchy.
     */
    private function setTree($value, $parent_hash, array $keylist = array())
    {
        $current = &$this->tree[$parent_hash];
        while (null !== $key = array_shift($keylist)) {
            if (!is_array($current)) {
                $current = array();
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    /**
     * Unset tree of return values.
     *
     * @access private
     * @param string $hash *Stack ID* or *cURL ID*
     */
    private function unsetTree($hash)
    {
        if (isset($this->tree[$hash])) {
            foreach (self::flatten($this->tree[$hash]) as $v) {
                if (self::isGenerator($v)) {
                    $this->unsetTree(spl_object_hash($v));
                }
            }
            unset($this->tree[$hash]);
        }
    }

    /**
     * Set table of dependencies.
     *
     * @access private
     * @param Generator|resource $value
     * @param string $parent_hash *Stack ID* or *cURL ID*
     * @param array $keylist Queue of keys for its hierarchy.
     */
    private function setTable($value, $parent_hash, array $keylist = array())
    {
        $hash = is_object($value) ? spl_object_hash($value) : (string)$value;
        $this->values[$hash] = $value;
        $this->value_to_parent[$hash] = $parent_hash;
        $this->value_to_children[$parent_hash][$hash] = true;
        $this->value_to_keylist[$hash] = $keylist;
    }

    /**
     * Unset table of dependencies.
     *
     * @access private
     * @param string $hash *Stack ID* or *cURL ID*
     */
    private function unsetTable($hash)
    {
        $parent_hash = $this->value_to_parent[$hash];
        // Clear self table.
        unset($this->queue[$hash]);
        unset($this->values[$hash]);
        unset($this->value_to_parent[$hash]);
        unset($this->value_to_keylist[$hash]);
        // Clear descendants tables.
        // (This is required for cases that
        //  some cURL resources are abondoned because of Exceptions thrown)
        if (isset($this->value_to_children[$hash])) {
            foreach ($this->value_to_children[$hash] as $child => $_) {
                $this->unsetTable($child);
            }
            unset($this->value_to_children[$hash]);
        }
        // Clear reference from ancestor table.
        if (isset($this->value_to_children[$parent_hash][$hash])) {
            unset($this->value_to_children[$parent_hash][$hash]);
        }
    }

    /**
     * Unset table of dependencies.
     *
     * @access private
     * @param mixed $value
     * @param string $parent_hash  *Stack ID* or *cURL ID*
     * @param array $keylist       Queue of keys for its hierarchy.
     * @return bool                Enqueued?
     */
    private function initialize($value, $parent_hash, array $keylist = array())
    {
        // Array or Traversable
        if (self::isArrayLike($value)) {
            $this->setTree($value, $parent_hash, $keylist);
            $enqueued = false;
            foreach ($value as $k => $v) {
                // Append current key and call recursively
                $tmp_keylist = $keylist;
                $tmp_keylist[] = $k;
                $enqueued = $this->initialize($v, $parent_hash, $tmp_keylist) || $enqueued;
            }
            return $enqueued;
        }
        // Generator
        if (self::isGenerator($value)) {
            $hash = spl_object_hash($value);
            if (isset($this->values[$hash])) {
                throw new \InvalidArgumentException("The Genertor is already running: #$hash");
            }
            $this->setTree($value, $parent_hash, $keylist);
            $this->setTable($value, $parent_hash, $keylist);
            try {
                while (self::isGeneratorRunning($value)) {
                    // Call recursively
                    $current = $value->current();
                    $enqueued = $this->initialize($current, $hash);
                    if ($enqueued) { // If cURL resource found?
                        $this->setTable($value, $parent_hash, $keylist);
                        return true;
                    }
                    // Search more...
                    $value->send($current);
                }
                $value = self::getGeneratorReturn($value);
            } catch (\RuntimeException $value) {
                $this->throwIfCan($parent_hash, $value);
            }
            $this->unsetTree($hash);
            $this->unsetTable($hash);
            // Replace current tree with new value
            return $this->initialize($value, $parent_hash, $keylist);
        }
        // cURL resource
        if (self::isCurl($value)) {
            $hash = (string)$value;
            try {
                $this->enqueue($value);
                $this->setTree($value, $parent_hash, $keylist);
                $this->setTable($value, $parent_hash, $keylist);
                return true;
            } catch (\RuntimeException $value) {
                $this->unsetTree($hash);
                $this->unsetTable($hash);
                $this->throwIfCan($parent_hash, $value);
                return $this->initialize($value, $parent_hash, $keylist);
            }
        }
        // Other
        try {
            $normalized = self::normalize($value);
            if ($normalized === $value) {
                $this->setTree($value, $parent_hash, $keylist);
                return false;
            }
            return $this->initialize($normalized, $parent_hash, $keylist);
        } catch (\RuntimeException $value) {
            $this->setTree($value, $parent_hash, $keylist);
            $this->throwIfCan($parent_hash, $value);
            return $this->initialize($value, $parent_hash, $keylist);
        }
    }

    /**
     * Update tree with cURL result.
     *
     * @access private
     * @param resource $value
     * @param int $errno
     * @see self::updateGenerator()
     */
    private function updateCurl($value, $errno)
    {
        $hash = (string)$value;
        if (!isset($this->values[$hash])) {
            return;
        }
        $parent_hash = $this->value_to_parent[$hash]; // *Stack ID*
        $parent = isset($this->values[$parent_hash]) ? $this->values[$parent_hash] : null; // Generator or null
        $keylist = $this->value_to_keylist[$hash];
        $result =
            $errno === CURLE_OK
            ? curl_multi_getcontent($value)
            : new CURLException(curl_error($value), $errno, $value)
        ;
        $this->setTree($result, $parent_hash, $keylist);
        $this->unsetTable($hash);
        try {
            if ($errno !== CURLE_OK && $this->throwIfCan($parent_hash, $result)) {
                $this->unsetTree($parent_hash);
                return $this->updateGenerator($parent);
            }
            if ($parent && !$this->value_to_children[$parent_hash]) {
                $result = $this->tree[$parent_hash];
                $this->unsetTree($parent_hash);
                $parent->send($result);
                return $this->updateGenerator($parent);
            }
            if ($parent_hash === 'async') {
                $this->unsetTree($parent_hash);
                return;
            }
        } catch (\RuntimeException $e) {
            $this->unsetTree($parent_hash);
            while (true) {
                $hash = $parent_hash;
                $this->unsetTree($hash);
                if ($hash === 'async' || $hash === 'wait') {
                    throw $e;
                }
                $parent_hash = $this->value_to_parent[$hash];
                $parent = isset($this->values[$parent_hash]) ? $this->values[$parent_hash] : null; // Generator or null
                $keylist = $this->value_to_keylist[$hash];
                $this->setTree($e, $parent_hash, $keylist);
                $this->unsetTable($hash);
                try {
                    if ($this->throwIfCan($parent_hash, $e)) {
                        $this->unsetTree($parent_hash);
                        return $this->updateGenerator($parent);
                    }
                    if ($parent && !$this->value_to_children[$parent_hash]) {
                        $result = $this->tree[$parent_hash];
                        $this->unsetTree($parent_hash);
                        $parent->send($result);
                        return $this->updateGenerator($parent);
                    }
                    break;
                } catch (\RuntimeException $e) { }
            }
        }
    }

    /**
     * Check current Generator can throw a CURLException.
     *
     * @access private
     * @param Generator $value
     * @return bool
     */
    private function throwIfCan($hash, \RuntimeException $e)
    {
        $value = isset($this->values[$hash]) ? $this->values[$hash] : null;
        if ($value && $value->key() === self::SAFE) {
            return false;
        }
        if ($value and $value->key() === self::UNSAFE || $this->options['throw']) {
            $value->throw($e);
            return true;
        }
        if ($this->options['throw']) {
            throw $e;
        }
    }

    /**
     * Update tree with updateCurl() result.
     *
     * @access private
     * @param Generator $value
     */
    private function updateGenerator(\Generator $value)
    {
        $hash = spl_object_hash($value);
        if (!isset($this->values[$hash])) {
            return;
        }
        while (self::isGeneratorRunning($value)) {
            $current = self::normalize($value->current());
            $enqueued = $this->initialize($current, $hash);
            if ($enqueued) { // cURL resource found?
                return;
            }
            // Search more...
            $value->send($current);
        }
        $value = self::getGeneratorReturn($value);
        $parent_hash = $this->value_to_parent[$hash];
        $parent = isset($this->values[$parent_hash]) ? $this->values[$parent_hash] : null;
        $keylist = $this->value_to_keylist[$hash];
        $this->unsetTable($hash);
        $this->unsetTree($hash);
        $enqueued = $this->initialize($value, $parent_hash, $keylist);
        if (!$enqueued && $parent && !$this->value_to_children[$parent_hash]) { // Generator complete?
            // Traverse parent stack.
            $next = $this->tree[$parent_hash];
            $this->unsetTree($parent_hash);
            $parent->send($next);
            $this->updateGenerator($parent);
        }
    }

}
