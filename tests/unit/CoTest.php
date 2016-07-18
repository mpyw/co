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

    public function testWaitGenerator()
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

    public function testAsyncGenerator()
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

    public function testClient01()
    {
        $expected = ['Response[1]', 'Response[7]'];
        $actual = Co::wait([new DummyCurl('1', 7), function () {

            $expected = ['Response[2]', 'Response[3]'];
            $actual = yield [new DummyCurl('2', 3), new DummyCurl('3', 4)];
            $this->assertEquals($expected, $actual);

            $expected = ['Response[5]', 'Response[6]'];
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
            return new DummyCurl('7', 1);
        }]);
        $this->assertEquals($expected, $actual);
    }
}
