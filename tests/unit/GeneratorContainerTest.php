<?php

use mpyw\Co\CoInterface;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;

/**
 * @requires PHP 7.0
 */
class GeneratorContainerTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;
    private static $GeneratorContainer;

    public function _before()
    {
        self::$GeneratorContainer = Proxy::get(GeneratorContainer::class);
    }

    public function _after()
    {

    }

    public function testConstructor()
    {
        $gen = (function () { yield 1; })();
        $con = self::$GeneratorContainer::new($gen);
        $this->assertEquals($gen, $con->g);
        $this->assertEquals(spl_object_hash($gen), $con->h);
    }

    public function testToString()
    {
        $gen = (function () { yield 1; })();
        $con = new GeneratorContainer($gen);
        $this->assertEquals(spl_object_hash($gen), (string)$con);
    }

    public function testNormalFlow()
    {
        $gen = (function () {
            $this->assertEquals(2, yield 1);
            $this->assertEquals(4, yield 3);
            return 5;
        })();
        $con = new GeneratorContainer($gen);
        $this->assertEquals($con->current(), 1);
        $con->send(2);
        $this->assertEquals($con->current(), 3);
        $con->send(4);
        $this->assertFalse($con->valid());
        $this->assertEquals(5, $con->getReturnOrThrown());
        $this->setExpectedException(\LogicException::class);
        $con->send(null);
    }

    public function testPseudoReturn()
    {
        $gen = (function () {
            $this->assertEquals(2, yield 1);
            $this->assertEquals(4, yield 3);
            yield CoInterface::RETURN_WITH => 5;
        })();
        $con = new GeneratorContainer($gen);
        $this->assertEquals($con->current(), 1);
        $con->send(2);
        $this->assertEquals($con->current(), 3);
        $con->send(4);
        $this->assertFalse($con->valid());
        $this->assertEquals(5, $con->getReturnOrThrown());
        $this->setExpectedException(\LogicException::class);
        $con->send(null);
    }

    public function testInvalidGetReturn()
    {
        $gen = (function () {
            yield null;
            yield null;
        })();
        $con = new GeneratorContainer($gen);
        $this->setExpectedException(\LogicException::class);
        $con->getReturnOrThrown();
    }

    public function testInternalException()
    {
        $genfunc = function () {
            throw new \RuntimeException;
            yield null;
            yield null;
        };
        $con = new GeneratorContainer($genfunc());
        $this->assertFalse($con->valid());
        $this->assertTrue($con->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $con->getReturnOrThrown());
    }

    public function testExternalException()
    {
        $gen = (function () {
            $this->assertInstanceOf(\RuntimeException::class, yield CoInterface::SAFE => null);
            yield null;
        })();
        $con = new GeneratorContainer($gen);

        $con->key() === CoInterface::SAFE
            ? $con->send(new \RuntimeException)
            : $con->throw_(new \RuntimeException);

        $this->assertTrue($con->valid());
        $this->assertFalse($con->thrown());

        $gen = (function () {
            yield null;
            yield null;
        })();
        $con = new GeneratorContainer($gen);

        $con->key() === CoInterface::SAFE
            ? $con->send(new \RuntimeException)
            : $con->throw_(new \RuntimeException);

        $this->assertFalse($con->valid());
        $this->assertTrue($con->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $con->getReturnOrThrown());
    }

}
