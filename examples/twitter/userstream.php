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
while (true) {
    fwrite(STDERR, 'FAVORITE_REGEX: ');
    $pattern = '/' . trim(fgets(STDIN)) . '/';
    if (@preg_match($pattern, '') !== false) {
        define('FAVORITE_REGEX', $pattern);
        break;
    }
    fwrite(STDERR, "Bad pattern.\n");
    if (feof(STDIN)) {
        exit;
    }
}

// How many accounts do you want to use?
while (true) {
    fwrite(STDERR, 'NUMBER_OF_ACCOUNTS: ');
    $number = (int)trim(fgets(STDIN));
    if ($number > 0) {
        define('NUMBER_OF_ACCOUNTS', $number);
        break;
    }
    fwrite(STDERR, "Bad number.\n");
    if (feof(STDIN)) {
        exit;
    }
}

// Listen Twitter UserStreaming on 2 different accounts
Co::wait(array_map(function ($i) {
    // You have to install (Go)mpyw/twhelp-go or (PHP)mpyw/twhelp.
    // - Go: https://github.com/mpyw/twhelp-go
    // - PHP: https://github.com/mpyw/twhelp
    exec('twhelp --twist --app=google --xauth', $r, $status);
    if ($status !== 0) {
        exit(1);
    }
    eval(implode($r));
    return $to->curlStreaming('user', function ($status) use ($to, $i) {
        if (!isset($status->text)) {
            return;
        }
        echo "Account[$i]: new tweet\n";
        if (!preg_match(FAVORITE_REGEX, htmlspecialchars_decode($status->text, ENT_NOQUOTES))) {
            return;
        }
        echo "Account[$i]: matched\n";
        Co::async(function () use ($to, $i, $status) {
            echo "Account[$i]: sending favorite request: $status->id_str\n";
            yield $to->curlPost('favorites/create', [
                'id' => $status->id_str,
            ]);
            echo "Account[$i]: favorited: $status->id_str\n";
        });
    });
}, range(0, NUMBER_OF_ACCOUNTS - 1)));
