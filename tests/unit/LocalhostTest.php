<?php

use mpyw\Co\Co;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class LocalhostTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;
    private static $Co;

    private static function here($doc) {
        $doc = preg_replace('/\A(^ *+\n)++|([ \n]*+)++\z/m', '', $doc);
        preg_replace_callback('/^ *+/m', function ($m) use (&$min) {
            $len = strlen($m[0]);
            if ($min === null || $len < $min) $min = $len;
        }, $doc);
        return preg_replace("/^ {{$min}}/m", '', $doc);
    }
    private static function hereln($doc) {
        return here($doc) . "\n";
    }

    public function _before()
    {
        require_once __DIR__ . '/../../examples/localhost/client-init.php';
        $path = __DIR__ . '/../../examples/localhost/server.php';
        $this->pid = trim(system("nohup php $path 1>/dev/null 2>/dev/null & echo $!"));
        self::$Co = Proxy::get(Co::class);
    }

    public function _after()
    {
        system("kill $this->pid");
    }

    public function test01()
    {
        $expected = '
            【Time】0.0 s
            Array
            (
                [0] => Rest response #2 (sleep: 3 sec)

                [1] => Rest response #3 (sleep: 4 sec)

            )
            【Time】4.0 s
            mpyw\Co\CURLException: The requested URL returned error: 404 Not Found
            【Time】4.0 s
            mpyw\Co\CURLException: The requested URL returned error: 404 Not Found
            【Time】4.0 s
            Rest response #4 (sleep: 1 sec)

            【Time】5.0 s
            Array
            (
                [0] => Rest response #5 (sleep: 1 sec)

                [1] => Array
                    (
                        [x] => Array
                            (
                                [y] => Rest response #6 (sleep: 2 sec)

                            )

                    )

            )
            【Time】6.0 s
            Array
            (
                [0] => Rest response #1 (sleep: 7 sec)

                [1] => Rest response #7 (sleep: 1 sec)

            )
            【Time】7.0 s
        ';
        $actual = Co::wait([curl('/rest', ['id' => 1, 'sleep' => 7]), function () {
            // Wait 4 sec
            print_r(yield [
                curl('/rest', ['id' => 2, 'sleep' => 3]),
                curl('/rest', ['id' => 3, 'sleep' => 4]),
            ]);
            print_time();
            // Wait 2 sec
            print_r(yield [
                function () {
                    // Wait 1 sec
                    echo yield curl('/rest', ['id' => 4, 'sleep' => 1]), "\n";
                    print_time();
                    return curl('/rest', ['id' => 5, 'sleep' => 1]);
                },
                function () {
                    // Wait 0 sec
                    echo unwrap(yield CO::SAFE => curl('/invalid')), "\n";
                    print_time();
                    try {
                        // Wait 0 sec
                        yield curl('/invalid');
                    } catch (CURLException $e) {
                        echo unwrap($e), "\n";
                        print_time();
                    }
                    return ['x' => ['y' => function () {
                        return curl('/rest', ['id' => 6, 'sleep' => 2]);
                    }]];
                }
            ]);
            print_time();
            return curl('/rest', ['id' => 7, 'sleep' => 1]);
        }], ['interval' => 0]);
        $this->assertEquals(self::hereln($expected), $actual);
    }

}
