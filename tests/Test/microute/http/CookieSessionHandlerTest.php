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

    function test_regression()
    {
        // v0 も読み込み可能
        $v0cookies = [
            'sessname'  => 2,
            'sessname0' => 'eNrbvNl5R42pf-LWU0HutQ9UFrobRRlpl5bmBFb6hWf7VVSW6me6ROXmBweFJaU4-lTmZaflhDuXRFimeeVWVGWFBlcVBhoGGhukeYXnhLsk-xhVlXlYpOZX-qQUFBYG-xk4hfoHGTuampl4euWY-_inFwZ6RpZVppea-acFhQan5LtlhgJt8TEoMM1ISg8',
            'sessname1' => 'JiEgCAIMPNEQ=',
        ];
        $handler = $this->provideHandler($v0cookies);
        $actual = $handler->read('sid');
        $this->assertEquals('this is v0 cookie data. and other data:[[1,2,3,4,5,6,7,8,9,0],"a","b","c","d","e","f"]', $actual);

        // 書き込むとマイグレーションされて v2 になる
        $handler->write('sid', "append-$actual");
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $v0cookies['sessname']);

        // v1 も読み込み可能
        $v1cookies = [
            'sessname'  => json_encode(['version' => 1, 'length' => 2]),
            'sessname0' => 'D4nuo82eyCm2Rz8tqB3QEzN6SkpXVlBwcHV4UlVBdzhEekVvajRaWVBNc0hjVy9kMXJodFlDQXI2SloydlFza2NmUTVuUz',
            'sessname1' => 'NaYTJ5SkJQcjJiVXc5d3Z6WHlYS0k2YUduNm1RMGpoS1NYWTdscHcvQ3V5UnlkM2ZSUjdJPQ==',
        ];
        $handler = $this->provideHandler($v1cookies);
        $actual = $handler->read('sid');
        $this->assertEquals('this is v1 cookie data. and other data:[[1,2,3,4,5,6,7,8,9,0],"a","b","c","d","e","f"]', $actual);

        // 書き込むとマイグレーションされて v2 になる
        $handler->write('sid', "append-$actual");
        $this->assertJsonStringEquals([
            'version' => 2,
            'length'  => 1,
            'ctime'   => time(),
            'atime'   => time(),
            'mtime'   => time(),
        ], $v1cookies['sessname']);
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
