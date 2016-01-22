# simple-parallel-curl

Simple parallel cURL functions with PHP5.5+ Generator

## Installing

```
composer require mpyw/simple-parallel-curl:@dev
```

## API

All functions are defined **globally**, if not exists.

### curl_get_init()<br />curl_post_init()

```php
function curl_get_init($url, array $options = [])
function curl_post_init($url, array $postfields = [], array $options = [])
```

Simple wrappers for `curl_init()`. Some default values are defined.

#### Arguments

- **`(string)`** __*$url*__<br /> Destination URL for `curl_init()`.
- **`(array<string, string|cURLFile>)`** __*$postfields*__<br /> Postfields. Multipart format is used when detected `cURLFile` instance.
- **`(array<CURLOPT_*, mixed>)`** __*$options*__<br /> cURL options for `curl_setopt_array()`.

#### Return Value

**`(resource)`**<br /> cURL resource.

#### Note

```php
// Default values for GET
$default = [
    CURLOPT_RETURNTRANSFER => true, // return string instead of flushing into STDOUT
    CURLOPT_ENCODING => 'gzip', // compress connection
    CURLOPT_FOLLOWLOCATION => (string)ini_get('open_basedir') === '', // normally true
];

// Default values for POST
$default = [
    CURLOPT_RETURNTRANSFER => true, // return string instead of flushing into STDOUT
    CURLOPT_ENCODING => 'gzip', // compress connection
    CURLOPT_FOLLOWLOCATION => (string)ini_get('open_basedir') === '', // normally true
    CURLOPT_POST => true, // enable POST request
    CURLOPT_SAFE_UPLOAD => true, // disable legacy filename annotation support for PHP5.4-
];
```

### curl_parallel_exec()

```php
function curl_parallel_exec(array $curls, $timeout = 0.0)
```

Await all cURL resources and return results.

#### Arguments

- **`(array<mixed, resource>)`** __*$curls*__<br /> An array of cURL resources. Keys are preserved for returning results.
- **`(float)`** __*$timeout*__<br /> Zero means infinite.

#### Return Value

**`(array<mixed, string|RuntimeException>)`**<br /> An array of content string or `RuntimeException`

### curl_parallel_exec_generator()

```php
function curl_parallel_exec_generator(array $generators, $timeout = 0.0)
```

Await all yielded cURL resources.

- All events are observed in a static event loop. You can call recursively.
- Any values are yieldables. This function replace each cURL resource into content string recursively.

#### Arguments

- **`(array<mixed, Generator|Function<Generator>)`** __*$curls*__<br /> An array of Generator or Generator function. Keys are preserved for returning results.
- **`(float)`** __*$timeout*__<br /> Zero means infinite.

#### Return Value

**`(array<mixed, string|RuntimeException>)`**<br /> An array of content string or `RuntimeException`

#### Note

Yielded values are converted like...

From:

```php
$x = (yield curl_get_init('http://example.com/A'));
$y = (yield [
    'foo' => curl_get_init('http://example.com/B'),
    'bar' => [
        'baz' => curl_get_init('http://example.com/C'),
    ],
]);
```

To:

```php
$x = 'Content string of http://example.com/A';
$y = [
    'foo' => 'Content string of http://example.com/B',
    'bar' => [
        'baz' => 'Content string of http://example.com/C',
    ],
];
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
