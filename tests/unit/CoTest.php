<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\CURLException;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\CURLPool;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class CoTest extends \Codeception\TestCase\Test {
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

    public function testWaitBasic()
    {
        $this->assertEquals([1], Co::wait([1]));

        $genfunc = function () {
            $x = yield 3;
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));

        $genfunc = function () {
            $x = yield function () {
                yield Co::RETURN_WITH => yield function () {
                    return yield function () {
                        return 3;
                        yield null;
                    };
                    yield null;
                };
                yield null;
            };
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));

        $genfunc = function () {
            return array_sum(array_map('current', yield [
                [function () { return 7; yield null; }],
                [function () { return 9; }],
                [function () { yield null; return 13; }],
            ]));
        };
        $this->assertEquals(29, Co::wait($genfunc));
    }

    public function testAsyncBasic()
    {
        $i = 0;
        $genfunc = function () use (&$i) {
            Co::async(function () use (&$i) {
                ++$i;
            });
            Co::async(function () use (&$i) {
                ++$i;
            });
        };
        Co::wait($genfunc);
        $this->assertEquals($i, 2);
    }

    public function testWaitCurl()
    {
        $result = Co::wait([
            'A' => new DummyCurl('A', mt_rand(1, 10)),
            [
                'B' => new DummyCurl('B', mt_rand(1, 10)),
            ],
            [
                [
                    'C' => new DummyCurl('C', mt_rand(1, 10)),
                ]
            ]
        ]);
        $this->assertEquals([
            'A' => 'Response[A]',
            [
                'B' => 'Response[B]',
            ],
            [
                [
                    'C' => 'Response[C]',
                ],
            ],
        ], $result);
    }

    public function testRuntimeExceptionHandling()
    {
        $e = Co::wait(function () {
            $e = yield function () {
                throw new \RuntimeException;
            };
            $this->assertInstanceOf(\RuntimeException::class, $e);
            return $e;
        }, ['throw' => false]);
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->setExpectedException(\RuntimeException::class);
        Co::wait(function () {
            yield function () {
                throw new \RuntimeException;
            };
        });
    }

    public function testLogicExceptionHandling()
    {
        $this->setExpectedException(\LogicException::class);
        Co::wait(function () {
            yield function () {
                throw new \LogicException;
            };
        }, ['throw' => false]);
    }

    public function testComplicated01()
    {
        $expected = ['Response[1]', 'Response[7]'];
        $actual = Co::wait([new DummyCurl('1', 7), function () {

            $expected = ['Response[2]', 'Response[3]'];
            $actual = yield [new DummyCurl('2', 3), new DummyCurl('3', 4)];
            $this->assertEquals($expected, $actual);

            $expected = ['Response[5]', ['x' => ['y' => 'Response[6]']]];
            $actual = yield [
                function () {
                    $this->assertEquals('Response[4]', yield new DummyCurl('4', 1));
                    return new DummyCurl('5', 1);
                },
                function () {
                    $e = yield CO::SAFE => new DummyCurl('invalid-01', 1, true);
                    $this->assertInstanceOf(CURLException::class, $e);
                    $this->assertEquals('Error[invalid-01]', $e->getMessage());
                    try {
                        yield new DummyCurl('invalid-02', 1, true);
                        $this->assertTrue(false);
                    } catch (CURLException $e) {
                        $this->assertEquals('Error[invalid-02]', $e->getMessage());
                    }
                    return ['x' => ['y' => function () {
                        return new DummyCurl('6', 2);
                    }]];
                }
            ];
            $this->assertEquals($expected, $actual);

            return new DummyCurl('7', 1);

        }]);
        $this->assertEquals($expected, $actual);
    }

    public function testComplicated02()
    {
        $expected = ['Response[1]', 'Response[4]'];
        $actual = Co::wait([new DummyCurl('1', 5), function () {

            $y = yield Co::SAFE => [
                new DummyCurl('2', 3),
                function () {
                    throw new \RuntimeException('01');
                },
            ];
            $this->assertEquals('Response[2]', $y[0]);
            $this->assertInstanceOf(\RuntimeException::class, $y[1]);
            $this->assertEquals('01', $y[1]->getMessage());

            $y = yield Co::SAFE => [
                function () {
                    $this->assertEquals('Response[3]', yield new DummyCurl('3', 1));
                    $y = yield function () {
                        throw new \RuntimeException('02');
                    };
                    $this->assertInstanceOf(\RuntimeException::class, $y);
                    $this->assertEquals('02', $y->getMessage());
                    yield Co::UNSAFE => function () {
                        throw new \RuntimeException('03');
                    };
                    $this->assertTrue(false);
                },
                function () {
                    $y = yield function () {
                        throw new \RuntimeException('04');
                    };
                    $this->assertInstanceOf(\RuntimeException::class, $y);
                    $this->assertEquals('04', $y->getMessage());
                    yield Co::UNSAFE => function () {
                        throw new \RuntimeException('05');
                    };
                    $this->assertTrue(false);
                }
            ];
            $y = yield Co::SAFE => function () {
                yield Co::UNSAFE => function () {
                    $y = yield Co::SAFE => function () {
                        yield Co::UNSAFE => function () {
                            yield new DummyCurl('invalid', 1, true);
                        };
                    };
                    throw $y;
                };
            };
            $this->assertInstanceOf(CURLException::class, $y);
            $this->assertEquals('Error[invalid]', $y->getMessage());
            return new DummyCurl('4', 1);
        }]);
        $this->assertEquals($expected, $actual);
    }

    public function testComplicatedAsync()
    {
        $async_results = [];
        $sync_results = Co::wait([new DummyCurl('5', 1), function () use (&$async_results) {
            Co::async(function () use (&$async_results) {
                $async_results[] = yield new DummyCurl('2', 6);
                Co::async(function () use (&$async_results) {
                    $async_results[] = yield new DummyCurl('4', 4);
                });
                $async_results[] = yield new DummyCurl('3', 3);
            });
            Co::async(function () use (&$async_results) {
                $async_results[] = yield new DummyCurl('1', 1);
            });
            return new DummyCurl('6', 2);
        }]);
        $this->assertEquals(['Response[5]', 'Response[6]'], $sync_results);
        $this->assertEquals(['Response[1]', 'Response[2]', 'Response[3]', 'Response[4]'], $async_results);
    }
}
