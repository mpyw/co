<?php

namespace mpyw\Co\Internal;

class CoOption implements \ArrayAccess
{
    /**
     * Field types.
     * @var array
     */
    private static $types = [
        'throw' => 'Bool', // Throw CURLExceptions?
        'pipeline' => 'Bool', // Use HTTP/1.1 pipelining?
        'multiplex' => 'Bool', // Use HTTP/2 multiplexing?
        'autoschedule' => 'Bool', // Use AutoScheduler?
        'interval' => 'NaturalFloat', // curl_multi_select() timeout
        'concurrency' => 'NaturalInt', // Limit of TCP connections
    ];

    /**
     * Default values.
     * @var array
     */
    private static $defaults = [
        'throw' => true,
        'pipeline' => false,
        'multiplex' => true,
        'autoschedule' => false,
        'interval' => 0.002,
        'concurrency' => 6,
    ];

    /**
     * Actual values.
     * @var array
     */
    private $options;

    /**
     * Set default options.
     * @param array $options
     */
    public static function setDefault(array $options)
    {
        self::$defaults = self::validateOptions($options) + self::$defaults;
    }

    /**
     * Get default options.
     * @return array $options
     */
    public static function getDefault()
    {
        return self::$defaults;
    }

    /**
     * Constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = self::validateOptions($options) + self::$defaults;
    }

    /**
     * Reconfigure to get new instance.
     * @return CoOption
     */
    public function reconfigure(array $options)
    {
        return new self($options + $this->options);
    }

    /**
     * Implemention of ArrayAccess.
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->options[$offset]);
    }

    /**
     * Implemention of ArrayAccess.
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (!isset($this->options[$offset])) {
            throw new \DomainException('Undefined field: ' + $offset);
        }
        return $this->options[$offset];
    }

    /**
     * Implemention of ArrayAccess.
     * @param  mixed $offset
     * @param  mixed $value
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('The instance of CoOptions is immutable.');
    }

    /**
     * Implemention of ArrayAccess.
     * @param  mixed $offset
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('The instance of CoOptions is immutable.');
    }

    /**
     * Validate options.
     * @param  array $options
     * @return array
     */
    private static function validateOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (!isset(self::$types[$key])) {
                throw new \DomainException("Unknown option: $key");
            }
            if ($key === 'autoschedule' && !defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
                throw new \OutOfBoundsException('"autoschedule" can be used only on PHP 7.0.7 or later.');
            }
            $validator = [__CLASS__, 'validate' . self::$types[$key]];
            $options[$key] = $validator($key, $value);
        }
        return $options;
    }

    /**
     * Validate bool value.
     * @param  string $key
     * @param  mixed  $value
     * @throws InvalidArgumentException
     * @return bool
     */
    private static function validateBool($key, $value)
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, [
            'flags' => FILTER_NULL_ON_FAILURE,
        ]);
        if ($value === null) {
            throw new \InvalidArgumentException("Option[$key] must be boolean.");
        }
        return $value;
    }

    /**
     * Validate natural float value.
     * @param  string $key
     * @param  mixed  $value
     * @throws InvalidArgumentException
     * @return float
     */
    private static function validateNaturalFloat($key, $value)
    {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            throw new \InvalidArgumentException("Option[$key] must be float.");
        }
        if ($value < 0.0) {
            throw new \DomainException("Option[$key] must be positive or zero.");
        }
        return $value;
    }

    /**
     * Validate natural int value.
     * @param  string $key
     * @param  mixed  $value
     * @throws InvalidArgumentException
     * @return int
     */
    private static function validateNaturalInt($key, $value)
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new \InvalidArgumentException("Option[$key] must be integer.");
        }
        if ($value < 0) {
            throw new \DomainException("Option[$key] must be positive or zero.");
        }
        return $value;
    }
}
