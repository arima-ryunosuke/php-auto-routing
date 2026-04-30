<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RequestTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_duplicate()
    {
        $request1 = new Request();
        $request1->query->set('dummy', 'DUMMY1');
        $request1->get->set('dummy', 'DUMMY1');
        $request2 = $request1->duplicate();

        $this->assertEquals('DUMMY1', $request2->query->get('dummy'));
        $this->assertEquals('DUMMY1', $request2->get->get('dummy'));

        $request1->query->set('dummy', 'DUMMY2');
        $request1->get->set('dummy', 'DUMMY2');

        $this->assertEquals('DUMMY1', $request2->query->get('dummy'));
        $this->assertEquals('DUMMY1', $request2->get->get('dummy'));
    }

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

    function test_require()
    {
        $request = new Request([
            'bool'         => false,
            'bool-string'  => 'on',
            'int'          => 123,
            'int-string'   => '123',
            'float'        => 3.14,
            'float-string' => '3.14',
            'string'       => 'hoge',
            'array'        => [1, 2, 3],
        ], [
            'post-only' => 'posted',
        ]);

        $this->assertSame('', $request->require('bool'));
        $this->assertSame(false, $request->require('bool', FILTER_VALIDATE_BOOL));
        $this->assertSame('on', $request->require('bool-string'));
        $this->assertSame(true, $request->require('bool-string', FILTER_VALIDATE_BOOL));

        $this->assertSame('123', $request->require('int'));
        $this->assertSame(123, $request->require('int', FILTER_VALIDATE_INT));
        $this->assertSame('123', $request->require('int-string'));
        $this->assertSame(123, $request->require('int-string', FILTER_VALIDATE_INT));

        $this->assertSame('3.14', $request->require('float'));
        $this->assertSame(3.14, $request->require('float', FILTER_VALIDATE_FLOAT));
        $this->assertSame('3.14', $request->require('float-string'));
        $this->assertSame(3.14, $request->require('float-string', FILTER_VALIDATE_FLOAT));

        $this->assertSame('hoge', $request->require('string'));
        $this->assertSame(['1', '2', '3'], $request->require('array', FILTER_DEFAULT, flags: FILTER_REQUIRE_ARRAY));
        $this->assertSame([1, 2, 3], $request->require('array', FILTER_VALIDATE_INT, flags: FILTER_REQUIRE_ARRAY));

        $this->assertSame('posted', $request->require('post-only', bags: $request->post));
        $this->assertException('is missing', fn() => $request->require('post-only', bags: $request->get));

        $this->assertException('is missing', fn() => $request->require('undefined'));
        $this->assertException('is missing', fn() => $request->require('string', FILTER_VALIDATE_BOOL));
        $this->assertException('is missing', fn() => $request->require('string', FILTER_VALIDATE_INT));
        $this->assertException('is missing', fn() => $request->require('string', FILTER_VALIDATE_FLOAT));
        $this->assertException('is missing', fn() => $request->require('array'));

        $this->assertException('is missing', fn() => $request->require('int', FILTER_VALIDATE_INT, min_range: 200));
        $this->assertException('is missing', fn() => $request->require('float', FILTER_VALIDATE_FLOAT, max_range: 3));
        $this->assertException('is missing', fn() => $request->require('float-string', FILTER_VALIDATE_FLOAT, decimal: ','));
        $this->assertException('is missing', fn() => $request->require('string', FILTER_VALIDATE_REGEXP, regexp: '#\d+#'));
    }

    function test_any()
    {
        $request = new Request(['hoge' => 'HOGE', 'fuga' => 'FUGA', 'array' => [1, 2, 3]]);

        $this->assertEquals('HOGE', $request->any('unknown', 'hoge', 'fuga'));
        $this->assertEquals('FUGA', $request->any('unknown', 'fuga', 'hoge'));
        $this->assertEquals([1, 2, 3], $request->any('unknown', 'array', 'hoge'));
        $this->assertEquals(null, $request->any('unknown', 'unknown1', 'unknown2'));
    }

    function test_only_except()
    {
        $request = new Request(
            ['hoge' => 'get-HOGE', 'fuga' => 'get-FUGA', 'piyo' => 'get-PIYO', 'array' => [1, 2, 3]],
            ['hoge' => 'post-HOGE', 'fuga' => 'post-FUGA', 'piyo' => 'post-PIYO', 'array' => [4, 5, 6]],
            [], [], [],
            ['REQUEST_METHOD' => 'GET'],
        );

        $this->assertSame([
            'piyo'  => 'get-PIYO',
            'array' => [1, 2, 3],
        ], $request->only('piyo', 'array', 'unknown'));

        $this->assertSame([
            'hoge'  => 'get-HOGE',
            'array' => [1, 2, 3],
        ], $request->except('piyo', 'fuga', 'unknown'));

        $request = new Request(
            ['hoge' => 'get-HOGE', 'fuga' => 'get-FUGA', 'piyo' => 'get-PIYO', 'array' => [1, 2, 3]],
            ['hoge' => 'post-HOGE', 'fuga' => 'post-FUGA', 'piyo' => 'post-PIYO', 'array' => [4, 5, 6]],
            [], [], [],
            ['REQUEST_METHOD' => 'POST'],
        );

        $this->assertSame([
            'piyo'  => 'post-PIYO',
            'array' => [4, 5, 6],
        ], $request->only('piyo', 'array', 'unknown'));

        $this->assertSame([
            'hoge'  => 'post-HOGE',
            'array' => [4, 5, 6],
        ], $request->except('piyo', 'fuga', 'unknown'));
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

    function test_isAsynchronous()
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_Sec-Fetch-Dest' => 'empty',
        ]);
        $this->assertEquals(true, $request->isAsynchronous());

        $request = new Request([], [], [], [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        $this->assertEquals(true, $request->isAsynchronous());

        $request = new Request([], [], [], [], [], [
            'HTTP_Sec-Fetch-Dest'   => 'document',
            'HTTP_X-Requested-With' => 'fetch',
        ]);
        $this->assertEquals(false, $request->isAsynchronous());
    }

    function test_getClientHints()
    {
        $request = new Request([], [], [], [
            'client_hints' => json_encode([
                'Content-DPR'                => 2,
                'DPR'                        => 2,
                'Device-Memory'              => 32,
                'Viewport-Width'             => 1920,
                'Width'                      => 2000,
                'Sec-CH-UA'                  => '"Dummy";v="100", "Not(A:Brand";v="10"',
                'Sec-CH-UA-Arch'             => '"x86"',
                'Sec-CH-UA-Full-Version'     => '"123.7.8.9"',
                'Sec-CH-UA-Mobile'           => '?1',
                'Sec-CH-UA-Model'            => '""',
                'Sec-CH-UA-Platform'         => '"Linux"',
                'Sec-CH-UA-Platform-Version' => '"6.8.1"',
            ]),
        ], [], [
            'HTTP_DPR'                        => '1',
            'HTTP_DEVICE_MEMORY'              => '0.5',
            'HTTP_SEC_CH_UA_MODEL'            => '"Machine"',
            'HTTP_SEC_CH_UA_PLATFORM_VERSION' => '"10.0.0"',
            'HTTP_SEC_CH_UA_PLATFORM'         => '"Windows"',
            'HTTP_SEC_CH_UA_ARCH'             => '"x86"',
            'HTTP_SEC_CH_UA_FULL_VERSION'     => '"100.1.2.3"',
            'HTTP_SEC_CH_UA_MOBILE'           => '?0',
            'HTTP_SEC_CH_UA'                  => '"Chromium";v="100", "Not(A:Brand";v="10", "Google Chrome";v="100"',
            'HTTP_VIEWPORT_WIDTH'             => '1280',
        ]);

        $this->assertEquals([
            'Content-DPR'                => 2.0,
            'DPR'                        => 1.0,
            'Device-Memory'              => 0.5,
            'Viewport-Width'             => 1280,
            'Width'                      => 2000,
            'Sec-CH-UA'                  => [
                'Chromium'      => ['v' => '100'],
                'Not(A:Brand'   => ['v' => '10'],
                'Google Chrome' => ['v' => '100'],
            ],
            'Sec-CH-UA-Arch'             => 'x86',
            'Sec-CH-UA-Full-Version'     => '100.1.2.3',
            'Sec-CH-UA-Mobile'           => false,
            'Sec-CH-UA-Model'            => 'Machine',
            'Sec-CH-UA-Platform'         => 'Windows',
            'Sec-CH-UA-Platform-Version' => '10.0.0',
        ], $request->getClientHints());

        $this->assertEquals([
            'Content-DPR'                => null,
            'DPR'                        => '1',
            'Device-Memory'              => '0.5',
            'Viewport-Width'             => '1280',
            'Width'                      => null,
            'Sec-CH-UA'                  => '"Chromium";v="100", "Not(A:Brand";v="10", "Google Chrome";v="100"',
            'Sec-CH-UA-Arch'             => '"x86"',
            'Sec-CH-UA-Full-Version'     => '"100.1.2.3"',
            'Sec-CH-UA-Mobile'           => '?0',
            'Sec-CH-UA-Model'            => '"Machine"',
            'Sec-CH-UA-Platform'         => '"Windows"',
            'Sec-CH-UA-Platform-Version' => '"10.0.0"',
        ], $request->getClientHints(true, ''));

        $request->headers->replace([]);
        $this->assertEquals([
            'Content-DPR'                => 2.0,
            'DPR'                        => 2.0,
            'Device-Memory'              => 32.0,
            'Viewport-Width'             => 1920,
            'Width'                      => 2000,
            'Sec-CH-UA'                  => [
                'Dummy'       => ['v' => '100'],
                'Not(A:Brand' => ['v' => '10'],
            ],
            'Sec-CH-UA-Arch'             => 'x86',
            'Sec-CH-UA-Full-Version'     => '123.7.8.9',
            'Sec-CH-UA-Mobile'           => true,
            'Sec-CH-UA-Model'            => '',
            'Sec-CH-UA-Platform'         => 'Linux',
            'Sec-CH-UA-Platform-Version' => '6.8.1',
        ], $request->getClientHints(false, 'client_hints'));
    }
}
