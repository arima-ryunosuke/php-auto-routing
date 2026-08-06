<?php
namespace ryunosuke\Test\microute\attribute;

use ryunosuke\microute\attribute\Method;

class MethodTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_preset()
    {
        $attr = new Method(['@safe']);
        $methods = [];
        $attr->merge($methods);
        $this->assertEquals([
            'HEAD'    => true,
            'OPTIONS' => true,
            'GET'     => true,
            '*'       => false,
        ], $methods);

        $attr = new Method(['@safe' => false]);
        $methods = [];
        $attr->merge($methods);
        $this->assertEquals([
            'HEAD'    => false,
            'OPTIONS' => false,
            'GET'     => false,
            '*'       => true,
        ], $methods);

        $attr = new Method(['@unsafe']);
        $methods = [];
        $attr->merge($methods);
        $this->assertEquals([
            'HEAD'    => false,
            'OPTIONS' => false,
            'GET'     => false,
            '*'       => true,
        ], $methods);

        $attr = new Method(['@unsafe' => false]);
        $methods = [];
        $attr->merge($methods);
        $this->assertEquals([
            'HEAD'    => true,
            'OPTIONS' => true,
            'GET'     => true,
            '*'       => false,
        ], $methods);
    }
}
