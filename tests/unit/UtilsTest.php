<?php

use mpyw\Co\CoInterface;
use mpyw\Co\Internal\GeneratorContainer;
use mpyw\Co\Internal\YieldableUtils;
use mpyw\Co\Internal\TypeUtils;
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
        $this->assertTrue(TypeUtils::isCurl($ch));
        curl_close($ch);
        $this->assertFalse(TypeUtils::isCurl($ch));
        $this->assertFalse(TypeUtils::isCurl(curl_multi_init()));
        $this->assertFalse(TypeUtils::isCurl([1]));
        $this->assertFalse(TypeUtils::isCurl((object)[1]));
    }

    public function testIsGeneratorContainer()
    {
        $gen = (function () {
            yield 1;
        })();
        $con = new GeneratorContainer($gen);
        $this->assertTrue(TypeUtils::isGeneratorContainer($con));
        $this->assertFalse(TypeUtils::isGeneratorContainer($gen));
    }

    public function testBasicNormalize()
    {
        $options = new CoOption;

        $genfunc = function () {
            $x = yield function () {
                return [1, 2, yield function () {
                    $y = yield 3;
                    $this->assertEquals($y, 'A');
                    return 4;
                }];
            };
            $this->assertEquals($x, 'B');
            $z = yield 5;
            $this->assertEquals($z, 'C');
            return [
                function () use ($x) { return yield $x; },
                $z,
            ];
        };

        $gen1 = YieldableUtils::normalize($genfunc, $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen1);
        $this->assertInstanceOf(\Closure::class, $gen1->current());

        $gen2 = YieldableUtils::normalize($gen1->current(), $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen2);
        $this->assertInstanceOf(\Closure::class, $gen2->current());

        $gen3 = YieldableUtils::normalize($gen2->current(), $options);
        $this->assertInstanceOf(GeneratorContainer::class, $gen3);
        $this->assertEquals(3, $gen3->current());

        $gen3->send('A');
        $this->assertFalse($gen3->valid());
        $this->assertFalse($gen3->thrown());
        $r1 = YieldableUtils::normalize($gen3->getReturnOrThrown(), $options);
        $this->assertEquals(4, $r1);

        $gen2->send(4);
        $this->assertFalse($gen2->valid());
        $this->assertFalse($gen2->thrown());
        $r2 = YieldableUtils::normalize($gen2->getReturnOrThrown(), $options);
        $this->assertEquals([1, 2, 4], $r2);

        $gen1->send('B');
        $this->assertEquals(5, $gen1->current());

        $gen1->send('C');
        $this->assertFalse($gen1->valid());
        $this->assertFalse($gen1->thrown());
        $r3 = YieldableUtils::normalize($gen1->getReturnOrThrown(), $options);
        $this->assertInstanceOf(GeneratorContainer::class, $r3[0]);
        $this->assertEquals('C', $r3[1]);

        $gen4 = $r3[0];
        $this->assertEquals('B', $gen4->current());

        $gen4->send('D');
        $this->assertFalse($gen4->valid());
        $this->assertFalse($gen4->thrown());
        $r4 = YieldableUtils::normalize($gen4->getReturnOrThrown(), $options);
        $this->assertEquals('D', $r4);
    }

    public function testNormalizeWithYieldKeysOnGeneratorFunction()
    {
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

        $gen1 = YieldableUtils::normalize($genfunc);
        $this->assertInstanceOf(GeneratorContainer::class, $gen1);
        $this->assertTrue($gen1->valid());
        $this->assertEquals(CoInterface::SAFE, $gen1->key());
        $this->assertInstanceOf(\Closure::class, $gen1->current());

        $gen2 = YieldableUtils::normalize($gen1->current(), $gen1->key());
        $this->assertInstanceOf(GeneratorContainer::class, $gen2);
        $this->assertFalse($gen2->valid());
        $this->assertTrue($gen2->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $gen2->getReturnOrThrown());

        $gen2->getYieldKey() === CoInterface::SAFE
            ? $gen1->send($gen2->getReturnOrThrown())
            : $gen1->throw_($gen2->getReturnOrThrown());
        $this->assertTrue($gen1->valid());

        $gen3 = YieldableUtils::normalize($gen1->current(), $gen1->key());
        $this->assertInstanceOf(GeneratorContainer::class, $gen3);
        $this->assertFalse($gen3->valid());
        $this->assertTrue($gen3->thrown());
        $this->assertInstanceOf(\RuntimeException::class, $gen3->getReturnOrThrown());

        $gen3->getYieldKey() === CoInterface::SAFE
            ? $gen1->send($gen3->getReturnOrThrown())
            : $gen1->throw_($gen3->getReturnOrThrown());
        $this->assertFalse($gen1->valid());
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
        ], YieldableUtils::getYieldables($r));
    }

    public function testTwoTypesOfFunctions()
    {
        $v = YieldableUtils::normalize([
            function () { return 1; },
            function () { return yield 1; },
        ], new CoOption);
        $this->assertInstanceOf(\Closure::class, $v[0]);
        $this->assertInstanceOf(GeneratorContainer::class, $v[1]);
    }

}
