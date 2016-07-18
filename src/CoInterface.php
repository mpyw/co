<?php

namespace mpyw\Co;

interface CoInterface
{
    const RETURN_WITH = '__RETURN_WITH__';
    const RETURN_ = '__RETURN_WITH__'; // alias
    const RET = '__RETURN_WITH__'; // alias
    const RTN = '__RETURN_WITH__'; // alias
    const UNSAFE = '__UNSAFE__';
    const SAFE = '__SAFE__';

    public static function setDefaultOptions(array $options);
    public static function getDefaultOptions();
    public static function wait($value, array $options = []);
    public static function async($value);
}
