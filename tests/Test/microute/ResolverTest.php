<?php
namespace ryunosuke\Test\microute;

use ryunosuke\Test\stub\Controller\DefaultController;
use ryunosuke\Test\stub\Controller\HogeController;
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
        $service = $this->service;
        $resolver = $service->resolver;
        $this->assertEquals('/', $resolver->url(DefaultController::class, 'default'));
        $this->assertEquals('/index', $resolver->url(DefaultController::class, 'index'));
        $this->assertEquals('/sub-sub/index', $resolver->url(\ryunosuke\Test\stub\Controller\SubSub\DefaultController::class, 'index'));
        $this->assertEquals('/hoge', $resolver->url(HogeController::class));
        $this->assertEquals('/hoge', $resolver->url(HogeController::class, 'default'));
        $this->assertEquals('/hoge/action', $resolver->url(HogeController::class, 'action'));
        $this->assertEquals('/hoge/action-id?123&name=hoge', $resolver->url(HogeController::class, 'actionId', ['id' => 123, 'name' => 'hoge']));

        $service = $this->provideService([
            'request' => new class extends Request
            {
                public function getBaseUrl() { return '/base/path'; }
            }
        ]);
        $resolver = $service->resolver;
        $this->assertEquals('/base/path/hoge', $resolver->url(HogeController::class));
    }

    function test_url_params()
    {
        $service = $this->service;
        $resolver = $service->resolver;
        $this->assertEquals('/hoge/query?12&34', $resolver->url(HogeController::class, 'query', [12, 34]));
        $this->assertEquals('/hoge/query?12&34', $resolver->url(HogeController::class, 'query', ['p2' => 34, 'p1' => 12]));
        $this->assertEquals('/hoge/query?12&34', $resolver->url(HogeController::class, 'query', [999, 999, 'p2' => 34, 'p1' => 12]));
    }

    function test_url_params_query()
    {
        $service = $this->provideService([
            'parameterDelimiter' => '?',
            'parameterSeparator' => '&',
        ]);
        $resolver = $service->resolver;
        $this->assertEquals('/hoge/query', $resolver->url(HogeController::class, 'query', []));
        $this->assertEquals('/hoge/query?a%26b', $resolver->url(HogeController::class, 'query', ['a&b']));
        $this->assertEquals('/hoge/query?12&34', $resolver->url(HogeController::class, 'query', [12, 34]));
        $this->assertEquals('/hoge/query?12&34', $resolver->url(HogeController::class, 'query', ['p2' => 34, 'p1' => 12]));
        $this->assertEquals('/hoge/query?999&34&p3=56&p4=78', $resolver->url(HogeController::class, 'query', [999, 999, 'p2' => 34, 'p3' => 56, 'p4' => 78]));
        $this->assertEquals('/hoge/query-context.json?arg=123', $resolver->url(HogeController::class, 'queryContext.json', [123]));
        $this->assertEquals('/hoge/query-context.json?arg=123', $resolver->url(HogeController::class, 'queryContext.json', ['arg' => 123]));
    }

    function test_url_alias()
    {
        $service = $this->provideService([
            'request' => new class extends Request
            {
                public function getBaseUrl() { return '/base/path'; }
            }
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
        $this->assertEquals('/hoge', $resolver->url(HogeController::class, 'default'));
        $this->assertEquals('/?hoge=1', $resolver->url(DefaultController::class, 'default', ['hoge' => 1]));
    }

    function test_action()
    {
        $service = $this->service;
        $service->dispatcher->dispatch(Request::create('/resolver/action1?123'));
        $resolver = $service->resolver;

        $url = $resolver->action('Resolver', 'action1', ['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action1?id=123&op=x', $url);

        $url = $resolver->action('Resolver', 'action1');
        $this->assertEquals('/resolver/action1', $url);

        $url = $resolver->action('action2', ['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action2?123&op=x', $url);

        $url = $resolver->action(['id' => 123, 'op' => 'x']);
        $this->assertEquals('/resolver/action1?id=123&op=x', $url);

        $url = $resolver->action('action1');
        $this->assertEquals('/resolver/action1', $url);

        $url = $resolver->action();
        $this->assertEquals('/resolver/action1', $url);
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

        $url = $resolver->path('http://hostname:80/css/style.css');
        $this->assertEquals("http://hostname:80/css/style.css?$style_css_mtime", $url);

        $url = $resolver->path('http://hostname:80/css/style.css', false);
        $this->assertEquals('http://hostname:80/css/style.css', $url);

        $url = $resolver->path('http://hostname:80/css/style.css?key=value');
        $this->assertEquals("http://hostname:80/css/style.css?key=value&$style_css_mtime", $url);

        $url = $resolver->path('http://hostname:80/css/style.css?key=value', false);
        $this->assertEquals('http://hostname:80/css/style.css?key=value', $url);

        $url = $resolver->path('http://hostname:80/css/notdound.css?key=value');
        $this->assertEquals('http://hostname:80/css/notdound.css?key=value', $url);

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

        $url = $resolver->path('//css/style.css', false);
        $this->assertEquals('/css/style.css', $url);

        $url = $resolver->path('//css/style.css');
        $this->assertEquals("/css/style.css?$style_css_mtime", $url);
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
