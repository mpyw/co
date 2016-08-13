<?php

use mpyw\Co\Co;
use mpyw\Co\CURLException;

require __DIR__ . '/client-init.php';

// Wait 5 sec
print_time();
$result = Co::wait([curl('/rest', ['id' => 1, 'sleep' => 5]), function () {
    // Wait 3 sec
    print_r(unwrap(yield Co::SAFE => [
        curl('/rest', ['id' => 2, 'sleep' => 3]),
        function () {
            yield;
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
            echo unwrap(yield Co::SAFE => function () {
                yield;
                throw new \RuntimeException('02');
            }) . "\n";
            yield function () {
                yield;
                throw new \RuntimeException('03');
            };
            return 'Unreachable';
        },
        function () {
            echo unwrap(yield Co::SAFE => function () {
                yield;
                throw new \RuntimeException('04');
            }) . "\n";
            yield function () {
                yield;
                throw new \RuntimeException('05');
            };
            return 'Unreachable';
        },
    ]));
    echo unwrap(yield Co::SAFE => function () {
        yield curl('/invalid');
        return 'Unreachable';
    }) . "\n";
    // Wait 1 sec
    return curl('/rest', ['id' => 4, 'sleep' => 1]);
}]);
print_r($result);
print_time();
