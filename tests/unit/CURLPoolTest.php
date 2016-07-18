<?php

require __DIR__ . '/DummyCurl.php';
require __DIR__ . '/DummyCurlMulti.php';
require __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\CURLPool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;
use React\Promise\Deferred;

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
            new DummyCurl('E', 6),
            new DummyCurl('D', 5),
            new DummyCurl('C', 4),
            new DummyCurl('B', 3),
            new DummyCurl('A', 2),
        ];
        shuffle($curls);
        $invalids = [
            new DummyCurl('X', 5, true),
            new DummyCurl('Y', 3, true),
        ];
        shuffle($invalids);
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
        foreach ($curls as $curl) {
            $str = str_replace('DummyCurl', 'Response', (string)$curl);
            $this->assertContains($str, $done);
        }
        foreach ($invalids as $curl) {
            $str = str_replace('DummyCurl', 'Error', (string)$curl);
            $this->assertContains($str, $failed);
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
