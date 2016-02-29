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
     * Static properties
     */
    private static $default_concurrency = 6;
    private static $default_throw = true;
    private static $self;

    /**
     * Instance properties
     *
     * *Stack ID* means...
     *   - Generator ID
     *   - "wait" (Co::wait calls)
     *   - "async" (Co::async calls)
     */
    private $mh;                          // curl_multi_init()
    private $count = 0;                   // count(curl_multi_add_handle called)
    private $concurrency = 6;             // Limit of TCP connections
    private $throw = true;                // Throw CURLExceptions?
    private $queue = array();             // cURL resources over concurrency limits are temporalily stored here
    private $tree = array();              // array<*Stack ID*, mixed>
    private $values = array();            // array<*Stack ID*|*cURL ID*, Generator|resource<cURL>>
    private $value_to_parent = array();   // array<*Stack ID*|*cURL ID*, *Stack ID*>
    private $value_to_children = array(); // array<*Stack ID*, array<*Stack ID*|*cURL ID*, true>>
    private $value_to_keylist = array();  // array<*Stack ID*|*cURL ID*, array<mixed>>

    /**
     * Get and set default configurations.
     *
     * @access public
     * @static
     * @param int $concurrency
     * @param bool $throw
     */
    public static function getDefaultConcurrency()
    {
        return self::$default_concurrency;
    }
    public static function setDefaultConcurrency($concurrency)
    {
        self::$default_concurrency = self::validateConcurrency($concurrency);
    }
    public static function setDefaultThrow($throw)
    {
        self::$default_throw = (bool)$throw;
    }
    public static function getDefaultThrow()
    {
        return self::$default_throw;
    }

    /**
     * Wait all cURL requests to be completed.
     * Options override static defaults.
     *
     * @access public
     * @static
     * @param mixed $value
     * @param int? $concurrency
     * @param bool? $throw
     * @see self::__construct()
     */
    public static function wait($value, $concurrency = null, $throw = null)
    {
        $concurrency =
            $concurrency !== null
            ? self::validateConcurrency($concurrency)
            : self::$default_concurrency
        ;
        $throw =
            $throw !== null
            ? (bool)$throw
            : self::$default_throw
        ;
        // This function call must be atomic.
        try {
            if (self::$self) {
                throw new \BadMethodCallException(
                    'Co::wait() is already running. Use Co::async() instead.'
                );
            }
            self::$self = new self($value, $concurrency, $throw);
            $enqueued = self::$self->initialize($value, 'wait');
            if ($enqueued) {
                self::$self->run();
            }
            $result = self::$self->tree['wait'];
            self::$self = null;
            return $result;
        } catch (\Throwable $e) { // for PHP 7+
            self::$self = null;
            throw $e;
        } catch (\Exception $e) { // for PHP 5
            self::$self = null;
            throw $e;
        }
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
     * @param mixed $value
     * @param int $concurrency
     * @param bool $throw
     * @see self::initialize(), self::run()
     */
    private function __construct($value, $concurrency, $throw)
    {
        $this->mh = curl_multi_init();
        $this->concurrency = $concurrency;
        $this->throw = $throw;
    }

    /**
     * Call curl_multi_add_handle or push into waiting queue.
     *
     * @access private
     * @param resource<cURL> $curl
     */
    private function enqueue($curl)
    {
        if ($this->count < $this->concurrency) {
            // If within concurrency limit...
            if (CURLM_OK !== $errno = curl_multi_add_handle($this->mh, $curl)) {
                $msg = curl_multi_strerror($errno) . ": $curl";
                if ($errno === CURLM_BAD_HANDLE || $errno === CURLM_BAD_EASY_HANDLE) {
                    // These errors are caused by users mistake.
                    throw new \InvalidArgumentException($msg);
                } else {
                    // These errors are by internal reason.
                    throw new \RuntimeException($msg);
                }
            }
            ++$this->count;
        } else {
            // Else...
            if (isset($this->queue[(string)$curl])) {
                throw new \InvalidArgumentException("The cURL resource is already enqueued: $curl");
            }
            $this->queue[(string)$curl] = $curl;
        }
    }

    /**
     * Set or overwrite tree of return values.
     *
     * @access private
     * @param mixed $value mixed
     * @param string $parent_hash      *Stack ID*
     * @param array<string>? $keylist  Queue of keys for its hierarchy.
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
     * @param Generator|resource<cURL> $value
     * @param string $parent_hash              *Stack ID* or *cURL ID*
     * @param array? $keylist                  Queue of keys for its hierarchy.
     */
    private function setTable($value, $parent_hash, array $keylist = array())
    {
        $hash = is_object($value) ? spl_object_hash($value) : (string)$value;
        $this->values[$hash] = $value;
        $this->value_to_parent[$hash] = $parent_hash;
        $this->value_to_children[$parent_hash][$hash] = true;
        $this->value_to_keylist[$hash] = $keylist;
        $parent =
            isset($this->values[$parent_hash]) // Is in Generator stack?
            ? $this->values[$parent_hash]
            : null
        ;
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
        $parent =
            isset($this->values[$parent_hash])
            ? $this->values[$parent_hash]
            : null
        ;
        // Clear self table.
        if (isset($this->queues[$hash])) {
            unset($this->queues[$hash]);
        }
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
     * Run curl_multi_exec() loop.
     *
     * @access private
     * @see self::updateCurl(), self::enqueue()
     */
    private function run()
    {
        curl_multi_exec($this->mh, $active); // Start requests.
        do {
            curl_multi_select($this->mh, 1.0); // Wait events.
            curl_multi_exec($this->mh, $active); // Update resources.
            // NOTE: DO NOT call curl_multi_remove_handle
            //       or curl_multi_add_handle while looping curl_multi_info_read!
            $entries = array();
            do if ($entry = curl_multi_info_read($this->mh, $remains)) {
                $entries[] = $entry;
            } while ($remains);
            // Remove done and consume queue.
            foreach ($entries as $entry) {
                curl_multi_remove_handle($this->mh, $entry['handle']);
                --$this->count;
                if ($curl = array_shift($this->queue)) {
                    $this->enqueue($curl);
                }
            }
            // Update cURL and Generator stacks.
            foreach ($entries as $entry) {
                $this->updateCurl($entry['handle'], $entry['result']);
            }
        } while ($this->count > 0 || $this->queue);
        // All request must be done when reached here.
        if ($active) {
            throw new \LogicException('Unreachable statement.');
        }
    }

    /**
     * Unset table of dependencies.
     *
     * @access private
     * @param mixed $value
     * @param string $parent_hash  *Stack ID* or *cURL ID*
     * @param array? $keylist      Queue of keys for its hierarchy.
     * @return bool                Enqueued?
     */
    private function initialize($value, $parent_hash, array $keylist = array())
    {
        $value = self::normalize($value);
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
            while (self::isGeneratorRunning($value)) {
                $current = self::normalize($value->current());
                // Call recursively
                $enqueued = $this->initialize($current, $hash);
                if ($enqueued) { // If cURL resource found?
                    $this->setTable($value, $parent_hash, $keylist);
                    return true;
                }
                // Search more...
                $value->send($current);
            }
            $value = self::getGeneratorReturn($value);
            // Replace current tree with new value
            $this->unsetTree($hash);
            return $this->initialize($value, $parent_hash, $keylist);
        }
        // cURL resource
        if (self::isCurl($value)) {
            $hash = (string)$value;
            $this->enqueue($value);
            $this->setTree($value, $parent_hash, $keylist);
            $this->setTable($value, $parent_hash, $keylist);
            return true;
        }
        // Other
        $this->setTree($value, $parent_hash, $keylist);
        return false;
    }

    /**
     * Update tree with cURL result.
     *
     * @access private
     * @param resource<cURL> $value
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
        if ($errno !== CURLE_OK && $parent && $this->canThrow($parent)) {// Error and is to be thrown into Generator?
            $this->unsetTree($hash); // No more needed
            $parent->throw($result);
            $this->updateGenerator($parent);
        } elseif ($errno !== CURLE_OK && !$parent && $this->throw) { // Error and is to be thrown globally?
            $this->unsetTree($hash); // No more needed
            throw $result;
        } elseif ($parent_hash === 'async') { // Co::async() complete?
            $this->unsetTree($hash); // No more needed
        } elseif ($parent && !$this->value_to_children[$parent_hash]) { // Generator complete?
            $this->unsetTree($hash); // No more needed
            $result = $this->tree[$parent_hash];
            $parent->send($result);
            $this->updateGenerator($parent);
        }
    }

    /**
     * Check current Generator can throw a CURLException.
     *
     * @access private
     * @param Generator $value
     * @return bool
     */
    private function canThrow(\Generator $value)
    {
        while (true) {
            $key = $value->key();
            if ($key === self::SAFE) {
                return false;
            }
            if ($key === self::UNSAFE) {
                return true;
            }
            $parent_hash = $this->value_to_parent[spl_object_hash($value)];
            if (!isset($this->values[$parent_hash])) {
                return $this->throw;
            }
            $value = $this->values[$parent_hash];
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

    /**
     * Validate concurrency value.
     *
     * @access private
     * @static
     * @param int|float|string $concurrency
     * @return int
     */
    private static function validateConcurrency($concurrency)
    {
        if (!is_numeric($concurrency)) {
            throw new \InvalidArgumentException('Concurrency must be a valid number');
        }
        $concurrency = (int)$concurrency;
        if ($concurrency <= 0) {
            throw new \InvalidArgumentException('Concurrency must be a positive integer');
        }
        return $concurrency;
    }

    /**
     * Normalize value.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return miexed
     */
    private static function normalize($value)
    {
        while ($value instanceof \Closure) {
            $value = $value();
        }
        if (self::isArrayLike($value)
            && !is_array($value)
            && !$value->valid()) {
            $value = array();
        }
        return $value;
    }

    /**
     * Check if a Generator is running.
     * This method supports psuedo return with Co::RETURN_WITH.
     *
     * @access private
     * @static
     * @param Generator $value
     * @return bool
     */
    private static function isGeneratorRunning(\Generator $value)
    {
        $value->current();
        return $value->valid() && $value->key() !== self::RETURN_WITH; // yield Co::RETURN_WITH => XX
    }

    /**
     * Get return value from a Generator.
     * This method supports psuedo return with Co::RETURN_WITH.
     *
     * @access private
     * @static
     * @param Generator $value
     * @return bool
     */
    private static function getGeneratorReturn(\Generator $value)
    {
        $value->current();
        if ($value->valid() && $value->key() === self::RETURN_WITH) {  // yield Co::RETURN_WITH => XX
            return $value->current();
        }
        if ($value->valid()) {
            throw new \LogicException('Unreachable statement.');
        }
        return method_exists($value, 'getReturn') ? $value->getReturn() : null;
    }

    /**
     * Check if value is a valid cURL resource.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return bool
     */
    private static function isCurl($value)
    {
        return is_resource($value) && get_resource_type($value) === 'curl';
    }

    /**
     * Check if value is a valid Generator.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return bool
     */
    private static function isGenerator($value)
    {
        return $value instanceof \Generator;
    }

    /**
     * Check if value is a valid array or Traversable, not a Generator.
     *
     * @access private
     * @static
     * @param mixed $value
     * @return bool
     */
    private static function isArrayLike($value)
    {
        return $value instanceof \Traversable && !$value instanceof \Generator
               || is_array($value);
    }

    /**
     * Flatten an array or a Traversable.
     *
     * @access private
     * @static
     * @param mixed $value
     * @param array &$carry
     * @return array<mixed>
     */
    private static function flatten($value, &$carry = array())
    {
        if (!self::isArrayLike($value)) {
            $carry[] = $value;
        } else {
            foreach ($value as $v) {
                self::flatten($v, $carry);
            }
        }
        return func_num_args() <= 1 ? $carry : null;
    }

}
