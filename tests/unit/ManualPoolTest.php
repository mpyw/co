<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\Pool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;
use React\Promise\Deferred;

/**
 * @requires PHP 7.0.7
 */
class ManualPoolTest extends \Codeception\TestCase\Test {
    use \Codeception\Specify;
    private static $pool;

    public function _before()
    {
        test::double('mpyw\Co\Internal\TypeUtils', ['isCurl' => function ($arg) {
            return $arg instanceof \DummyCurl;
        }]);
    }

    public function _after()
    {
        test::clean();
    }

    public function testWait()
    {
        $pool = new Pool(new CoOption(['concurrency' => 3]));
        $a = new Deferred;
        $curls = [
            new DummyCurl('E', 5),        // 0===1===2===3===4===5                  (0, 5)
            new DummyCurl('A', 1),        // 0===1                                  (0, 1)
            new DummyCurl('D', 4),        // 0===1===2===3===4                      (0, 4)
            new DummyCurl('C', 3),        // 0---1---2===3===4===5                  (2, 5)
            new DummyCurl('B', 2),        // 0---1---2---3---4---5===6===7          (5, 7)
        ];
        $invalids = [
            new DummyCurl('X', 3, true),  // 0---1---2---3---4---5---6===7===8===9  (6, 9)
            new DummyCurl('Y', 2, true),  // 0---1---2---3---4---5---6===7===8      (6, 8)
        ];
        $curl_timings = [[0, 5], [0, 1], [0, 4], [2, 5], [5, 7]];
        $invalid_timings = [[6, 9], [6, 8]];
        $done = [];
        $failed = [];
        foreach ($curls as $ch) {
            $pool->addCurl($ch)->then(
                function ($result) use (&$done) {
                    $done[] = $result;
                },
                function () {
                    $this->assertTrue(false);
                }
            );
        }
        foreach ($invalids as $ch) {
            $pool->addCurl($ch)->then(
                function () {
                    $this->assertTrue(false);
                },
                function ($e) use (&$failed) {
                    $failed[] = $e;
                }
            );
        }
        $pool->wait();
        $failed = array_map(function (\RuntimeException $e) {
            $e->getHandle(); // Just for coverage
            return $e->getMessage();
        }, $failed);
        foreach ($curls as $i => $curl) {
            $str = str_replace('DummyCurl', 'Response', (string)$curl);
            $this->assertContains($str, $done);
            $this->assertEquals($curl_timings[$i], [$curl->startedAt(), $curl->stoppedAt()]);
        }
        foreach ($invalids as $i => $curl) {
            $str = str_replace('DummyCurl', 'Error', (string)$curl);
            $this->assertContains($str, $failed);
            $this->assertEquals($invalid_timings[$i], [$curl->startedAt(), $curl->stoppedAt()]);
        }
    }

    public function testWaitCurlAndDelay()
    {
        $pool = new Pool(new CoOption(['concurrency' => 3]));
        $pool->addCurl(new DummyCurl('A', 10))->then(function () use (&$x) {
            $x = microtime(true);
        });
        $pool->addDelay(1.3)->then(function () use (&$y) {
            $y = microtime(true);
        });
        $pool->addDelay(1.1)->then(function () use (&$z) {
            $z = microtime(true);
        });
        $pool->addDelay(1.2)->then(function () use (&$w) {
            $w = microtime(true);
        });
        $pool->wait();
        $this->assertNotNull($x);
        $this->assertNotNull($y);
        $this->assertNotNull($z);
        $this->assertNotNull($w);
        $this->assertTrue($x < $z);
        $this->assertTrue($z < $w);
        $this->assertTrue($w < $y);
    }

    public function testUnlimitedConcurrency()
    {
        $pool = new Pool(new CoOption(['concurrency' => 0]));
        $curls = [];
        foreach (range(1, 100) as $i) {
            $curls[] = new DummyCurl($i, 2);
        }
        $done = [];
        foreach ($curls as $ch) {
            $pool->addCurl($ch)->then(
                function ($result) use (&$done) {
                    $done[] = $result;
                },
                function () {
                    $this->assertTrue(false);
                }
            );;
        }
        $pool->wait();
        foreach ($curls as $curl) {
            $str = str_replace('DummyCurl', 'Response', (string)$curl);
            $this->assertContains($str, $done);
            $this->assertEquals([0, 2], [$curl->startedAt(), $curl->stoppedAt()]);
        }
    }
}
