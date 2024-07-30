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
        $handler = new class($options) extends CookieSessionHandler {
            protected function doWrite($sessionId, $data): bool
            {
                $return = parent::doWrite($sessionId, $data);
                $this->close();
                return $return;
            }

            protected function doDestroy($sessionId): bool
            {
                $return = parent::doDestroy($sessionId);
                $this->close();
                return $return;
            }
        };
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
        $value = $this->generateLongString(64);

        $cookies = [];
        $handler = $this->provideHandler($cookies);
        $handler->read('sid');
        $handler->write('sid', $value);
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 4,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $cookies['sessname']);

        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);

        $cookies['sessname'] = json_encode([
            'length'  => 10,
            'version' => 1,
        ]);
        $handler = $this->provideHandler($cookies);
        $handler->read('sid');
        $handler->write('sid', $value);
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 4,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $cookies['sessname']);

        $cookies['sessname'] = json_encode([
            'length'  => 3,
            'version' => 1,
        ]);
        $handler = $this->provideHandler($cookies, ['lifetime' => 1]);
        $handler->read('sid');
        $handler->write('sid', $value);
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 4,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $cookies['sessname']);

        $handler = $this->provideHandler($cookies, ['lifetime' => 10]);
        $actual = $handler->read('sid');
        $this->assertNotEmpty($actual);

        sleep(2);

        $handler = $this->provideHandler($cookies, ['lifetime' => 1]);
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
        $handler->read('sid');
        $handler->write('sid', $value);
        $this->assertEmpty($cookies);

        $handler = $this->provideHandler($cookies);
        $actual = $handler->read('sid');
        $this->assertEquals('', $actual);
    }

    function test_multiple_keys()
    {
        $value = $this->generateLongString(64);

        $cookies = [];
        $handler = $this->provideHandler($cookies, [
            'privateKey' => 'key2000',
        ]);
        $handler->read('sid');
        $handler->write('sid', $value);

        $handler = $this->provideHandler($cookies, [
            'privateKey' => ['key2002', 'key2001', 'key2000'],
        ]);
        $actual = $handler->read('sid');
        $this->assertEquals($value, $actual);

        $handler = $this->provideHandler($cookies, [
            'privateKey' => fn() => ['key2003', 'key2002', 'key2001'],
        ]);
        $actual = $handler->read('sid');
        $this->assertEquals('', $actual);
    }

    function test_cookie()
    {
        $cookies = [
            'sessname'  => json_encode(['version' => 2, 'length' => 4]),
            'sessname0' => 'hoge',
            'sessname1' => 'fuga',
            'sessname2' => 'piyo',
        ];
        $handler = $this->provideHandler($cookies);
        $handler->read('sid');
        $handler->write('sid', 'session data');
        $this->assertArrayHasKey('sessname', $cookies);
        $this->assertArrayHasKey('sessname0', $cookies);
        $this->assertArrayNotHasKey('sessname1', $cookies);
        $this->assertArrayNotHasKey('sessname2', $cookies);
    }

    function test_open()
    {
        $cookies = [];
        $handler = $this->provideHandler($cookies, [
            'storeName' => 'hoge',
        ]);
        $this->assertArrayNotHasKey('hoge', $cookies);
        $handler->read('sid');
        $handler->write('sid', 'hogera');
        $this->assertArrayHasKey('hoge', $cookies);
    }

    function test_destroy()
    {
        $cookies = [];
        $handler = $this->provideHandler($cookies);

        $handler->read('sid');
        $handler->write('sid', 'hogera');
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $cookies['sessname']);

        $this->assertTrue($handler->destroy('sessname'));
        $this->assertArrayNotHasKey('sessname', $cookies);
    }

    function test_misc()
    {
        $handler = $this->provideHandler($cookies);
        $this->assertTrue($handler->updateTimestamp('sessname', ''));
        $this->assertTrue($handler->gc(0));
        $this->assertTrue($handler->close());
    }
}
