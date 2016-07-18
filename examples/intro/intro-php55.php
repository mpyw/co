<?php

require __DIR__ . '/../../vendor/autoload.php';

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
    yield Co::RETURN_WITH => new \DOMXPath($dom);
}

var_dump(Co::wait([
    "google.com HTML" => curl_init_with("https://google.com"),
    "Content-Length of github.com" => function () {
        yield Co::RETURN_WITH => strlen(yield curl_init_with("https://github.com"));
    },
    "Save mpyw's Gravatar Image URL to local" => function () {
        $xpath = (yield get_xpath_async('https://github.com/mpyw'));
        yield curl_init_with(
            $xpath->evaluate('string(//img[contains(@class,"avatar")]/@src)'),
            [CURLOPT_FILE => fopen('/tmp/mpyw.png', 'w')]
        );
        yield Co::RETURN_WITH => "Saved as /tmp/mpyw.png";
    },
]));
