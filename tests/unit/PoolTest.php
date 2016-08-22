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
use mpyw\RuntimePromise\Deferred;

/**
 * @requires PHP 7.0
 */
class PoolTest extends \Codeception\TestCase\Test {
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
        $pool = new Pool(new CoOption(['concurrency' => 0]));
        $a = new Deferred;
        $curls = [];
        foreach (range(1, 100) as $i) {
            $curls[] = new DummyCurl($i, 2);
        }
        $done = [];
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
            $pool->addCurl($ch, $dfd);
        }
        $pool->wait();
        foreach ($curls as $curl) {
            $str = str_replace('DummyCurl', 'Response', (string)$curl);
            $this->assertContains($str, $done);
            $this->assertEquals([0, 2], [$curl->startedAt(), $curl->stoppedAt()]);
        }
    }

    public function testWaitCurlAndDelay()
    {
        $pool = new Pool(new CoOption(['concurrency' => 3]));
        $pool->addCurl(new DummyCurl('A', 10), $a = new Deferred);
        $pool->addDelay(1.3, $b = new Deferred);
        $pool->addDelay(1.1, $c = new Deferred);
        $pool->addDelay(1.2, $d = new Deferred);
        $a->promise()->then(function () use (&$x) {
            $x = microtime(true);
        });
        $b->promise()->then(function () use (&$y) {
            $y = microtime(true);
        });
        $c->promise()->then(function () use (&$z) {
            $z = microtime(true);
        });
        $d->promise()->then(function () use (&$w) {
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

    public function testInvalidDelayType()
    {
        $pool = new Pool(new CoOption(['concurrency' => 3]));
        $this->setExpectedException(\InvalidArgumentException::class);
        $pool->addDelay([], new Deferred);
    }

    public function testInvalidDelayDomain()
    {
        $pool = new Pool(new CoOption(['concurrency' => 3]));
        $this->setExpectedException(\DomainException::class);
        $pool->addDelay(-1, new Deferred);
    }

    public function testCurlWithoutDeferred()
    {
        $pool = new Pool(new CoOption);
        $pool->addCurl(new DummyCurl('valid', 1));
        $pool->addCurl(new DummyCurl('invalid', 1, true));
        $pool->wait();
        $this->assertTrue(true);
    }

    public function testUnlimitedConcurrency()
    {
        $pool = new Pool(new CoOption(['concurrency' => 0]));
        $a = new Deferred;
        $curls = [];
        foreach (range(1, 100) as $i) {
            $curls[] = new DummyCurl($i, 2);
        }
        $done = [];
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
            $pool->addCurl($ch, $dfd);
        }
        $pool->wait();
        foreach ($curls as $curl) {
            $str = str_replace('DummyCurl', 'Response', (string)$curl);
            $this->assertContains($str, $done);
            $this->assertEquals([0, 2], [$curl->startedAt(), $curl->stoppedAt()]);
        }
    }
}
