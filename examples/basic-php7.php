<?php

use mpyw\Co\Co;
use mpyw\Co\CURLException;

require __DIR__ . '/client_init.php';

// Todo
trigger_error('Currenly this script is broken', E_USER_NOTICE);

// Wait 9 sec
$result = Co::wait([curl('/rest', ['id' => 1, 'sleep' => 7]), function () {
    // Wait 4 sec
    print_r(yield [
        curl('/rest', ['id' => 2, 'sleep' => 3]),
        curl('/rest', ['id' => 3, 'sleep' => 4]),
    ]);
    // Wait 3 sec
    print_r(yield [
        function () {
            // Wait 1 sec
            echo yield curl('/rest', ['id' => 4, 'sleep' => 1]), "\n";
            return curl('/rest', ['id' => 5, 'sleep' => 2]);
        },
        function () {
            // Wait 0 sec
            echo (yield CO::SAFE => curl_init('invaild'))->getMessage(), "\n";
            try {
                // Wait 0 sec
                yield curl_init('invalid');
            } catch (CURLException $e) {
                echo $e->getMessage(), "\n";
            }
            return ['x' => ['y' => function () {
                return curl('/rest', ['id' => 6, 'sleep' => 2]);
            }]];
        }
    ]);
    return curl('/rest', ['id' => 7, 'sleep' => 1]);
}]);
print_r($result);
