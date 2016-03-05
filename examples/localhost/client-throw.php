<?php

use mpyw\Co\Co;
use mpyw\Co\CURLException;

require __DIR__ . '/client-init.php';

// Wait 4 sec
print_time();
$result = unwrap(Co::wait([curl('/rest', ['id' => 1, 'sleep' => 2]), function () {
    // Wait 3 sec
    print_r(unwrap(yield Co::SAFE => [
        curl('/rest', ['id' => 2, 'sleep' => 3]),
        function () {
            throw new \RuntimeException('01');
        }
    ]));
    print_time();
    // Wait 1 sec
    print_r(unwrap(yield Co::SAFE => [
        function () {
            // Wait 1 sec
            echo yield curl('/rest', ['id' => 3, 'sleep' => 1]), "\n";
            print_time();
            throw new \RuntimeException('02');
        },
        function () {
            yield function () {
                throw new \RuntimeException('03');
            };
        }
    ]));
    print_time();
}], ['interval' => 0.05]));
print_r($result);
print_time();
