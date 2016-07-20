<?php

require __DIR__ . '/../../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

class TerminatedException extends \RuntimeException {}

Co::wait(function () {
    try {
        yield [timer($e), main()];
    } catch (TerminatedException $_) {
        $e = $_; // Since PHP Bug #72629, we need to assign caught exception to another variable.
        var_dump('Terminated.');
    }
});

function curl_init_with($url, array $options = [CURLOPT_RETURNTRANSFER => true])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    return $ch;
}

function timer(&$e)
{
    $ms = 0;
    while (true) {
        yield CO::DELAY => 0.2;
        if ($e) {
            return;
        }
        $ms += 200;
        echo "[Timer]: $ms miliseconds passed\n";
    }
}

function main()
{
    var_dump(array_map('strlen', yield [
        'Content-Length of github.com' => curl_init_with('https://github.com/mpyw'),
        'Content-Length of twitter.com' => curl_init_with('https://twitter.com/mpyw'),
    ]));
    throw new TerminatedException;
}
