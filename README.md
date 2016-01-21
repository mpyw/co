# simple-parallel-curl

Simple parallel cURL functions with PHP5.5+ Generator

## API

All functions are defined **globally**, if not exists.

```php
function curl_get_init($url, array $options = [])
function curl_post_init($url, array $postfields = [], array $options = [])
function curl_parallel_exec(array $curls, $timeout)
function curl_parallel_exec_generator(array $generators, $timeout)
```
