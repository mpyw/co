<?php

use mpyw\Co\Co;
use mpyw\Co\CURLException;

require __DIR__ . '/client-init.php';

// Wait 5 sec
print_time();
$result = unwrap(Co::wait([curl('/rest', ['id' => 1, 'sleep' => 5]), function () {
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
            yield function () {
                throw new \RuntimeException('02');
            };
            throw new \LogicException('Unreachable here: A');
        },
        function () {
            var_dump(yield Co::SAFE => function () {
                throw new \RuntimeException('03');
            });
            return 'Reachable here: B';
        }
    ]));
    var_dump(yield Co::SAFE => function () {
        yield Co::UNSAFE => curl('/invalid');
        throw new \LogicException('Unreachable here: C');
    });
    // Wait 1 sec
    return curl('/rest', ['id' => 4, 'sleep' => 1]);
}], ['interval' => 0]));
print_r($result);
print_time();
