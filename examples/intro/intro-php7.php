<?php

require __DIR__ . '/../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

function curl_init_with($url, array $options = [CURLOPT_RETURNTRANSFER => true]) {
    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    return $ch;
}
function get_xpath_async($url) {
    $dom = new \DOMDocument;
    @$dom->loadHTML(yield curl_init_with($url));
    return new \DOMXPath($dom);
}

var_dump(Co::wait([
    "google.com HTML" => curl_init_with("https://google.com"),
    "Content-Length of github.com" => function () {
        return strlen(yield curl_init_with("https://github.com"));
    },
    "Save mpyw's Gravatar Image URL to local" => function () {
        yield curl_init_with(
            (yield get_xpath_async('https://github.com/mpyw'))
                ->evaluate('string(//img[contains(@class,"avatar")]/@src)'),
            [CURLOPT_FILE => fopen('/tmp/mpyw.png', 'w')]
        );
        return "Saved as /tmp/mpyw.png";
    },
]));
