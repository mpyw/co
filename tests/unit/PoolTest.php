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
}
