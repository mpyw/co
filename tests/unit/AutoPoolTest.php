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
class AutoPoolTest extends \Codeception\TestCase\Test {
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

    public function testInvalidOption()
    {
        $this->setExpectedException(\OutOfBoundsException::class);
        $pool = new Pool(new CoOption(['autoschedule' => true]));
    }

    public function testWait()
    {
        $pool = new Pool(new CoOption(['concurrency' => 1, 'autoschedule' => true]));
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

    public function testUnlimitedConcurrency()
    {
        $pool = new Pool(new CoOption(['concurrency' => 0, 'autoschedule' => true]));
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
