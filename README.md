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
static Co::wait(mixed $value, bool $throw = null, float $interval = null, int $concurrency = null) : mixed
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallelly resolved.
- **`(bool)`** __*$throw*__<br /> Whether throw `CURLException` on cURL errors. **Default is `true`.**
- **`(float)`** __*$interval*__<br /> `curl_multi_select()` timeout. **Default is `0.5`.**
- **`(int)`** __*$concurrency*__<br /> cURL execution pool size. **Default is `6`.**

#### Return Values

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

#### Return Values

**`(null)`**

#### Exception

- Throws `CURLException` within Exception-unsafe mode.

### Co::setDefaultThrow()<br />Co::getDefaultThrow()<br />Co::setDefaultInterval()<br />Co::getDefaultInterval()<br />Co::setDefaultConcurrency()<br />Co::getDefaultConcurrency()

Overrides/gets static default settings.

```php
static Co::setDefaultThrow(bool $throw) : null
static Co::getDefaultThrow() : bool
static Co::setDefaultInterval(float $interval) : null
static Co::getDefaultInterval() : float
static Co::setDefaultConcurrency(int $concurrency) : null
static Co::getDefaultConcurrency() : int
```

#### Arguments

- **`(bool)`** __*$throw*__
- **`(float)`** __*$interval*__
- **`(int)`** __*$concurrency*__

#### Return Value

Omitted

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
yield CO::SAFE => $value
yield CO::UNSAFE => $value
```

Priority:

1. Current scope `yield` option
2. Parent scope `yield` option
3. 2nd argument of `Co::wait()`
4. Static default

### Comparison with Generators of PHP7.0+ or PHP5.5~5.6

#### `return` Statements

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

#### `yield` Statements with assignment

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
- Fix broken examples
