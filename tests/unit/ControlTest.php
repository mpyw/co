<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\CURLException;
use mpyw\Co\AllFailedException;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\Pool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class ControlTest extends \Codeception\TestCase\Test {
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

    public function testRaceSuccess()
    {
        $r = Co::wait(Co::race([
            new DummyCurl('A', 3, true),
            new DummyCurl('B', 2),
        ]));
        $this->assertEquals('Response[B]', $r);
    }

    public function testRaceFailure()
    {
        $this->setExpectedException(CURLException::class, 'Error[B]');
        $r = Co::wait(Co::race([
            new DummyCurl('A', 3),
            new DummyCurl('B', 2, true),
        ]));
    }

    public function testRaceEmpty()
    {
        try {
            Co::wait(Co::race(['A', 'B']));
            $this->assertTrue(false);
        } catch (AllFailedException $e) {
            $this->assertEquals('Co::race() failed.', $e->getMessage());
            $this->assertEquals(['A', 'B'], $e->getReasons());
        }
    }

    public function testAnyAllSuccess()
    {
        $r = Co::wait(Co::any([
            new DummyCurl('A', 3),
            new DummyCurl('B', 2),
        ]));
        $this->assertEquals('Response[B]', $r);
    }

    public function testAnyPartialSuccess()
    {
        $r = Co::wait(Co::any([
            new DummyCurl('A', 3),
            new DummyCurl('B', 2, true),
        ]));
        $this->assertEquals('Response[A]', $r);
    }

    public function testAnyAllFailure()
    {
        try {
            Co::wait(Co::any([
                new DummyCurl('A', 3, true),
                new DummyCurl('B', 2, true),
            ]));
            $this->assertTrue(false);
        } catch (AllFailedException $e) {
            $this->assertEquals('Co::any() failed.', $e->getMessage());
            $this->assertEquals('Error[A]', $e->getReasons()[0]->getMessage());
            $this->assertEquals('Error[B]', $e->getReasons()[1]->getMessage());
        }
    }

    public function testAnyEmpty()
    {
        try {
            Co::wait(Co::any(['A', 'B']));
            $this->assertTrue(false);
        } catch (AllFailedException $e) {
            $this->assertEquals('Co::any() failed.', $e->getMessage());
            $this->assertEquals(['A', 'B'], $e->getReasons());
        }
    }

    public function testAll()
    {
        $a = new DummyCurl('A', 3);
        $b = new DummyCurl('B', 2);
        $r = Co::wait(Co::all([$a, $b]));
        $this->assertEquals('Response[A]', $r[0]);
        $this->assertEquals('Response[B]', $r[1]);
    }
}
