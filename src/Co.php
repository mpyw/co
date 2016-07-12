<?php

namespace mpyw\Co;
use mpyw\Co\Internal\Utils;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\

class Co implements CoInterface
{
    /**
     * [$self description]
     * @var [type]
     */
    private static $self;

    /**
     * [setDefaultOptions description]
     * @param array $options [description]
     */
    public static function setDefaultOptions(array $options)
    {
        CoOption::setDefaultOptions($options);
    }

    /**
     * [getDefaultOptions description]
     * @return [type] [description]
     */
    public static function getDefaultOptions()
    {
        return CoOption::getDefaultOptions();
    }

    public static function wait($value, array $options = array())
    {
        $options = new CoOptions($options);
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

    public static function async($value)
    {
        if (!self::$self) {
            throw new \BadMethodCallException(
                'Co::async() must be called along with Co::wait(). ' .
                'This method is mainly expected to be used in CURLOPT_WRITEFUNCTION callback.'
            );
        }
        self::$self->initialize($value, 'async');
    }

    private function initialize()
    {
        $
    }

}
