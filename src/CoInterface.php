<?php

namespace mpyw\Co;

interface CoInterface
{
    const RETURN_WITH = '__RETURN_WITH__';
    const RETURN_ = '__RETURN_WITH__'; // alias
    const RET = '__RETURN_WITH__'; // alias
    const RTN = '__RETURN_WITH__'; // alias
    const SAFE = '__SAFE__';
    const DELAY = '__DELAY__';
    const SLEEP = '__DELAY__'; // alias

    public static function setDefaultOptions(array $options);
    public static function getDefaultOptions();
    public static function wait($value, array $options = []);
    public static function async($value, $throw = null);
    public static function isRunning();
    public static function race($value);
    public static function any($value);
    public static function all($value);
}
