<?php

namespace mpyw\Co\Internal;

class CoOption implements \ArrayAccess
{
    /**
     * Field types.
     * @const array
     */
    const TYPES = [
        'throw' => 'Bool', // Throw CURLExceptions?
        'pipeline' => 'Bool', // Use HTTP/1.1 pipelining?
        'multiplex' => 'Bool', // Use HTTP/2 multiplexing?
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
        'interval' => 0.5,
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
    public static function setDefaultOptions(array $options)
    {
        self::$defaults = self::validateOptions($options) + self::$defaults;
    }

    /**
     * Get default options.
     * @param array $options
     */
    public static function getDefaultOptions()
    {
        return self::$defaults;
    }

    /**
     * Constructor.
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = self::validateOptions($options) + self::$defaults;
    }

    /**
     * Implemention of ArrayAccess.
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset(self::$types[$offset]);
    }

    /**
     * Implemention of ArrayAccess.
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \DomainException('Undefined field: ' + $offset);
        }
        return self::$defaults[$offset];
    }

    /**
     * Implemention of ArrayAccess.
     * @param mixed $offset
     * @param mixed $value
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('The instance of CoOptions is immutable.');
    }

    /**
     * Implemention of ArrayAccess.
     * @param mixed $offset
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('The instance of CoOptions is immutable.');
    }

    /**
     * Validate options.
     * @param array $options
     * @return array
     */
    private static function validateOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (!isset(self::TYPES[$key])) {
                throw new \InvalidArgumentException("Unknown option: $key");
            }
            $validator = [__CLASS__, 'validate' . self::TYPES[$key]];
            $options[$key] = $validator($key, $value);
        }
        return $options;
    }

    /**
     * Validate bool value.
     * @param  string $key
     * @param  mixed $value
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
     * @param  mixed $value
     * @throws InvalidArgumentException
     * @return float
     */
    private static function validateNaturalFloat($key, $value)
    {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($value === false || $value < 0.0) {
            throw new \InvalidArgumentException("Option[$key] must be positive float or zero.");
        }
        return $value;
    }

    /**
     * Validate natural int value.
     * @param  string $key
     * @param  mixed $value
     * @throws InvalidArgumentException
     * @return int
     */
    private static function validateNaturalInt($key, $value)
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            throw new \InvalidArgumentException("Option[concurrency] must be positive integer or zero.");
        }
        return $value;
    }
}
