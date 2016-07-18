<?php

use mpyw\Co\Co;
use mpyw\Co\CoInterface;
use mpyw\Co\CURLException;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;
use AspectMock\Test as test;

/**
 * @requires PHP 7.0
 */
class FinalBossTest extends \Codeception\TestCase\Test {
    use \Codeception\Specify;

    public function testICannotDefeatThisBug1()
    {
        $result = Co::wait(function () {
            $something = yield Co::SAFE => function () {
                new \RuntimeException;
            };
            if ($something === null) {
                return 'OMFG';
            }
        }, ['throw' => false]);
        $this->assertNotEquals('OMFG', $result);
    }
    
    public function testICannotDefeatThisBug2()
    {
        $result = Co::wait(function () {
            $something = yield Co::UNSAFE => function () {
                new \RuntimeException;
            };
            if ($something === null) {
                return 'OMFG';
            }
        }, ['throw' => false]);
        $this->assertNotEquals('OMFG', $result);
    }
    
    public function testICannotDefeatThisBug3()
    {
        $result = Co::wait(function () {
            $something = yield Co::SAFE => function () {
                new \RuntimeException;
            };
            if ($something === null) {
                return 'OMFG';
            }
        }, ['throw' => true]);
        $this->assertNotEquals('OMFG', $result);
    }
    
    public function testICannotDefeatThisBug4()
    {
        $result = Co::wait(function () {
            $something = yield Co::UNSAFE => function () {
                new \RuntimeException;
            };
            if ($something === null) {
                return 'OMFG';
            }
        }, ['throw' => true]);
        $this->assertNotEquals('OMFG', $result);
    }
}
