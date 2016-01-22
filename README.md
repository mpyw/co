# simple-parallel-curl

Simple parallel cURL functions with PHP5.5+ Generator

## Installing

```
composer require mpyw/simple-parallel-curl:@dev
```

## API

All functions are defined **globally**, if not exists.

```php
function curl_get_init($url, array $options = []) : resource<curl>
function curl_post_init($url, array $postfields = [], array $options = []) : resource<curl>
function curl_parallel_exec(array $curls, $timeout) : array<string>
function curl_parallel_exec_generator(array $generators, $timeout) : null
```

## Example

```php
curl_parallel_exec_generator([
    function () {
        $a = (yield curl_get_init('http://example.com/a'));
        echo "Request for A done.\n";
        list($b1, $b2) = (yield [
            curl_get_init('http://example.com/b1'),
            curl_get_init('http://example.com/b2'),
        ]);
        echo "Request for B1, B2 done.\n";
    },
    function () {
        $c = (yield curl_get_init('http://example.com/c'));
        echo "Request for C done.\n";
        curl_parallel_exec_generator([
            function () {
                $d1 = (yield curl_get_init('http://example.com/d1'));
                echo "Request for D1 done.\n";
            },
            function () {
                $d2 = (yield curl_get_init('http://example.com/d2'));
                echo "Request for D2 done.\n";
            },
        ], 10);
    },
    function () {
        $e = (yield curl_get_init('http://example.com/e'));
        echo "Request for E done.\n";
        try {
            $f = (yield curl_get_init('http://invalid-url.com/', [
                CURLOPT_FAILONERROR => true,
            ]));
        } catch (\RuntimeException $ex) {
            echo "Request for F Failed ({$ex->getMessage()}).\n";
        }
    }
], 10);
```
