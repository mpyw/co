# Co [![Build Status](https://travis-ci.org/mpyw/co.svg?branch=master)](https://travis-ci.org/mpyw/co) [![Coverage Status](https://coveralls.io/repos/github/mpyw/co/badge.svg?branch=master)](https://coveralls.io/github/mpyw/co?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mpyw/co/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mpyw/co/?branch=master)

Asynchronous cURL executor simply based on resource and Generator

| PHP | :question: | Feature Restriction |
|:---:|:---:|:---:|
| 7.0~ | :smile: | Full Support |
| 5.5~5.6 | :anguished: | Generator is not so cool |
| ~5.4 | :boom: | Incompatible |

```php
function curl_init_with($url, array $options = [CURLOPT_RETURNTRANSFER => true])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    return $ch;
}
function get_xpath_async($url)
{
    $dom = new \DOMDocument;
    @$dom->loadHTML(yield curl_init_with($url));
    return new \DOMXPath($dom);
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
        $content = yield curl_init_with("https://github.com");
        echo "[GitHub] Done! Now I calculate length of contents\n";
        return strlen($content);
    },

    "Save mpyw's Gravatar Image URL to local" => function () {
        echo "[Gravatar] I start to request for github.com to get Gravatar URL\n";
        $src = (yield get_xpath_async('https://github.com/mpyw'))
                 ->evaluate('string(//img[contains(@class,"avatar")]/@src)');
        echo "[Gravatar] Done! Now I download its data\n";
        yield curl_init_with($src, [CURLOPT_FILE => fopen('/tmp/mpyw.png', 'w')]);
        echo "[Gravatar] Done! Saved as /tmp/mpyw.png\n";
    }

]));
```

The requests are executed as parallelly as possible :smile:  
Note that there is only **1 process** and **1 thread**.

```Text
[Delay] I start to have a pseudo-sleep in this coroutine for about 5 secs
[GitHub] I start to request for github.com to calculate Content-Length
[Gravatar] I start to request for github.com to get Gravatar URL
[Delay] .
[Delay] ..
[GitHub] Done! Now I calculate length of contents
[Gravatar] Done! Now I download its data
[Delay] ...
[Gravatar] Done! Saved as /tmp/mpyw.png
[Delay] ....
[Delay] Done!
array(4) {
  ["Delay 5 secs"]=>
  NULL
  ["google.com HTML"]=>
  string(262) "<HTML><HEAD><meta http-equiv="content-type" content="text/html;charset=utf-8">
<TITLE>302 Moved</TITLE></HEAD><BODY>
<H1>302 Moved</H1>
The document has moved
<A HREF="https://www.google.co.jp/?gfe_rd=cr&amp;ei=XXXXXX">here</A>.
</BODY></HTML>
"
  ["Content-Length of github.com"]=>
  int(25534)
  ["Save mpyw's Gravatar Image URL to local"]=>
  NULL
}
```

## Installing

Install via Composer.

```sh
composer require mpyw/co:^1.0
```

And require Composer autoloader in your scripts.

```php
require 'vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;
```

## API

### Co::wait()

Wait for all the cURL requests to complete.  
The options will override static defaults.

```php
static Co::wait(mixed $value, array $options = array()) : mixed
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallelly resolved.
- **`(array<string, mixed>)`** __*$options*__<br /> Associative array of options.

| Key | Default | Description |
|:---:|:---:|:---|
| `throw` | **`true`** | Whether to throw or capture `CURLException` on cURL errors.<br />Whether to propagate or capture `RuntimeException` thrown in Generator.|
| `pipeline` | **`false`** | Whether to use HTTP/1.1 pipelining.<br />libcurl 7.16.0+ are required. |
| `multiplex` | **`true`** | Whether to use HTTP/2 multiplexing.<br />PHP build configuration `--with-nghttp2`, libcurl 7.43.0+ are required. |
| `interval` | **`0.002`** | `curl_multi_select()` timeout seconds. `0` means real-time observation.|
| `concurrency` | **`6`** | cURL execution pool size. `0` means unlimited.<br />The value should be within `10` at most.|

#### Return Value

**`(mixed)`**<br />Resolved values; within Exception-safe mode, it may contain...

- `CURLException` which has been raised internally.
- `RuntimeException` which has been raised by user.

#### Exception

- Throws `CURLException` or `RuntimeException` on Exception-unsafe mode.

### Co::async()

Execute cURL requests along with `Co::wait()` call, **without waiting** resolved values.  
The options are inherited from `Co::wait()`.  
<ins>This method is mainly expected to be used in <code>CURLOPT_WRITEFUNCTION</code> callback.</ins>

```php
static Co::async(mixed $value) : null
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallelly resolved.

#### Return Value

**`(null)`**

#### Exception

- Throws `CURLException` or `RuntimeException` on Exception-unsafe mode.

### Co::setDefaultOptions()<br />Co::getDefaultOptions()

Overrides/gets static default settings.

```php
static Co::setDefaultOptions(array<string, mixed>) : null
static Co::getDefaultOptions() : array<string, mixed>
```

## Rules

### Conversion on resolving

The all yielded/returned values are resolved by the following rules.  
Yielded values are also resent to the Generator.  
The rules will be applied recursively.

| Before | After |
|:---:|:----:|
|cURL resource|`curl_multi_getconent()` result or `CURLException`|
|`Traversable`<br />(Excluding Generator) | Array or `RuntimeException`|
|Function | Return value or `RuntimeException`|
|Generator | Return value (After all yields done) or `RuntimeException`|

### Exception-safe or Exception-unsafe priority

The following `yield` statements can specify Exception-safe or Exception-unsafe:

```php
yield Co::SAFE => $value
yield Co::UNSAFE => $value
```

Option priority:

1. `yield` in current scope
2. `yield` in parent scope
3. `throw` in `Co::wait()` options
4. `throw` in static default options

### Pseudo-sleep for each coroutine

The following `yield` statements delay the coroutine processing:

```php
yield Co::DELAY => $seconds
yield Co::SLEEP => $seconds  # Alias
```

### Comparison with Generators of PHP7.0+ or PHP5.5~5.6

#### `return` statements

PHP7.0+:

```php
yield $foo;
yield $bar;
return $baz;
```

PHP5.5~5.6:

```php
yield $foo;
yield $bar;
yield Co::RETURN_WITH => $baz;
```

Although experimental aliases `Co::RETURN_` `Co::RET` `Co::RTN` are provided,  
**`Co::RETURN_WITH`** is recommended in terms of readability.

#### `yield` statements with assignment

PHP7.0+:

```php
$a = yield $foo;
echo yield $bar;
```

PHP5.5~5.6:

```php
$a = (yield $foo);
echo (yield $bar);
```

### Optimizing concurrency by grouping same destination

Note that HTTP/1.1 pipelining or HTTP/2 multiplexing actually uses only **1 TCP connection** for **the same destination**.  
You don't have to increase `concurrency` if the number of destination hosts is low.  

However, Co cannot read `CURLOPT_URL`. This is the limitation from PHP implemention.  
To express that some cURL handles' destination are the same, set unique identifier using **`CURLOPT_PRIVATE`** each.

```php
$urls = [
    'mpyw/co' => 'https://github.com/mpyw/co',
    'mpyw/TwistOAuth' => 'https://github.com/mpyw/TwistOAuth',
    '@mpyw' => 'https://twitter.com/mpyw',
    '@twitter' => 'https://twitter.com/twitter',
    '@TwitterJP' => 'https://twitter.com/TwitterJP',
];

$requests = [];
$hosts = [];
foreach ($urls as $title => $url) {
    $ch = curl_init();
    $host = parse_url($url, PHP_URL_HOST);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_PRIVATE => $host,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'gzip',
    ]);
    $requests[$title] = $ch;
    $hosts[$host] = true;
}

$responses = Co::wait($requests, [
    'pipeline' => true,
    'concurrency' => count($hosts),
]);
```

## FAQ

### How can I yield/return without resolving?

Currently there are no options for suppressing resolution.  
In order to achieve it, you can simply wrap raw values in objects.

```php
Co::wait(function () {
    $obj = yield (object)['curl' => curl_init()];
    $curl = $obj['curl'];
    assert(is_resource($curl)); // It is still cURL handle.
});
```

Do you **REALLY** need new features such as `Co::RETURN_RAW`?  
[Create new issue!](https://github.com/mpyw/co/issues)
