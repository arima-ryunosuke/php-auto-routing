<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\Response;
use Symfony\Component\HttpFoundation\Cookie;

class ResponseTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_setHeaders()
    {
        $response = new Response();

        $response->setHeaders([
            'X-Hoge' => 'hoge',
            'X-Fuga' => 'fuga',
        ]);
        $this->assertEquals('hoge', $response->headers->get('X-Hoge'));
        $this->assertEquals('fuga', $response->headers->get('X-Fuga'));
    }

    function test_cookie()
    {
        $response = new Response();

        $response->setCookie(new Cookie('name', 'value'));
        $this->assertStringStartsWith('name=value;', $response->headers->get('set-cookie'));
    }

    function test_cookies()
    {
        $response = new Response();

        $response->setCookies([
            'hoge' => 'hoge',
            'fuga' => 'fuga',
        ]);
        $cookies = $response->headers->all('set-cookie');
        $this->assertStringStartsWith('hoge=hoge;', $cookies[0]);
        $this->assertStringStartsWith('fuga=fuga;', $cookies[1]);
    }

    function test_setDisposition()
    {
        $response = new Response();

        $response->setDisposition('filename.txt');
        $this->assertEquals('attachment; filename=filename.txt', $response->headers->get('Content-Disposition'));
    }

    function test_setCors()
    {
        $response = new Response();

        $response->setCors([]);
        $this->assertEquals(null, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals(null, $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals(null, $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals(null, $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertEquals(null, $response->headers->get('Access-Control-Allow-Max-Age'));

        $response->setCors([
            'origin'      => ['*'],
            'methods'     => ['GET', 'POST'],
            'headers'     => ['Content-Type'],
            'credentials' => true,
            'max-age'     => 3600,
        ]);
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Allow-Max-Age'));
    }
}
