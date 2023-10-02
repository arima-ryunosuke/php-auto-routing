<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\CookieSessionHandler;

class CookieSessionHandlerTest extends \ryunosuke\Test\AbstractTestCase
{
    function provideHandler(&$cookies, $options = [])
    {
        $options += [
            'privateKey' => 'test',
            'cookie'     => $cookies,
            'chunkSize'  => 256,
            'maxLength'  => 10,
            'setcookie'  => function ($name, $value, $options) use (&$cookies) {
                if ($value === '') {
                    unset($cookies[$name]);
                }
                else {
                    $cookies[$name] = $value;
                }
            },
        ];
        $handler = new CookieSessionHandler($options);
        $handler->open('', 'sessname');
        return $handler;
    }

    function generateLongString($repeat)
    {
        srand(12345);
        return implode("\n", array_map(function () { return base64_encode(rand(100000000, 999999999)); }, range(0, $repeat)));
    }

    function test_IO()
    {
        $value = $this->generateLongString(50);

        $cookies = [];
        $handler = $this->provideHandler($cookies);
        $handler->write('sid', $value);
        $this->assertEquals(json_encode([
            'length'  => 4,
            'version' => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ]), $cookies['sessname']);

        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);

        $cookies['sessname'] = json_encode([
            'length'  => 10,
            'version' => 1,
        ]);
        $handler = $this->provideHandler($cookies);
        $handler->write('sid', $value);
        $this->assertEquals(json_encode([
            'length'  => 4,
            'version' => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ]), $cookies['sessname']);

        $cookies['sessname'] = json_encode([
            'length'  => 10,
            'version' => 1,
        ]);
        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);

        $cookies['sessname'] = json_encode([
            'length'  => 3,
            'version' => 1,
        ]);
        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEmpty($actual);
    }

    function test_edgecase()
    {
        $value = implode('+', array_map(function () { return base64_encode(rand(100000000, 999999999)); }, range(0, 99)));

        $cookies = [];
        $handler = $this->provideHandler($cookies, [
            'maxLength' => 2,
        ]);
        $handler->write('sid', $value);
        $this->assertEmpty($cookies);

        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEquals('', $actual);
    }

    function test_regression()
    {
        $v0cookies = [
            'sessname'  => 2,
            'sessname0' => 'eNrbvNl5R42pf-LWU0HutQ9UFrobRRlpl5bmBFb6hWf7VVSW6me6ROXmBweFJaU4-lTmZaflhDuXRFimeeVWVGWFBlcVBhoGGhukeYXnhLsk-xhVlXlYpOZX-qQUFBYG-xk4hfoHGTuampl4euWY-_inFwZ6RpZVppea-acFhQan5LtlhgJt8TEoMM1ISg8',
            'sessname1' => 'JiEgCAIMPNEQ=',
        ];

        // v0 (no version) も読み込み可能
        $handler = $this->provideHandler($v0cookies);
        $actual = $handler->read('sid');
        $this->assertEquals('this is v0 cookie data. and other data:[[1,2,3,4,5,6,7,8,9,0],"a","b","c","d","e","f"]', $actual);

        // 書き込むとマイグレーションされて v1 になる
        $handler->write('sid', "append-$actual");
        $this->assertEquals(json_encode([
            'length'  => 2,
            'version' => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ]), $v0cookies['sessname']);
    }

    function test_open()
    {
        $cookies = [];
        $handler = $this->provideHandler($cookies, [
            'storeName' => 'hoge',
        ]);
        $this->assertArrayNotHasKey('hoge', $cookies);
        $handler->write('sid', 'hogera');
        $this->assertArrayHasKey('hoge', $cookies);
    }

    function test_destroy()
    {
        $cookies = [];
        $handler = $this->provideHandler($cookies);
        $handler->write('sid', 'hogera');
        $this->assertEquals(json_encode([
            'length'  => 1,
            'version' => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ]), $cookies['sessname']);
        $this->assertTrue($handler->destroy('sessname'));
        $this->assertEquals(json_encode([
            'length'  => 0,
            'version' => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ]), $cookies['sessname']);
    }

    function test_misc()
    {
        $handler = $this->provideHandler($cookies);
        $this->assertTrue($handler->updateTimestamp('sessname', ''));
        $this->assertTrue($handler->gc(0));
        $this->assertTrue($handler->close());
    }
}
