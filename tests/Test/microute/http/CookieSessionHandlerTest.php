<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\CookieSessionHandler;

class CookieSessionHandlerTest extends \ryunosuke\Test\AbstractTestCase
{
    private $cookies = [];

    function provideHandler($cookies)
    {
        $handler = new CookieSessionHandler([
            'privateKey' => 'test',
            'cookie'     => $cookies,
            'chunkSize'  => 256,
            'setcookie'  => function ($name, $value, $expires, $path, $domain, $secure, $httponly) {
                if ($value === '') {
                    unset($this->cookies[$name]);
                }
                else {
                    $this->cookies[$name] = $value;
                }
            },
        ]);
        $handler->open('', 'sessname');
        return $handler;
    }

    function test_IO()
    {
        $value = str_repeat('hoge_', 100);

        $handler = $this->provideHandler([]);
        $handler->write('sid', $value);
        $this->assertEquals(4, $this->cookies['sessname']);

        $handler = $this->provideHandler($this->cookies);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);

        $this->cookies['sessname'] = 10;
        $handler = $this->provideHandler($this->cookies);
        $handler->write('sid', $value);
        $this->assertEquals(4, $this->cookies['sessname']);

        $this->cookies['sessname'] = 10;
        $handler = $this->provideHandler($this->cookies);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);
    }

    function test_edgecase()
    {
        $value = str_repeat('hoge_', 256 * 20);

        $this->cookies = [];
        $handler = $this->provideHandler([]);
        $handler->write('sid', $value);
        $this->assertEmpty($this->cookies);

        $handler = $this->provideHandler(['sessname' => 0]);
        $actual = $handler->read('sid');
        $this->assertEquals('', $actual);
    }

    function test_destroy()
    {
        $this->cookies = [];
        $handler = $this->provideHandler([]);
        $handler->write('sid', 'hogera');
        $this->assertEquals(1, $this->cookies['sessname']);
        $this->assertTrue($handler->destroy('sessname'));
        $this->assertEquals(0, $this->cookies['sessname']);
    }

    function test_misc()
    {
        $handler = $this->provideHandler([]);
        $this->assertTrue($handler->updateTimestamp('sessname', ''));
        $this->assertTrue($handler->gc(0));
        $this->assertTrue($handler->close());
    }
}
