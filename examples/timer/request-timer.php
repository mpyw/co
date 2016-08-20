<?php

require __DIR__ . '/../../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

Co::wait(function () {
    yield [timer($stop), main($stop)];
});

function curl_init_with(string $url, array $options = [])
{
    $ch = curl_init();
    $options = array_replace([
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
    ], $options);
    curl_setopt_array($ch, $options);
    return $ch;
}

function timer(&$stop)
{
    $ms = 0;
    while (true) {
        yield CO::DELAY => 0.2;
        if ($stop) break;
        $ms += 200;
        echo "[Timer]: $ms miliseconds passed\n";
    }
}

function main(&$stop)
{
    echo "Started first requests...\n";
    var_dump(array_map('strlen', yield [
        'Content-Length of github.com' => curl_init_with('https://github.com/mpyw'),
        'Content-Length of twitter.com' => curl_init_with('https://twitter.com/mpyw'),
    ]));
    echo "Started second requests...\n";
    var_dump(array_map('strlen', yield [
        'Content-Length of example.com' => curl_init_with('http://example.com'),
    ]));
    $stop = true;
}
