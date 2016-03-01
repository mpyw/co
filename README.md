# Co [![Build Status](https://travis-ci.org/mpyw/co.svg?branch=master)](https://travis-ci.org/mpyw/co) [![Coverage Status](https://coveralls.io/repos/github/mpyw/co/badge.svg?branch=master)](https://coveralls.io/github/mpyw/co?branch=master)

Asynchronous cURL executor simply based on resource and Generator

| PHP | :question: | Feature Restriction |
|:---:|:---:|:---:|
| 7.0~ | :smile: | Full Support |
| 5.5~5.6 | :grinning: | Generator is not so cool |
| 5.3~5.4 | :cold_sweat: | No Generator |
| ~5.2 | :boom: | Incompatible at all |

```php
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
```

The requests are executed as parallelly as possible :smile:

## Installing

Install via Composer.

```sh
# I only need the library!
composer require mpyw/co:@dev

# I need both the library and utils for testing.
# composer require mpyw/co:@dev
# composer install
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
| `throw` | **`true`** | Whether to throw `CURLException` on cURL errors. |
| `pipeline` | **`false`** | Whether to use HTTP/1.1 pipelining.<br />PHP 5.5+, libcurl 7.16.0+ are required. |
| `multiplex` | **`true`** | Whether to use HTTP/2 multiplexing.<br />PHP 5.5+ `--with-nghttp2`, libcurl 7.43.0+ are required. |
| `interval` | **`0.5`** | `curl_multi_select()` timeout seconds.<br />All events are observed in this span. |
| `concurrency` | **`6`** | cURL execution pool size.<br />Larger value will be recommended if you use pipelining or multiplexing. |

#### Return Value

**`(mixed)`**<br />Resolved values; it may contain `CURLException` within Exception-safe mode.

#### Exception

- Throws `CURLException` on Exception-unsafe mode.

### Co::async()

Execute cURL requests along with `Co::wait()` call, **without waiting** resolved values.  
The options are inherited from `Co::wait()`.  
<ins>This method is mainly expected to be used in <code>CURLOPT_WRITEFUNCTION</code> callback.</ins>

```php
static Co::async(mixed $value) : mixed
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallelly resolved.

#### Return Value

**`(null)`**

#### Exception

- Throws `CURLException` within Exception-unsafe mode.

### Co::setDefaultOptions()<br />Co::getDefaultOptions()

Overrides/gets static default settings.

```php
static Co::setDefaultOptions(array<string, mixed>) : null
static Co::getDefaultOptions() : array<string, mixed>
```

## Rules

### Conversion on resolving

The all values are resolved by the following rules.  
Yielded values are also sent to the Generator.  
The rules will be applied recursively.

| Before | After |
|:---:|:----:|
|cURL resource|`curl_multi_getconent()` result or `CURLException`|
|`Traversable`<br />(Excluding Generator) | Array |
|Function | Return value |
|Generator | Return value (After all yields done) |

### Exception-safe or Exception-unsafe priority

The following `yield` statements can specify Exception-safe or Exception-unsafe.

```php
yield Co::SAFE => $value
yield Co::UNSAFE => $value
```

Option priority:

1. `yield` in current scope
2. `yield` in parent scope
3. `throw` in `Co::wait()` options
4. `throw` in static default options

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

## Todos

- Tests
- Fix bugs
