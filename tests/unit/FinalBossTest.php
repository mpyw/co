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

    public function testICannotDefeatThisBug()
    {
        $e = Co::wait(function () {
            yield Co::UNSAFE => function () {
                new \RuntimeException;
            };
        }, ['throw' => false]);
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }
}
