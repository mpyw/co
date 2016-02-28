# Co

Simple parallel cURL functions with PHP5.5+ Generator.

| PHP | :question: | Feature Restriction |
|:---:|:---:|:---:|
| 7.0~ | :smile: | Full Support |
| 5.5~5.6 | :grinning: | Generator is not so cool |
| 5.3~5.4 | :cold_sweat: | No Generator |
| ~5.2 | :boom: | Incompatible at all |


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
wait(mixed $value, int $concurrency = null, bool $throw = null) : mixed
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
This method is mainly expected to be used in `CURLOPT_WRITEFUNCTION` callback.

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

The following conversion are applied recursively.

| Before | After |
|:---:|:----:|
|cURL resource|`curl_multi_getconent()` result or `CURLException`|
|`Traversable`<br />(Excluding Generator) | Array |
|Function | Return value |
|Generator | Return value (After all yields done) |

### Exception-safe or Exception-unsafe priority

1. `yield CO::UNSAFE => $var` or `yield CO::SAFE => $var`
2. 3rd argument of `Co::wait()`\
3. Static default

### Comparison Generator of PHP7.0+ or PHP5.5~5.6

#### `return` Statements

PHP7.0+:

```
yield $foo;
yield $bar;
return $baz;
```

PHP5.5~5.6:

```
yield $foo;
yield $bar;
yield Co::RETURN_WITH => $baz;
```

#### `yield` Statements with assignment

PHP7.0+:

```
$a = yield $foo;
echo yield $bar;
```

PHP5.5~5.6:

```
$a = (yield $foo);
echo (yield $bar);
```

## Todos

- Add PHPUnit tests
- Fix broken examples
