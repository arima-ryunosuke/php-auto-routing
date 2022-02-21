<?php
namespace ryunosuke\Test\microute;

use ryunosuke\microute\Router;
use ryunosuke\Test\stub\Controller\HogeController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class RouterTest extends \ryunosuke\Test\AbstractTestCase
{
    function test___construct()
    {
        $service = $this->provideService();
        $service->cacher->clear();
        $this->assertFalse($service->cacher->has(Router::CACHE_KEY . '.routings'));
        new Router($service);
        $this->assertTrue($service->cacher->has(Router::CACHE_KEY . '.routings'));
    }

    function test_parse()
    {
        $service = $this->provideService([
            'parameterDelimiter' => '$',
            'parameterSeparator' => ';',
        ]);

        // 普通のパース
        $parsed = $service->router->parse('/hoge/fuga/piyo');
        $this->assertEquals('Hoge\\Fuga', $parsed['controller']);
        $this->assertEquals('piyo', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals([], $parsed['parameters']);

        // ハイフン区切り（camelCase に変換されるはず）
        $parsed = $service->router->parse('/ho-ge/fu-ga/pi-yo');
        $this->assertEquals('HoGe\\FuGa', $parsed['controller']);
        $this->assertEquals('piYo', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals([], $parsed['parameters']);

        // パラメータ付き
        $parsed = $service->router->parse('/hoge/fuga/piyo$a;b');
        $this->assertEquals('Hoge\\Fuga', $parsed['controller']);
        $this->assertEquals('piyo', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（コンテキスト有り）
        $parsed = $service->router->parse('/hoge/fuga/piyo$a;b.json');
        $this->assertEquals('Hoge\\Fuga', $parsed['controller']);
        $this->assertEquals('piyo', $parsed['action']);
        $this->assertEquals('json', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);
    }

    function test_parse_slash()
    {
        $service = $this->provideService([
            'parameterDelimiter' => '/',
            'parameterSeparator' => ';',
        ]);

        // パラメータ付き（本当に 404）
        $parsed = $service->router->parse('/hoge/fuga/piyo/a;b');
        $this->assertEquals('Hoge\\Fuga', $parsed['controller']);
        $this->assertEquals('piyo', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（アクションメソッド有り）
        $parsed = $service->router->parse('/hoge/query-context/a;b');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（コンテキスト有り）
        $parsed = $service->router->parse('/hoge/query-context/a;b.json');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('json', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);
    }

    function test_parse_slash_slash()
    {
        $service = $this->provideService([
            'parameterDelimiter' => '/',
            'parameterSeparator' => '/',
        ]);

        // パラメータ付き（本当に 404）
        $parsed = $service->router->parse('/hoge/fuga/piyo/a/b');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('default', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['fuga', 'piyo', 'a', 'b'], $parsed['parameters']);

        // パラメータ付き（デフォルトアクション）
        $parsed = $service->router->parse('/a/b');
        $this->assertEquals('Default', $parsed['controller']);
        $this->assertEquals('default', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（アクションメソッド有り）
        $parsed = $service->router->parse('/hoge/query-context/a/b');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（コンテキスト有り）
        $parsed = $service->router->parse('/hoge/query-context/a/b.json');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('json', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);
    }

    function test_parse_query()
    {
        $service = $this->provideService([
            'parameterDelimiter' => '?',
            'parameterSeparator' => '&',
        ]);

        // パラメータ付き
        $parsed = $service->router->parse('/hoge/query-context', 'a&b');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);

        // パラメータ付き（コンテキスト有り）
        $parsed = $service->router->parse('/hoge/query-context.json', 'a&b');
        $this->assertEquals('Hoge', $parsed['controller']);
        $this->assertEquals('queryContext', $parsed['action']);
        $this->assertEquals('json', $parsed['context']);
        $this->assertEquals(['a', 'b'], $parsed['parameters']);
    }

    function test_route()
    {
        $service = $this->service;
        $service->router->route('route', HogeController::class, 'default');
        $this->assertEquals('/hoge/', $service->router->reverseRoute('route'));
        $this->assertEquals('/hoge/', $service->router->reverseRoute('route')); // for cache coverage
    }

    function test_rewrite()
    {
        $service = $this->service;

        $service->router->rewrite('/namespace', $service->resolver->url(HogeController::class, 'action-simple'));
        $route = $service->router->match(Request::create('/namespace'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => '',
            'parameters' => [],
            'route'      => 'rewrite',
        ], $route);

        $service->router->rewrite('/namespace', HogeController::class, 'action-simple');
        $route = $service->router->match(Request::create('/namespace'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => '',
            'parameters' => [],
            'route'      => 'rewrite',
        ], $route);
    }

    function test_redirect()
    {
        $service = $this->service;

        $service->router->redirect('/url', '/direct', 301);
        $route = $service->router->match(Request::create('/url'));
        $this->assertTrue($route->isRedirect('/direct'));
        $this->assertEquals(301, $route->getStatusCode());

        $service->router->redirect('/url302', '/direct', 'hoge');
        $route = $service->router->match(Request::create('/url302'));
        $this->assertTrue($route->isRedirect('/direct'));
        $this->assertEquals(302, $route->getStatusCode());

        $service->router->redirect('/param', HogeController::class, 302, 'actionId');
        $route = $service->router->match(Request::create('/param?123'));
        $this->assertTrue($route->isRedirect('/hoge/action-id?123'));
        $this->assertEquals(302, $route->getStatusCode());

        $service->router->redirect('/context.json', HogeController::class, 302, 'action_context');
        $route = $service->router->match(Request::create('/context.json'));
        $this->assertTrue($route->isRedirect('/hoge/action_context.json'));
        $this->assertEquals(302, $route->getStatusCode());

        $service->router->redirect('/queryContext.json', HogeController::class, 303, 'queryContext');
        $route = $service->router->match(Request::create('/queryContext.json?123'));
        $this->assertTrue($route->isRedirect('/hoge/query-context.json?arg=123'));
        $this->assertEquals(303, $route->getStatusCode());

        $service->router->redirect('/external', 'http://example.com');
        $route = $service->router->match(Request::create('/external'));
        $this->assertTrue($route->isRedirect('http://example.com'));
        $this->assertEquals(302, $route->getStatusCode());
    }

    function test_alias()
    {
        $service = $this->service;

        $service->router->alias('/namespace', HogeController::class);
        $route = $service->router->match(Request::create('/namespace'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'default',
            'context'    => '',
            'parameters' => [],
            'route'      => 'alias',
        ], $route);
        $route = $service->router->match(Request::create('/namespace/action-simple'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => '',
            'parameters' => [],
            'route'      => 'alias',
        ], $route);
    }

    function test_alias_context()
    {
        $service = $this->service;

        $service->router->alias('/namespace', HogeController::class);
        $route = $service->router->match(Request::create('/namespace.txt'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'default',
            'context'    => 'txt',
            'parameters' => [],
            'route'      => 'alias',
        ], $route);
        $route = $service->router->match(Request::create('/namespace/action-simple.txt'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => 'txt',
            'parameters' => [],
            'route'      => 'alias',
        ], $route);
    }

    function test_scope()
    {
        $service = $this->service;

        $service->router->scope('/(?<hoge_id>[0-9a-z]+)/', HogeController::class);

        $route = $service->router->match(Request::create('/id13/'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'default',
            'context'    => '',
            'parameters' => [
                'hoge_id' => 'id13',
                0         => 'id13',
            ],
            'route'      => 'scope',
        ], $route);

        $route = $service->router->match(Request::create('/id14/scope'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'scope',
            'context'    => '',
            'parameters' => [
                'hoge_id' => 'id14',
                0         => 'id14',
            ],
            'route'      => 'scope',
        ], $route);

        $route = $service->router->match(Request::create('/id15/scope.json'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'scope',
            'context'    => 'json',
            'parameters' => [
                'hoge_id' => 'id15',
                0         => 'id15',
            ],
            'route'      => 'scope',
        ], $route);
    }

    function test_regex()
    {
        $service = $this->service;

        $service->router->regex('/(?<name>[a-z0-9]+)/detail/(?<seq>[0-9]+)', HogeController::class, 'actionSimple');
        $route = $service->router->match(Request::create('/hoge/detail/123'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => '',
            'parameters' => [
                0      => 'hoge',
                'name' => 'hoge',
                1      => '123',
                'seq'  => '123',
            ],
            'route'      => 'regex',
        ], $route);
    }

    function test_regex_relative()
    {
        $service = $this->service;

        $service->router->regex('detail/(?<seq>[0-9]+)', HogeController::class, 'actionSimple');
        $route = $service->router->match(Request::create('/hoge/detail/123'));
        $this->assertEquals([
            'controller' => 'Hoge',
            'action'     => 'actionSimple',
            'context'    => '',
            'parameters' => [
                0     => '123',
                'seq' => '123',
            ],
            'route'      => 'regex',
        ], $route);
    }

    function test_priority()
    {
        $service = $this->service;
        $service->router->rewrite('/hoge/action-simple', '/rewrite');
        $service->router->redirect('/hoge/action-simple', '/redirect');
        $service->router->alias('/hoge', 'alias');
        $service->router->regex('/hoge/action-simple', HogeController::class, 'default');

        $priority = function ($priority) use ($service) {
            $frozen = new \ReflectionProperty($service, 'frozen');
            $frozen->setAccessible(true);
            $current = $frozen->getValue($service);
            $current['priority'] = $priority;
            $frozen->setValue($service, $current);
        };

        $priority(['rewrite', 'redirect', 'alias', 'regex', 'default']);
        $route = $service->router->match(Request::create('/hoge/action-simple'));
        $this->assertEquals([
            'controller' => "",
            'action'     => "rewrite",
            'route'      => "rewrite",
            'context'    => '',
            'parameters' => [],
        ], $route);

        $priority(['redirect', 'alias', 'regex', 'default', 'rewrite']);
        $route = $service->router->match(Request::create('/hoge/action-simple'));
        $this->assertInstanceOf(RedirectResponse::class, $route);

        $priority(['alias', 'regex', 'default', 'rewrite', 'redirect']);
        $route = $service->router->match(Request::create('/hoge/action-simple'));
        $this->assertEquals([
            'controller' => "alias",
            'action'     => "actionSimple",
            'route'      => "alias",
            'context'    => '',
            'parameters' => [],
        ], $route);

        $priority(['regex', 'default', 'rewrite', 'redirect', 'alias']);
        $route = $service->router->match(Request::create('/hoge/action-simple'));
        $this->assertEquals([
            'controller' => "Hoge",
            'action'     => "default",
            'route'      => "regex",
            'context'    => '',
            'parameters' => [],
        ], $route);

        $priority(['default', 'rewrite', 'redirect', 'alias', 'regex']);
        $route = $service->router->match(Request::create('/hoge/action-simple'));
        $this->assertEquals([
            'controller' => "Hoge",
            'action'     => "actionSimple",
            'route'      => "default",
            'context'    => '',
            'parameters' => [],
        ], $route);

        $priority(['undefined']);
        $this->assertException('is not defined route method', function () use ($service) {
            $service->router->match(Request::create('/'));
        });
        $this->assertException('is not defined route method', function () use ($service) {
            $service->router->reverseRoute('Hoge::fuga');
        });
    }

    function test_currentRoute()
    {
        $service = $this->service;

        $service->dispatcher->dispatch(Request::create('/hoge/default'));
        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\HogeController::default', $service->router->currentRoute());

        $service->router->regex('detail/(?<seq>[0-9]+)', HogeController::class, 'actionSimple');
        $service->dispatcher->dispatch(Request::create('/hoge/detail/123'));
        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\HogeController::actionSimple', $service->router->currentRoute());
    }

    function test_reverseRoute()
    {
        $service = $this->service;
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->regex('/(?<name>[a-z0-9]+)/detail/(?<seq>[0-9]+)', HogeController::class, 'default');
        $url = $service->router->reverseRoute('routeName', ['name' => 'hoge', 'seq' => 789]);
        $this->assertEquals('/hoge/detail/789', $url);
    }

    function test_reverseRoute_scope()
    {
        $service = $this->provideService();
        $service->cacher->clear();
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->scope('(?<pref>[a-z]+)/(?<city>[a-z]+)/', HogeController::class);
        $url = $service->router->reverseRoute('routeName', ['pref' => 'tokyo', 'city' => 'chiyoda', 'dummy' => 0]);
        $this->assertEquals('/hoge/tokyo/chiyoda/?dummy=0', $url);

        $service = $this->provideService();
        $service->cacher->clear();
        $service->router->route('routeName', HogeController::class, 'actionSimple');
        $service->router->scope('/(?<pref>[a-z]+)/(?<city>[a-z]+)/', HogeController::class);
        $url = $service->router->reverseRoute('routeName', ['pref' => 'tokyo', 'city' => 'chiyoda', 'dummy' => 0]);
        $this->assertEquals('/tokyo/chiyoda/action-simple?dummy=0', $url);
    }

    function test_reverseRoute_name_and_index()
    {
        $service = $this->service;
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->regex('/hoge/fuga/(?<piyo>(a|b)\\\\[a-z]+(\\d))-(\d+)', HogeController::class, 'default');
        $url = $service->router->reverseRoute('routeName', ['piyo' => 'a\\foo1', 'dummy' => 0]);
        $this->assertEquals('/hoge/fuga/a%5Cfoo1-0', $url);
    }

    function test_reverseRoute_encode()
    {
        $service = $this->service;
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->regex('/(?<name>.+)', HogeController::class, 'default');
        $url = $service->router->reverseRoute('routeName', ['name' => '#?/']);
        $this->assertEquals('/%23%3F/', $url);
    }

    function test_reverseRoute_misc()
    {
        $service = $this->service;
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->regex('/hoge/fuga/(?<piyo>(a|b)\\\\[a-z]+(\\d))', HogeController::class, 'default');
        $url = $service->router->reverseRoute('routeName', ['piyo' => 'a\\foo1', 'dummy' => 0]);
        $this->assertEquals('/hoge/fuga/a%5Cfoo1?dummy=0', $url);
    }

    function test_reverseRoute_default()
    {
        $service = $this->service;
        $url = $service->router->reverseRoute('Dispatch::main', ['arg' => 'arg1']);
        $this->assertEquals('/dispatch/main?arg=arg1', $url);
    }

    function test_reverseRoute_none()
    {
        $service = $this->service;
        $expected = new \UnexpectedValueException("route name 'hoge' is not defined.");
        $this->assertException($expected, function () use ($service) {
            $service->router->reverseRoute('hoge', []);
        });
    }

    function test_reverseRoute_relative()
    {
        $request = Request::createFromGlobals();
        $ref = new \ReflectionProperty($request, 'basePath');
        $ref->setAccessible(true);
        $ref->setValue($request, '/basepath');
        $service = $this->provideService([
            'request' => $request,
        ]);
        $service->router->route('routeName', HogeController::class, 'default');
        $service->router->regex('(?<name>.+)', HogeController::class, 'default');
        $url = $service->router->reverseRoute('routeName', ['name' => '#?/']);
        $this->assertEquals('/basepath/hoge/%23%3F/', $url);
    }

    function test_urls()
    {
        $request = Request::createFromGlobals();
        $ref = new \ReflectionProperty($request, 'basePath');
        $ref->setAccessible(true);
        $ref->setValue($request, '/basepath');
        $service = $this->provideService([
            'debug'              => true,
            'request'            => $request,
            'parameterDelimiter' => '?',
            'parameterSeparator' => '&',
        ]);
        $service->router->redirect('/hoge/notfound/old', '/hoge/notfound');
        $service->router->redirect('/notfound/action/old', '/notfound/action');
        $urls = $service->router->urls();

        $expects = [
            // URL リダイレクトルーティング
            '/basepath/hoge/notfound/old'                                             => [
                'route'  => Router::ROUTE_REDIRECT,
                'name'   => null,
                'target' => '/hoge/notfound',
                'method' => [],
            ],
            '/basepath/notfound/action/old'                                           => [
                'route'  => Router::ROUTE_REDIRECT,
                'name'   => null,
                'target' => '/notfound/action',
                'method' => [],
            ],
            // デフォルトルーティング
            '/basepath/url/all/default-on'                                            => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOn',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOnAction',
                'method' => [],
            ],
            '/basepath/url/all/parameter?arg1=string&arg2=array'                      => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameter',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameterAction',
                'method' => [],
            ],
            '/basepath/url/all/queryable?$arg1@integer&$arg2@array'                   => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::queryable',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::queryableAction',
                'method' => [],
            ],
            '/basepath/url/all/post'                                                  => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::post',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::postAction',
                'method' => ['POST'],
            ],
            '/basepath/url/all/redirect'                                              => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirect',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirectAction',
                'method' => [],
            ],
            '/basepath/url/all/regex'                                                 => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regex',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regexAction',
                'method' => [],
            ],
            '/basepath/url/all/rewrite'                                               => [
                'route'  => 'default',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewrite',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewriteAction',
                'method' => [],
            ],
            '/basepath/url/all/routename'                                             => [
                'route'  => 'default',
                'name'   => 'mappingRoute',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::routenameAction',
                'method' => [],
            ],
            // リダイレクト
            '/basepath/mapping/redirect1'                                             => [
                'route'  => 'redirect',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirect',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirectAction',
                'method' => [],
            ],
            '/basepath/mapping/redirect2'                                             => [
                'route'  => 'redirect',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirect',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirectAction',
                'method' => [],
            ],
            // 正規表現
            '/basepath/mapping/regex1'                                                => [
                'route'  => 'regex',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regex',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regexAction',
                'method' => [],
            ],
            '/basepath/mapping/regex2'                                                => [
                'route'  => 'regex',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regex',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regexAction',
                'method' => [],
            ],
            '/basepath/mapping/route'                                                 => [
                'route'  => 'regex',
                'name'   => 'mappingRoute',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::routenameAction',
                'method' => [],
            ],
            // リライト
            '/basepath/mapping/rewrite1'                                              => [
                'route'  => 'rewrite',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewrite',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewriteAction',
                'method' => [],
            ],
            '/basepath/mapping/rewrite2'                                              => [
                'route'  => 'rewrite',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewrite',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewriteAction',
                'method' => [],
            ],
            // エイリアス
            '/basepath/relay/default-off'                                             => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOff',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOffAction',
                'method' => [],
            ],
            '/basepath/relay/default-on'                                              => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOn',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::defaultOnAction',
                'method' => [],
            ],
            '/basepath/relay/parameter?arg1=string&arg2=array'                        => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameter',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameterAction',
                'method' => [],
            ],
            '/basepath/relay/queryable?$arg1@integer&$arg2@array'                     => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::queryable',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::queryableAction',
                'method' => [],
            ],
            '/basepath/relay/post'                                                    => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::post',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::postAction',
                'method' => ['POST',],
            ],
            '/basepath/relay/redirect'                                                => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirect',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::redirectAction',
                'method' => [],
            ],
            '/basepath/relay/regex'                                                   => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regex',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::regexAction',
                'method' => [],
            ],
            '/basepath/relay/rewrite'                                                 => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewrite',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::rewriteAction',
                'method' => [],
            ],
            '/basepath/relay/routename'                                               => [
                'route'  => 'alias',
                'name'   => 'mappingRoute',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::routenameAction',
                'method' => [],
            ],
            '/basepath/relay/context.json?id=integer(123)'                            => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::context',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::contextAction',
                'method' => [],
            ],
            '/basepath/relay/context.xml?id=integer(123)'                             => [
                'route'  => 'alias',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::context',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::contextAction',
                'method' => [],
            ],
            // スコープ（抜粋）
            '/basepath/sub-sub/(?<id>[0-9]+)/index'                                   => [
                'route'  => 'scope',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\DefaultController::index',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\DefaultController::indexAction',
                'method' => [],
            ],
            '/basepath/sub-sub/scoped/(?<type>[a-z]+)/'                               => [
                'route'  => 'scope',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\ScopedController::default',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\ScopedController::defaultAction',
                'method' => [],
            ],
            '/basepath/sub-sub/scoped/(?<type>[a-z]+)/hoge'                           => [
                'route'  => 'scope',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\ScopedController::hoge',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\SubSub\\ScopedController::hogeAction',
                'method' => [],
            ],
            '/basepath/url/all/(?<scoped>[0-9a-z]+)/context.json?id=integer(123)'     => [
                'route'  => 'scope',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::context',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::contextAction',
                'method' => [],
            ],
            '/basepath/url/all/(?<scoped>[0-9a-z]+)/parameter?arg1=string&arg2=array' => [
                'route'  => 'scope',
                'name'   => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameter',
                'target' => 'ryunosuke\\Test\\stub\\Controller\\Url\\AllController::parameterAction',
                'method' => [],
            ],
        ];
        foreach ($expects as $url => $expect) {
            $this->assertArrayHasKey($url, $urls, implode("\n", array_keys($urls)));
            $this->assertEquals($expect, $urls[$url], "url is '$url'");
        }
        $this->assertArrayNotHasKey('/url/all/default-off', $urls);
    }

    function test_urls_slash()
    {
        $request = Request::createFromGlobals();
        $ref = new \ReflectionProperty($request, 'basePath');
        $ref->setAccessible(true);
        $ref->setValue($request, '/basepath');
        $service = $this->provideService([
            'debug'              => true,
            'request'            => $request,
            'parameterDelimiter' => '/',
            'parameterSeparator' => '/',
        ]);
        $urls = $service->router->urls();

        $expects = [
            '/basepath/url/all/parameter?arg1=string&arg2=array'    => [],
            '/basepath/url/all/queryable/$arg1@integer/$arg2@array' => [],
            '/basepath/relay/parameter?arg1=string&arg2=array'      => [],
            '/basepath/relay/queryable/$arg1@integer/$arg2@array'   => [],
            '/basepath/relay/context.json?id=integer(123)'          => [],
            '/basepath/relay/context.xml?id=integer(123)'           => [],
        ];
        foreach ($expects as $url => $expect) {
            $this->assertArrayHasKey($url, $urls, implode("\n", array_keys($urls)));
        }
    }
}
