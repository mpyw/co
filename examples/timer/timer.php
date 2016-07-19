<?php

require __DIR__ . '/../../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

Co::wait([
    function () {
        for ($i = 0; $i < 8; ++$i) {
            yield CO::DELAY => 1.1;
            echo "[A] Timer: $i\n";
        }
    },
    function () {
        for ($i = 0; $i < 5; ++$i) {
            yield CO::DELAY => 1.7;
            echo "[B] Timer: $i\n";
        }
    }
]);
