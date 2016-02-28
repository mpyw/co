<?php

require __DIR__ . '/../vendor/autoload.php';
set_time_limit(0);

function curl($path, array $q = array()) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ));
    return $ch;
}
function curl_streaming($path, $callback, array $q = array()) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_WRITEFUNCTION => function ($ch, $buf) use ($callback) {
            $callback($buf);
            return strlen($buf);
        },
    ));
    return $ch;
}
