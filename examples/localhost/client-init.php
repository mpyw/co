<?php

require __DIR__ . '/../../vendor/autoload.php';
set_time_limit(0);

/**
 * REST API
 * @param  string $path
 * @param  array  $q
 * @return resource
 */
function curl(string $path, array $q = [])
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => true,
    ]);
    return $ch;
}

/**
 * Streaming API
 * @param  string   $path
 * @param  callable $callback
 * @param  array    $q
 * @return resource
 */
function curl_streaming(string $path, callable $callback, array $q = [])
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_WRITEFUNCTION => function ($ch, $buf) use ($callback) {
            $callback($buf);
            return strlen($buf);
        },
    ]);
    return $ch;
}

/**
 * Print elapsed time
 */
function print_time()
{
    static $start;
    if (!$start) {
        $start = microtime(true);
        echo "【Time】0.0 s\n";
    } else {
        $diff = sprintf('%.1f', microtime(true) - $start);
        echo "【Time】$diff s\n";
    }
}

/**
 * Unwrap exception message
 * @param  mixed $value
 * @return mixed
 */
function unwrap($value)
{
    if (is_array($value)) {
        return array_map(__FUNCTION__, $value);
    }
    if (!$value instanceof \RuntimeException) {
        return $value;
    }
    $class = get_class($value);
    $message = $value->getMessage();
    return "$class: $message";
}
