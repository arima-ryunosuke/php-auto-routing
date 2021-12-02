<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RequestTest extends \ryunosuke\Test\AbstractTestCase
{
    function test___get()
    {
        $request = new Request();

        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->session->set('hoge', 'HOGE');
        $this->assertEquals('HOGE', $request->session->get('hoge'));
        $this->assertEquals('defval', $request->session->get('undefined', 'defval'));

        $this->assertException('hoge is not supported', fn() => $request->hoge);
    }

    function test_alias()
    {
        $request = new Request(['hoge' => 'HOGE'], ['fuga' => 'FUGA']);

        $this->assertEquals('HOGE', $request->get->get('hoge'));

        $request->get->add(['hoge1' => 'HOGE1']);
        $this->assertEquals('HOGE1', $request->get->get('hoge1'));
        $this->assertEquals('HOGE1', $request->query->get('hoge1'));

        $request->query->add(['hoge2' => 'HOGE2']);
        $this->assertEquals('HOGE2', $request->get->get('hoge2'));
        $this->assertEquals('HOGE2', $request->query->get('hoge2'));

        $this->assertEquals('FUGA', $request->post->get('fuga'));

        $request->post->add(['fuga1' => 'FUGA1']);
        $this->assertEquals('FUGA1', $request->post->get('fuga1'));
        $this->assertEquals('FUGA1', $request->request->get('fuga1'));

        $request->request->add(['fuga2' => 'FUGA2']);
        $this->assertEquals('FUGA2', $request->post->get('fuga2'));
        $this->assertEquals('FUGA2', $request->request->get('fuga2'));
    }

    function test_input()
    {
        $request = new Request(['hoge' => 'HOGE'], ['fuga' => 'FUGA'], [], ['piyo' => 'PIYO']);

        $this->assertEquals('HOGE', $request->input('hoge'));
        $this->assertEquals('FUGA', $request->input('fuga'));
        $this->assertEquals('PIYO', $request->input('piyo'));
        $this->assertEquals('defval', $request->input('undefined', 'defval'));
    }

    function test_getUserAgent()
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_user_agent' => 'browser',
        ]);

        $this->assertEquals('browser', $request->getUserAgent());
    }

    function test_getReferer()
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_referer' => 'referrer',
        ]);

        $this->assertEquals('referrer', $request->getReferer());
    }
}
