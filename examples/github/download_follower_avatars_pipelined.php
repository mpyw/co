<?php

require __DIR__ . '/../../vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;

function curl_init_with(string $url, array $options = [])
{
    $ch = curl_init();
    $options = array_replace([
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_TIMEOUT => 5,
    ], $options);
    curl_setopt_array($ch, $options);
    return $ch;
}

function get_github_followers_async(string $username, int $page, &$has_more) : \Generator
{
    $dom = new DOMDocument;
    $html = yield curl_init_with("https://github.com/$username/followers?page=$page");
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    $sources = [];
    foreach ($xpath->query('//li[contains(@class, "follow-list-item")]//img') as $node) {
        $sources[substr($node->getAttribute('alt'), 1)] = $node->getAttribute('src');
    }
    $has_more = (bool)$xpath->evaluate('string(//div[contains(@class, "pagination")]/a[string() = "Next"])');
    $has_more_int = (int)$has_more;
    echo "Downloaded https://github.com/$username/followers?page=$page (has_more = $has_more_int)\n";
    return $sources;
}

function download_image_async(string $url, string $basename, string $savedir = '/tmp') : \Generator
{
    static $exts = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif',
    ];
    $content = yield curl_init_with($url);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->buffer($content);
    $ext = isset($exts[$type]) ? $exts[$type] : '';
    file_put_contents("$savedir/$basename$ext", $content);
    echo "Downloaded $url, saved as $savedir/$basename$ext\n";
}

// Who are you?
 while (true) {
    do {
        if (feof(STDIN)) {
            exit;
        }
        fwrite(STDERR, 'USERNAME: ');
        $username = preg_replace('/[^\w-]++/', '', trim(fgets(STDIN)));
    } while ($username === '');
    if (@file_get_contents("https://github.com/$username")) {
        define('USERNAME', $username);
        break;
    }
    fwrite(STDERR, error_get_last()['message'] . "\n");
}

Co::wait(function () {
    $page = 0;
    do {
        $sources = yield get_github_followers_async(USERNAME, ++$page, $has_more);
        Co::async(function () use ($sources) {
            $requests = [];
            foreach ($sources as $username => $src) {
                $requests[] = download_image_async($src, $username);
            }
            yield $requests;
        });
    } while ($has_more);
}, ['concurrency' => 0, 'pipeline' => true]);
