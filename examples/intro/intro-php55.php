<?php

require __DIR__ . '/../../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

function curl_init_with($url, array $options = [])
{
    $ch = curl_init();
    $options = array_replace([
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
    ], $options);
    curl_setopt_array($ch, $options);
    return $ch;
}

function get_xpath_async($url)
{
    $dom = new \DOMDocument;
    @$dom->loadHTML(yield curl_init_with($url));
    yield Co::RETURN_WITH => new \DOMXPath($dom);
}

var_dump(Co::wait([

    'Delay 5 secs' => function () {
        echo "[Delay] I start to have a pseudo-sleep in this coroutine for about 5 secs\n";
        for ($i = 0; $i < 5; ++$i) {
            yield Co::DELAY => 1;
            if ($i < 4) {
                printf("[Delay] %s\n", str_repeat('.', $i + 1));
            }
        }
        echo "[Delay] Done!\n";
    },

    "google.com HTML" => curl_init_with("https://google.com"),

    "Content-Length of github.com" => function () {
        echo "[GitHub] I start to request for github.com to calculate Content-Length\n";
        $content = (yield curl_init_with("https://github.com"));
        echo "[GitHub] Done! Now I calculate length of contents\n";
        yield Co::RETURN_WITH => strlen($content);
    },

    "Save mpyw's Gravatar Image URL to local" => function () {
        echo "[Gravatar] I start to request for github.com to get Gravatar URL\n";
        $xpath = (yield get_xpath_async('https://github.com/mpyw'));
        $src = $xpath->evaluate('string(//img[contains(@class,"avatar")]/@src)');
        echo "[Gravatar] Done! Now I download its data\n";
        yield curl_init_with($src, [CURLOPT_FILE => fopen('/tmp/mpyw.png', 'wb')]);
        echo "[Gravatar] Done! Saved as /tmp/mpyw.png\n";
    }

]));
