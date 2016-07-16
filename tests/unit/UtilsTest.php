<?php

use mpyw\Co\CoInterface;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\Utils;
use mpyw\Co\Internal\CoOption;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;

/**
 * @requires PHP 7.0
 */
class UtilsTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;

    public function _before()
    {
    }

    public function _after()
    {
    }

    public function testIsCurl()
    {
        $ch = curl_init();
        $this->assertTrue(Utils::isCurl($ch));
        curl_close($ch);
        $this->assertFalse(Utils::isCurl($ch));
        $this->assertFalse(Utils::isCurl(curl_multi_init()));
        $this->assertFalse(Utils::isCurl([1]));
        $this->assertFalse(Utils::isCurl((object)[1]));
    }

    public function testIsGeneratorContainer()
    {
        $gen = (function () {
            yield 1;
        })();
        $con = new GeneratorContainer($gen);
        $this->assertTrue(Utils::isGeneratorContainer($con));
        $this->assertFalse(Utils::isGeneratorContainer($gen));
    }

    public function testIsArrayLike()
    {
        $gen = (function () {
            yield 1;
        })();
        $stdclass = (object)[1, 2, 3];
        $array = [1, 2, 3];
        $arrayobj = new \ArrayObject([1, 2, 3]);
        $stmt = new \PDOStatement;
        $null = null;
        $int = 1;
        $resource = curl_init();
        $this->assertFalse(Utils::isArrayLike($gen));
        $this->assertFalse(Utils::isArrayLike($stdclass));
        $this->assertTrue(Utils::isArrayLike($array));
        $this->assertTrue(Utils::isArrayLike($arrayobj));
        $this->assertTrue(Utils::isArrayLike($stmt));
        $this->assertFalse(Utils::isArrayLike($null));
        $this->assertFalse(Utils::isArrayLike($int));
        $this->assertFalse(Utils::isArrayLike($resource));
    }

    public function testBasicNormalize()
    {
        $options = new CoOption;

        $genfunc = function () {
            $x = yield function () {
                return function () {
                    return new \ArrayObject([1, 2, function () {
                        $y = yield 3;
                        $this->assertEquals($y, 'A');
                        return 4;
                    }]);
                };
            };
            $this->assertEquals($x, 'B');
            $z = yield 5;
            $this->assertEquals($z, 'C');
            return new \ArrayObject([
                function () use ($x) { return $x; },
                $z,
            ]);
        };

        $gen1 = Utils::normalize($genfunc, $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen1);
        $this->assertInstanceOf(\Closure::class, $gen1->current());

        $r1 = Utils::normalize($gen1->current(), $options);
        $this->assertEquals(1, $r1[0]);
        $this->assertEquals(2, $r1[1]);
        $this->assertInstanceOf(GeneratorContainer::class, $r1[2]);

        $gen2 = $r1[2];
        $this->assertEquals(3, $gen2->current());
        $gen2->send('A');

        $r2 = Utils::normalize($gen2->getReturnOrThrown(), $options);
        $this->assertEquals(4, $r2);
        $this->assertFalse($gen2->valid());
        $this->assertFalse($gen2->thrown());

        $gen1->send('B');
        $this->assertEquals(5, $gen1->current());

        $gen1->send('C');
        $r3 = $gen1->getReturnOrThrown();
        $this->assertInstanceOf(\ArrayObject::class, $r3);
        $this->assertFalse($gen2->valid());
        $this->assertFalse($gen2->thrown());

        $r3 = Utils::normalize($r3, $options);
        $this->assertEquals(['B', 'C'], $r3);
    }

    public function testNormalizeWithYieldKeysOnGeneratorFunction()
    {
        $options = new CoOption;
        $genfunc = function () {
            yield CoInterface::SAFE => function () {
                throw new \RuntimeException;
                yield null;
            };
            yield function () {
                throw new \RuntimeException;
                yield null;
            };
            yield null;
        };

        $gen1 = Utils::normalize($genfunc, $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen1);
        $this->assertTrue($gen1->valid());
        $this->assertEquals(CoInterface::SAFE, $gen1->key());
        $this->assertInstanceOf(\Closure::class, $gen1->current());

        $gen2 = Utils::normalize($gen1->current(), $gen1->getOptions(), $gen1->key());
        $this->assertInstanceOf(GeneratorContainer::class, $gen2);
        $this->assertFalse($gen2->valid());
        $this->assertFalse($gen2->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $gen2->getReturnOrThrown());

        $gen1->send(null);

        $gen3 = Utils::normalize($gen1->current(), $gen1->getOptions(), $gen1->key());
        $this->assertInstanceOf(GeneratorContainer::class, $gen3);
        $this->assertFalse($gen3->valid());
        $this->assertTrue($gen3->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $gen2->getReturnOrThrown());
    }

    public function testNormalizeWithYieldKeysOnNormalFunction()
    {
        $options = new CoOption;
        $genfunc = function () {
            yield CoInterface::SAFE => function () {
                throw new \RuntimeException;
            };
            yield function () {
                throw new \RuntimeException;
            };
            yield null;
        };

        $gen1 = Utils::normalize($genfunc, $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen1);
        $this->assertTrue($gen1->valid());
        $this->assertEquals(CoInterface::SAFE, $gen1->key());
        $this->assertInstanceOf(\Closure::class, $gen1->current());

        try {
            Utils::normalize($gen1->current(), $gen1->getOptions(), $gen1->key());
            $this->assertFalse(true);
        } catch (\RuntimeException $e) {
            $gen1->throw_($e);
        }
        $this->assertTrue($gen1->valid());
        $this->assertFalse($gen1->thrown());

        $gen1->send(null);

        try {
            Utils::normalize($gen1->current(), $gen1->getOptions(), $gen1->key());
            $this->assertFalse(true);
        } catch (\RuntimeException $e) {
            $gen1->throw_($e);
        }
        $this->assertFalse($gen1->valid());
        $this->assertTrue($gen1->thrown());
    }

    public function testGetYieldables()
    {
        $genfunc = function () {
            yield null;
        };
        $r = [
            'x' => [
                'y1' => (object)[
                    'ignored_0' => curl_init(),
                    'ignored_1' => new GeneratorContainer($genfunc()),
                ],
                'y2' => [
                    'z1' => $z1 = curl_init(),
                    'z2' => $z2 = new GeneratorContainer($genfunc()),
                ],
            ],
        ];
        $this->assertEquals([
            (string)$z1 => [
                'value' => $z1,
                'keylist' => ['x', 'y2', 'z1'],
            ],
            (string)$z2 => [
                'value' => $z2,
                'keylist' => ['x', 'y2', 'z2'],
            ],
        ], Utils::getYieldables($r));
    }

}
