<?php

use mpyw\Co\Internal\CoOption;
use mpyw\Privator\Proxy;
use mpyw\Privator\ProxyException;

/**
 * @requires PHP 7.0
 */
class CoOptionTest extends \Codeception\TestCase\Test {

    use \Codeception\Specify;
    private static $CoOption;

    public function _before()
    {
        $this->default = CoOption::getDefault();
        self::$CoOption = Proxy::get(CoOption::class);
    }

    public function _after()
    {
        CoOption::setDefault($this->default);
    }

    public function testConstructor()
    {
        $this->specify('default construction', function () {
            $options = new CoOption();
            $defaults = self::$CoOption::getStatic('defaults');
            foreach ($defaults as $key => $value) {
                $this->assertEquals($value, $options[$key]);
            }
        });

        $this->specify('custom construction', function () {
            $def = [
                'throw' => false,
                'pipeline' => true,
                'multiplex' => false,
                'interval' => 0.3,
                'concurrency' => 1,
            ];
            $options = new CoOption($def);
            foreach ($def as $key => $value) {
                $this->assertEquals($value, $options[$key]);
            }
        });
    }

    public function testStaticDefaults()
    {
        $def = [
            'throw' => false,
            'pipeline' => true,
            'multiplex' => false,
            'interval' => 0.3,
            'concurrency' => 1,
        ];
        CoOption::setDefault($def);
        $options = CoOption::getDefault();
        foreach ($def as $key => $value) {
            $this->assertEquals($value, $options[$key]);
        }
    }

    public function testValidateNaturalInt()
    {
        $this->assertEquals(self::$CoOption::validateNaturalInt('', 1), 1);
        $this->assertEquals(self::$CoOption::validateNaturalInt('', '3'), 3);
        $this->assertEquals(self::$CoOption::validateNaturalInt('', 3.0), 3);
        $invalid_cases = [
            function () {
                self::$CoOption::validateNaturalInt('', []);
            },
            function () {
                self::$CoOption::validateNaturalInt('', '3.0');
            },
            function () {
                self::$CoOption::validateNaturalInt('', INF);
            },
        ];
        foreach ($invalid_cases as $i => $case) {
            $this->specify("invalid types ($i)", $case, ['throws' => \InvalidArgumentException::class]);
        }
        $invalid_cases = [
            function () {
                self::$CoOption::validateNaturalInt('', -1);
            },
        ];
        foreach ($invalid_cases as $i => $case) {
            $this->specify("invalid domains ($i)", $case, ['throws' => \DomainException::class]);
        }
    }

    public function testValidateNaturalFloat()
    {
        $this->assertEquals(self::$CoOption::validateNaturalFloat('', 1), 1.0);
        $this->assertEquals(self::$CoOption::validateNaturalFloat('', '3'), 3.0);
        $this->assertEquals(self::$CoOption::validateNaturalFloat('', 3.0), 3.0);
        $this->assertEquals(self::$CoOption::validateNaturalFloat('', '3.0'), 3.0);
        $invalid_cases = [
            function () {
                self::$CoOption::validateNaturalFloat('', []);
            },
            function () {
                self::$CoOption::validateNaturalFloat('', INF);
            },
        ];
        foreach ($invalid_cases as $i => $case) {
            $this->specify("invalid types ($i)", $case, ['throws' => \InvalidArgumentException::class]);
        }
        $invalid_cases = [
            function () {
                self::$CoOption::validateNaturalFloat('', -1.0);
            },
        ];
        foreach ($invalid_cases as $i => $case) {
            $this->specify("invalid domains ($i)", $case, ['throws' => \DomainException::class]);
        }
    }

    public function testValidateBool()
    {
        $this->assertEquals(self::$CoOption::validateBool('', true), true);
        $this->assertEquals(self::$CoOption::validateBool('', false), false);
        $this->assertEquals(self::$CoOption::validateBool('', 'true'), true);
        $this->assertEquals(self::$CoOption::validateBool('', 'false'), false);
        $this->assertEquals(self::$CoOption::validateBool('', 'yes'), true);
        $this->assertEquals(self::$CoOption::validateBool('', 'no'), false);
        $this->assertEquals(self::$CoOption::validateBool('', '1'), true);
        $this->assertEquals(self::$CoOption::validateBool('', '0'), false);
        $this->specify('invalid', function () {
            self::$CoOption::validateBool('', []);
        }, ['throws' => \InvalidArgumentException::class]);
    }

    public function testReconfigure()
    {
        $options = new CoOption();
        $new_options = $options->reconfigure(['pipeline' => true]);
        $this->assertTrue($new_options['pipeline']);
        $this->assertNotEquals($new_options, $options);
    }

    public function testSpecialMethodCall()
    {
        $options = new CoOption();
        $this->assertTrue(isset($options['pipeline']));
        $this->assertFalse(isset($options['invalid']));

        $this->specify('invalid construction', function () use ($options) {
            new CoOption(['invalid' => true]);
        }, ['throws' => \DomainException::class]);

        $this->specify('invalid assignment', function () use ($options) {
            $options['pipeline'] = false;
        }, ['throws' => \BadMethodCallException::class]);

        $this->specify('invalid unset', function () use ($options){
            unset($options['pipeline']);
        }, ['throws' => \BadMethodCallException::class]);

        $this->specify('Undefined field', function () use ($options){
            $options['invalid'];
        }, ['throws' => \DomainException::class]);
    }

}
