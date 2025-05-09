<?php
namespace ryunosuke\Test\microute;

use ryunosuke\Test\stub\Controller\DefaultController;
use ryunosuke\Test\stub\Controller\HogeController;
use ryunosuke\Test\stub\Controller\HTMLManagerController;
use ryunosuke\Test\stub\Controller\ResolverController;
use Symfony\Component\HttpFoundation\Request;

class ResolverTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_host()
    {
        $service = $this->provideService(['request' => Request::create('http://hostname/path')]);
        $this->assertEquals('hostname', $service->resolver->host());

        $service = $this->provideService(['request' => Request::create('http://hostname:1080/path')]);
        $this->assertEquals('hostname:1080', $service->resolver->host());

        $service = $this->provideService(['request' => Request::create('https://hostname/path')]);
        $this->assertEquals('hostname', $service->resolver->host());

        $service = $this->provideService(['request' => Request::create('https://hostname:1443/path')]);
        $this->assertEquals('hostname:1443', $service->resolver->host());
    }

    function test_route()
    {
        $service = $this->service;
        $service->router->route('routeName1', ResolverController::class, 'action1');
        $service->router->route('routeName2', ResolverController::class, 'action2');
        $service->router->regex('/a/b/c', ResolverController::class, 'action1');
        $service->router->regex('/(?<name>[a-z0-9]+)/detail/(?<seq>[0-9]+)', ResolverController::class, 'action2');
        $resolver = $service->resolver;

        $url = $resolver->route('routeName1');
        $this->assertEquals('/a/b/c', $url);

        $url = $resolver->route('routeName1', ['op' => 'x']);
        $this->assertEquals('/a/b/c?op=x', $url);

        $url = $resolver->route('routeName2', ['name' => 'test123', 'seq' => 9, 'op' => 'x']);
        $this->assertEquals('/test123/detail/9?op=x', $url);
    }

    function test_url()
    {
        $resuest = Request::create('/dummy');
        $service = $this->provideService(['request' => $resuest]);
        $resolver = $service->resolver;
        $this->assertEquals('/', $resolver->url(DefaultController::class, 'default'));
        $this->assertEquals('/index', $resolver->url(DefaultController::class, 'index'));
        $this->assertEquals('/sub-sub/index', $resolver->url(\ryunosuke\Test\stub\Controller\SubSub\DefaultController::class, 'index'));
        $this->assertEquals('/hoge/', $resolver->url(HogeController::class));
        $this->assertEquals('/hoge/', $resolver->url(HogeController::class, 'default'));
        $this->assertEquals('/hoge/action', $resolver->url(HogeController::class, 'action'));
        $this->assertEquals('/hoge/action-id?id=123&name=hoge', $resolver->url(HogeController::class, 'actionId', ['id' => 123, 'name' => 'hoge']));

        $resuest->attributes->set('parameter', ['id' => 123]);
        $this->assertEquals('/sub-sub/123/index', $resolver->url(\ryunosuke\Test\stub\Controller\SubSub\DefaultController::class, 'index'));

        $service = $this->provideService([
            'request' => new class extends Request {
                public function getBaseUrl(): string { return '/base/path'; }
            },
        ]);
        $resolver = $service->resolver;
        $this->assertEquals('/base/path/hoge/', $resolver->url(HogeController::class));
    }

    function test_url_abbr()
    {
        $service = $this->service;
        $resolver = $service->resolver;
        $this->assertEquals('/html-manager/download-csv-file', $resolver->url(HTMLManagerController::class, 'downloadCSVFile'));
    }

    function test_url_alias()
    {
        $service = $this->provideService([
            'request' => new class extends Request {
                public function getBaseUrl(): string { return '/base/path'; }
            },
        ]);
        $resolver = $service->resolver;
        $this->assertEquals('/base/path/aliaspath/action', $resolver->url(HogeController::class, 'action', [], '/aliaspath'));
        $this->assertEquals('/base/path/hoge/action', $resolver->url(HogeController::class, 'action', [], null));
        $this->assertEquals('/hoge/action', $resolver->url(HogeController::class, 'action', [], ''));
    }

    function test_url_default()
    {
        $service = $this->service;
        $resolver = $service->resolver;
        $this->assertEquals('/', $resolver->url(DefaultController::class, ''));
        $this->assertEquals('/', $resolver->url(DefaultController::class, 'default'));
        $this->assertEquals('/hoge/', $resolver->url(HogeController::class, 'default'));
        $this->assertEquals('/?hoge=1', $resolver->url(DefaultController::class, 'default', ['hoge' => 1]));
    }

    function test_action()
    {
        $service = $this->service;
        $service->dispatcher->dispatch(Request::create('/resolver/action1?id=123'));
        $resolver = $service->resolver;

        $url = $resolver->action('Resolver', 'action1', ['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action1?id=123&op=x', $url);

        $url = $resolver->action('Resolver', 'action1');
        $this->assertEquals('/resolver/action1', $url);

        $url = $resolver->action('action1', ['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action1?id=123&op=x', $url);

        $url = $resolver->action(['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action1?id=123&op=x', $url);

        $url = $resolver->action('action1');
        $this->assertEquals('/resolver/action1', $url);

        $url = $resolver->action();
        $this->assertEquals('/resolver/action1', $url);
    }

    function test_current()
    {
        $service = $this->provideService([
            'request' => Request::create('/resolver/current?id=123&op=x'),
        ]);
        $resolver = $service->resolver;

        $url = $resolver->current(['op' => 'y', 'appendix' => 'hoge']);
        $this->assertEquals('/resolver/current?id=123&op=y&appendix=hoge', $url);

        $url = $resolver->current(['new' => 'z']);
        $this->assertEquals('/resolver/current?id=123&op=x&new=z', $url);

        $url = $resolver->current(['new' => 'z'], []);
        $this->assertEquals('/resolver/current?new=z', $url);
    }

    function test_path()
    {
        $request = Request::create('controller/action');
        $request->server->set('DOCUMENT_ROOT', realpath(__DIR__ . '/../../stub/public'));
        $service = $this->provideService([
            'request' => $request,
        ]);
        $resolver = $service->resolver;

        $style_css_mtime = filemtime(__DIR__ . '/../../stub/public/css/style.css');
        $script_js_sha1 = sha1_file(__DIR__ . '/../../stub/public/js/script.js');

        $url = $resolver->path('http://hostname:80/css/style.css');
        $this->assertEquals("http://hostname:80/css/style.css?v=$style_css_mtime", $url);

        $url = $resolver->path('http://hostname:80/css/style.css', []);
        $this->assertEquals('http://hostname:80/css/style.css', $url);

        $url = $resolver->path('http://hostname:80/css/style.css?key=value');
        $this->assertEquals("http://hostname:80/css/style.css?key=value&v=$style_css_mtime", $url);

        $url = $resolver->path('http://hostname:80/css/style.css?key=value', []);
        $this->assertEquals('http://hostname:80/css/style.css?key=value', $url);

        $url = $resolver->path('http://hostname:80/css/notdound.css?key=value');
        $this->assertEquals('http://hostname:80/css/notdound.css?key=value', $url);

        $url = $resolver->path('http://hostname:80/css/style.css?key=value', 'vv');
        $this->assertEquals("http://hostname:80/css/style.css?key=value&vv=$style_css_mtime", $url);

        $url = $resolver->path('http://hostname:80/css/notdound.css?key=value', []);
        $this->assertEquals('http://hostname:80/css/notdound.css?key=value', $url);

        $url = $resolver->path('http://hostname:80/css/notdound.css', ['v' => 'V', 'a' => ['a' => 'A'], 'n' => null]);
        $this->assertEquals('http://hostname:80/css/notdound.css?v=V&a%5Ba%5D=A', $url);

        $url = $resolver->path('http://hostname:80/css/notdound.css?key=value', ['vv' => fn() => 'v', 'v' => 'V', 'a' => ['a' => 'A'], 'n' => null]);
        $this->assertEquals('http://hostname:80/css/notdound.css?key=value&vv=v&v=V&a%5Ba%5D=A', $url);

        $url = $resolver->path('http://hostname:80/js/script.js?key=dummy.js', ['v' => fn($v) => sha1_file($v), 'a' => ['a' => 'A'], 'n' => null]);
        $this->assertEquals("http://hostname:80/js/script.js?key=dummy.js&v=$script_js_sha1&a%5Ba%5D=A", $url);

        $url = $resolver->path();
        $this->assertEquals('/', $url);

        $ref = new \ReflectionProperty($request, 'basePath');
        $ref->setAccessible(true);
        $ref->setValue($request, '/foobar');
        $resolver = $service->resolver;

        $url = $resolver->path();
        $this->assertEquals('/foobar/', $url);

        $url = $resolver->path('css');
        $this->assertEquals('/foobar/controller/css', $url);

        $url = $resolver->path('/css');
        $this->assertEquals('/foobar/css', $url);

        $url = $resolver->path('/css/notfound.css');
        $this->assertEquals('/foobar/css/notfound.css', $url);

        $url = $resolver->path('//css/style.css', 'version');
        $this->assertEquals("/css/style.css?version=$style_css_mtime", $url);

        $url = $resolver->path('//css/style.css');
        $this->assertEquals("/css/style.css?v=$style_css_mtime", $url);

        $url = $resolver->path('//css/style.css?a=b', []);
        $this->assertEquals("/css/style.css?a=b", $url);

        $url = $resolver->path('//css/style.css?a=b', ['v' => 'V', 'a' => ['a' => 'A'], 'n' => null]);
        $this->assertEquals("/css/style.css?a=b&v=V&a%5Ba%5D=A", $url);

        $url = $resolver->path('//js/script.js?a=b', ['v' => fn($v) => sha1_file($v), 'a' => ['a' => 'A'], 'n' => null]);
        $this->assertEquals("/js/script.js?a=b&v=$script_js_sha1&a%5Ba%5D=A", $url);

        // basePath が空文字の挙動が怪しいので追加テスト
        $ref->setValue($request, '');
        $resolver = $service->resolver;

        $url = $resolver->path();
        $this->assertEquals('/', $url);

        $url = $resolver->path('css');
        $this->assertEquals('/controller/css', $url);

        $url = $resolver->path('/css');
        $this->assertEquals('/css', $url);

        $url = $resolver->path('/css/notfound.css');
        $this->assertEquals('/css/notfound.css', $url);

        $url = $resolver->path('/css/style.css', 'version');
        $this->assertEquals("/css/style.css?version=$style_css_mtime", $url);
    }

    function test_query()
    {
        $service = $this->service;
        $resolver = $service->resolver;
        $current = [
            'flat' => 123,
            'list' => [1, 2, 3],
            'hash' => [
                'a' => 1,
                'b' => 2,
                'c' => 3,
            ],
            'nest' => [
                'x' => [
                    'y' => [
                        'z' => 'xyz',
                    ],
                ],
            ],
            'recu' => [[['z']]],
        ];
        $null = [
            'flat' => null,
            'list' => null,
            'hash' => null,
            'nest' => null,
            'recu' => null,
        ];
        $this->assertEquals('', $resolver->query([], []));
        $this->assertEquals('', $resolver->query($null, []));
        $this->assertEquals('?new=789', $resolver->query([
            'new' => 789,
        ], []));
        $this->assertEquals('?new=789', $resolver->query([
                'new' => 789,
            ] + $null, $current));
        $this->assertEquals('?list%5B%5D=1&list%5B%5D=2&list%5B%5D=3&list%5B%5D=4&new=1', $resolver->query([
                'new'  => function ($current) {
                    return intval(is_null($current));
                },
                'list' => function ($current) {
                    $current[] = 4;
                    return $current;
                },
            ] + $null, $current));
        $this->assertEquals('?list%5B%5D=123', $resolver->query([
                'list' => [123],
            ] + $null, $current));
        $this->assertEquals('?hash%5Ba%5D=1&hash%5Bb%5D=7&hash%5Bd%5D=10', $resolver->query([
                'hash' => [
                    'b' => 7,
                    'c' => null,
                    'd' => 10,
                ],
            ] + $null, $current));
        $this->assertEquals('?nest%5Bx%5D%5By%5D%5Bz%5D=XYZ&nest%5Bx%5D%5By%5D%5BZ%5D%5B%5D=z&nest%5Bx%5D%5BY%5D%5B%5D=y&nest%5BX%5D%5B%5D=x', $resolver->query([
                'nest' => [
                    'x' => [
                        'y' => [
                            'z' => 'XYZ',
                            'Z' => ['z'],
                        ],
                        'Y' => ['y'],
                    ],
                    'X' => ['x'],
                ],
            ] + $null, $current));
        $this->assertEquals('?flat=123&list%5B%5D=1&list%5B%5D=2&list%5B%5D=3&hash%5Ba%5D=1&hash%5Bb%5D=2&hash%5Bc%5D=3&nest%5Bx%5D%5By%5D%5Bz%5D=xyz&recu%5B0%5D%5B0%5D%5B%5D=z', $resolver->query([], $current));
    }

    function test_data()
    {
        $service = $this->service;
        $resolver = $service->resolver;

        $url = $resolver->data(__FILE__, 'text/plain', 'utf8');
        $this->assertStringStartsWith('data:text/plain;charset=utf8,%', $url);

        $url = $resolver->data(__FILE__);
        $this->assertStringStartsWith('data:text/x-php;charset=UTF-8,%', $url);

        $url = $resolver->data(__DIR__ . '/../../stub/public/img/image.png');
        $this->assertStringStartsWith('data:image/png;base64,i', $url);

        $this->assertException(new \InvalidArgumentException('is not exists'), function () use ($resolver) {
            $resolver->data('notfound');
        });
        $this->assertException(new \InvalidArgumentException('is not supported'), function () use ($resolver) {
            $resolver->data(__FILE__, null, null, 'hoge');
        });
    }
}
