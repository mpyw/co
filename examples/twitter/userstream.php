<?php

use mpyw\Co\Co;

require __DIR__ . '/../../vendor/autoload.php';
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8', true, 400);
    echo 'This script is only for php-cli.';
    exit(1);
}
set_time_limit(0);

// What kind of words do you want to favorite(like)?
fwrite(STDERR, 'FAVORITE_REGEX: ');
define('FAVORITE_REGEX', '/' . trim(fgets(STDIN)) . '/');

// Listen Twitter UserStreaming on 2 different accounts
Co::wait(array_map(function ($to) {
    // You have to install (Go)mpyw/twhelp-go or (PHP)mpyw/twhelp.
    // - Go: https://github.com/mpyw/twhelp-go
    // - PHP: https://github.com/mpyw/twhelp
    exec('twhelp --twist --app=google --xauth', $r, $status);
    if ($status !== 0) {
        exit(1);
    }
    eval(implode($r));
    return $to->curlStreaming('user', function ($status) use ($to) {
        if (isset($status->text)) {
            if (preg_match(FAVORITE_REGEX, htmlspecialchars_decode($status->text, ENT_NOQUOTES))) {
                Co::async($to->curlPost('favorites/create', array(
                    'id' => $status->id_str,
                )));
            }
        }
    });
}, array_fill(0, 2, null)));
