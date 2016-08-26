<?php

require_once __DIR__ . '/DummyCurl.php';
require_once __DIR__ . '/DummyCurlMulti.php';
require_once __DIR__ . '/DummyCurlFunctions.php';

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\CURLException;
use mpyw\Co\Internal\CoOption;
use mpyw\Co\Internal\Pool;
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
        test::double('mpyw\Co\Internal\TypeUtils', ['isCurl' => function ($arg) {
            return $arg instanceof \DummyCurl;
        }]);
        if (!defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            define('CURLMOPT_MAX_TOTAL_CONNECTIONS', 13);
        }
    }

    public function _after()
    {
        test::clean();
    }

    public function testRunningTrue()
    {
        $result = null;
        Co::wait(function () use (&$result) {
            yield;
            $result = Co::isRunning();
        });
        $this->assertSame(true, $result);
    }

    public function testRunningFalse()
    {
        $result = Co::isRunning();
        $this->assertSame(false, $result);
    }

    public function testSetDefaultOptions()
    {
        try {
            $this->assertTrue(Proxy::get(CoOption::class)->getStatic('defaults')['throw']);
            Co::setDefaultOptions(['throw' => false]);
            $this->assertFalse(Proxy::get(CoOption::class)->getStatic('defaults')['throw']);
        } finally {
            Co::setDefaultOptions(['throw' => true]);
        }
    }

    public function testGetDefaultOptions()
    {
        $expected = Proxy::get(CoOption::class)->getStatic('defaults');
        $actual = Co::getDefaultOptions();
        $this->assertEquals($expected, $actual);
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
                        yield;
                    };
                    yield;
                };
                yield;
            };
            $y = yield 2;
            return $x + $y;
        };
        $this->assertEquals(5, Co::wait($genfunc));

        $genfunc = function () {
            yield;
            return array_sum(array_map('current', yield [
                [function () { return 7; yield; }],
                [function () { return 9; yield; }],
                [function () { yield; return 13; }],
            ]));
        };
        $this->assertEquals(29, Co::wait($genfunc));
    }

    public function testAsyncBasic()
    {
        $i = 0;
        $genfunc = function () use (&$i) {
            yield;
            Co::async(function () use (&$i) {
                yield;
                ++$i;
            });
            Co::async(function () use (&$i) {
                yield;
                ++$i;
            });
        };
        Co::wait($genfunc);
        $this->assertEquals($i, 2);
    }

    public function testAsyncOverridesThrowInvalid()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $r = Co::wait(function () {
            yield;
            Co::async(function () {
                yield;
                throw new \RuntimeException;
            }, []);
            return 'done';
        }, ['throw' => false]);
    }

    public function testAsyncOverridesThrowTrue()
    {
        $this->setExpectedException(\RuntimeException::class);
        Co::wait(function () {
            yield;
            Co::async(function () {
                yield;
                throw new \RuntimeException;
            }, true);
        }, ['throw' => false]);
    }

    public function testAsyncOverridesThrowFalse()
    {
        $r = Co::wait(function () {
            yield;
            Co::async(function () {
                yield;
                throw new \RuntimeException;
            }, false);
            return 'done';
        }, ['throw' => true]);
        $this->assertEquals('done', $r);
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

    public function testRuntimeExceptionCaptured()
    {
        $e = Co::wait(function () {
            $e = yield Co::SAFE => function () {
                yield;
                throw new \RuntimeException;
            };
            $this->assertInstanceOf(\RuntimeException::class, $e);
            return $e;
        });
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testRuntimeExceptionThrown()
    {
        $this->setExpectedException(\RuntimeException::class);
        Co::wait(function () {
            yield function () {
                yield;
                throw new \RuntimeException;
            };
        });
    }

    public function testRuntimeExceptionTrappedTopLevel()
    {
        $e = Co::wait(function () {
            yield function () {
                yield;
                throw new \RuntimeException;
            };
        }, ['throw' => false]);
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testRuntimeExceptionCapturedDerivedFromReturn()
    {
        $e = Co::wait(function () {
            $e = yield Co::SAFE => function () {
                return function () {
                    yield;
                    throw new \RuntimeException;
                };
                yield;
            };
            $this->assertInstanceOf(\RuntimeException::class, $e);
            return $e;
        });
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testLogicExceptionHandling()
    {
        Co::wait(function () {
            try {
                yield function () {
                    yield;
                    throw new \LogicException;
                };
            } catch (\LogicException $e) {
                $this->assertTrue(true);
            }
        }, ['throw' => false]);
        $this->setExpectedException(\LogicException::class);
        Co::wait(function () {
            yield function () {
                yield;
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
                        yield;
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
                    yield;
                    throw new \RuntimeException('01');
                },
            ];
            $this->assertEquals('Response[2]', $y[0]);
            $this->assertInstanceOf(\RuntimeException::class, $y[1]);
            $this->assertEquals('01', $y[1]->getMessage());
            $y = yield Co::SAFE => [
                function () {
                    $this->assertEquals('Response[3]', yield new DummyCurl('3', 1));
                    $y = yield Co::SAFE => function () {
                        yield;
                        throw new \RuntimeException('02');
                    };
                    $this->assertInstanceOf(\RuntimeException::class, $y);
                    $this->assertEquals('02', $y->getMessage());
                    yield Co::SAFE => function () {
                        yield;
                        throw new \RuntimeException('03');
                    };
                    $this->assertTrue(false);
                },
                function () {
                    $y = yield Co::SAFE => function () {
                        yield;
                        throw new \RuntimeException('04');
                    };
                    $this->assertInstanceOf(\RuntimeException::class, $y);
                    $this->assertEquals('04', $y->getMessage());
                    yield Co::SAFE => function () {
                        yield;
                        throw new \RuntimeException('05');
                    };
                    $this->assertTrue(false);
                }
            ];
            $y = yield Co::SAFE => function () {
                yield function () {
                    $y = yield Co::SAFE => function () {
                        yield function () {
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
            yield;
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

    public function testDelay()
    {
        $results = Co::wait([
            function () use (&$x1, &$y1) {
                yield Co::DELAY => 0.5;
                $x1 = microtime(true);
                yield Co::DELAY => 1.5;
                $y1 = microtime(true);
                return true;
            },
            function () use (&$x2, &$y2) {
                yield Co::DELAY => 0.6;
                $x2 = microtime(true);
                yield Co::DELAY => 0.7;
                $y2 = microtime(true);
                throw new \RuntimeException;
            },
        ], ['throw' => false]);
        $this->assertNotNull($x1);
        $this->assertNotNull($y1);
        $this->assertNotNull($x2);
        $this->assertNotNull($y2);
        $this->assertTrue($x1 < $x2);
        $this->assertTrue($x2 < $y2);
        $this->assertTrue($y2 < $y1);
        $this->assertTrue($results[0]);
        $this->assertInstanceOf(\RuntimeException::class, $results[1]);
    }

    public function testBadWaitCall()
    {
        $this->setExpectedException(\BadMethodCallException::class);
        Co::wait(function () {
            yield;
            Co::wait(1);
        });
    }

    public function testBadAsyncCall()
    {
        $this->setExpectedException(\BadMethodCallException::class);
        Co::async(1);
    }

    public function testUncaughtRuntimeExceptionInClosureParallelToInfiniteDelayLoop()
    {
        $this->setExpectedException(\RuntimeException::class);
        Co::wait([
            function () {
                while (true) {
                    yield Co::SLEEP => 0.1;
                }
            },
            function () {
                yield;
                throw new \RuntimeException;
            }
        ]);
    }

    public function testUncaughtRuntimeExceptionInGeneratorParallelToInfiniteDelayLoop()
    {
        $this->setExpectedException(\RuntimeException::class);
        Co::wait([
            function () {
                while (true) {
                    yield Co::SLEEP => 0.1;
                }
            },
            function () {
                yield;
                throw new \RuntimeException;
            }
        ]);
    }

    public function testDuplicatedGenerators()
    {
        $this->setExpectedException(\DomainException::class);
        $func = function () {
            yield Co::DELAY => 0.01;
        };
        $gen = $func();
        Co::wait([$gen, $gen]);
    }

    public function testDuplicatedAdd()
    {
        $this->setExpectedException(\DomainException::class, 'Duplicated cURL resource or Generator instance found.');
        Co::wait([
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ], ['concurrency' => 4]);
    }

    public function testDuplicatedEnqueue()
    {
        $this->setExpectedException(\DomainException::class, 'Duplicated cURL resource or Generator instance found.');
        Co::wait([
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ], ['concurrency' => 2]);
    }

    public function testDuplicatedBetweenAddAndEnqueue()
    {
        $this->setExpectedException(\DomainException::class, 'Duplicated cURL resource or Generator instance found.');
        Co::wait([
            new DummyCurl('A', 2),
            new DummyCurl('B', 3),
            new DummyCurl('C', 4),
            new DummyCurl('C', 5),
        ], ['concurrency' => 3]);
    }
}
