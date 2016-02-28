# Co

Asynchronus cURL executor simply based on resource and Generator.

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

Those requests are executed as asynchronus as it can :smile:

## Installing

```
composer require mpyw/co:@dev
```

```php
require 'vendor/autoload.php';

use mpyw\Co\Co;
use mpyw\Co\CURLException;
```

## API

### Co::wait()

Wait all cURL requests to be completed.  
Options override static defaults.

```php
static wait(mixed $value, int $concurrency = null, bool $throw = null) : mixed
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallely resolved.
- **`(int)`** __*$concurrency*__<br /> cURL execution pool size. Default is `6`.
- **`(bool)`** __*$throw*__<br /> Whether throw `CURLException` on cURL errors. Default is `true`.

#### Return Values

**`(mixed)`**<br />Resolved values. It may contains `CURLException` on Exception-safe mode.

#### Exception

- Throws `CURLException` on Exception-unsafe mode.

### Co::async()

Parallel execution along with `Co::wait()`, **without waiting**.
Options are inherited from `Co::wait()`.  
<ins>This method is mainly expected to be used in <code>CURLOPT_WRITEFUNCTION</code> callback.</ins>

```php
static Co::async(mixed $value) : mixed
```

#### Arguments

- **`(mixed)`** __*$value*__<br /> Any values to be parallely resolved.

#### Return Values

**`(null)`**

#### Exception

- Throws `CURLException` on Exception-unsafe mode.

### Co::setDefaultConcurrency()<br />Co::getDefaultConcurrency()<br />Co::setDefaultThrow()<br />Co::getDefaultThrow()

Override or get static default settings.

```php
static Co::setDefaultConcurrency(int $concurrency) : null
static Co::getDefaultConcurrency() : int
static Co::setDefaultThrow(bool $throw) : null
static Co::getDefaultThrow() : bool
```

#### Arguments

- **`(int)`** __*$concurrency*__
- **`(bool)`** __*$throw*__

#### Return Value

Omitted

## Rules

### Conversion on resolving

All values are resolved by the following rules.  
Yielded values are also sent into the Generator.  
The rules are applied recursively.

| Before | After |
|:---:|:----:|
|cURL resource|`curl_multi_getconent()` result or `CURLException`|
|`Traversable`<br />(Excluding Generator) | Array |
|Function | Return value |
|Generator | Return value (After all yields done) |

### Exception-safe or Exception-unsafe priority

The following `yield` statements can specify Exception-safe or Exception-unsafe.

```
yield CO::SAFE => $value
yield CO::UNSAFE => $value
```

Priority:

1. Current scope `yield` option
2. Parent scope `yield` option
3. 3rd argument of `Co::wait()`
4. Static default

### Comparison Generator of PHP7.0+ or PHP5.5~5.6

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

- Add PHPUnit tests
- Fix bugs
- Fix broken examples
