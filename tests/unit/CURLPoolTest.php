<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\CURLPool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;
use mpyw\RuntimePromise\Deferred;

/**
 * @requires PHP 7.0
 */
class CURLPoolTest extends \Codeception\TestCase\Test {
    use \Codeception\Specify;
    private static $pool;

    public function _before()
    {
        test::double('mpyw\Co\Internal\Utils', ['isCurl' => function ($arg) {
            return $arg instanceof \DummyCurl;
        }]);
    }

    public function _after()
    {
        test::clean();
    }

    public function testWait()
    {
        $pool = new CURLPool(new CoOption(['concurrency' => 3]));
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
            $dfd = new Deferred();
            $dfd->promise()->then(
                function ($result) use (&$done) {
                    $done[] = $result;
                },
                function () {
                    $this->assertTrue(false);
                }
            );
            $pool->addOrEnqueue($ch, $dfd);
        }
        foreach ($invalids as $ch) {
            $dfd = new Deferred();
            $dfd->promise()->then(
                function () {
                    $this->assertTrue(false);
                },
                function ($e) use (&$failed) {
                    $failed[] = $e;
                }
            );
            $pool->addOrEnqueue($ch, $dfd);
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

    public function testDuplicatedAdd()
    {
        $pool = new CURLPool(new CoOption(['concurrency' => 4]));
        $a = new Deferred;
        $curls = [
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ];
        $this->setExpectedException(\InvalidArgumentException::class, 'The cURL handle is already enqueued: DummyCurl[C]');
        foreach ($curls as $ch) {
            $pool->addOrEnqueue($ch);
        }
    }

    public function testDuplicatedEnqueue()
    {
        $pool = new CURLPool(new CoOption(['concurrency' => 2]));
        $a = new Deferred;
        $curls = [
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ];
        $this->setExpectedException(\InvalidArgumentException::class, 'The cURL handle is already enqueued: DummyCurl[C]');
        foreach ($curls as $ch) {
            $pool->addOrEnqueue($ch);
        }
    }

    public function testDuplicatedBetweenAddAndEnqueue()
    {
        $pool = new CURLPool(new CoOption(['concurrency' => 3]));
        $a = new Deferred;
        $curls = [
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ];
        $this->setExpectedException(\InvalidArgumentException::class, 'The cURL handle is already enqueued: DummyCurl[C]');
        foreach ($curls as $ch) {
            $pool->addOrEnqueue($ch);
        }
    }
}
