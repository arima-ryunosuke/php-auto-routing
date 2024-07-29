<?php
namespace ryunosuke\Test\microute;

use MockLogger;
use ryunosuke\Test\stub\Controller\DefaultController;
use ryunosuke\Test\stub\Controller\HogeController;
use ryunosuke\Test\stub\Controller\SubSub;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DispatcherTest extends \ryunosuke\Test\AbstractTestCase
{
    function test___get()
    {
        $dispatcher = $this->service->dispatcher;

        $dispatcher->error(new \RuntimeException('handling-ex'), $this->service->request);
        $this->assertInstanceOf(\RuntimeException::class, $dispatcher->lastException);
        $this->assertInstanceOf(DefaultController::class, $dispatcher->dispatchedController);
    }

    function test_dispatch_503()
    {
        $maintenanceFile = sys_get_temp_dir() . '/maintenance.php';
        file_put_contents($maintenanceFile, 'maintenance');

        $service = $this->provideService([
            'request'              => $request = Request::create('/hoge'),
            'maintenanceFile'      => $maintenanceFile,
            'maintenanceAccessKey' => 'allow-maintenance-access',
        ]);
        $response = $service->dispatcher->dispatch($request);
        $this->assertEquals(503, $response->getStatusCode());

        $service = $this->provideService([
            'request'              => $request = Request::create('/hoge/fuga?allow-maintenance-access=1234&a=b'),
            'maintenanceFile'      => $maintenanceFile,
            'maintenanceAccessKey' => 'allow-maintenance-access',
        ]);
        $response = $service->dispatcher->dispatch($request);
        $this->assertTrue($response->isRedirect('/hoge/fuga?a=b'));

        $service = $this->provideService([
            'request'              => $request = Request::create('/hoge', cookies: ['microute-maintenance' => 'OK']),
            'maintenanceFile'      => $maintenanceFile,
            'maintenanceAccessKey' => 'allow-maintenance-access',
        ]);
        $response = $service->dispatcher->dispatch($request);
        $this->assertEquals('default-action', $response->getContent());

    }

    function test_dispatch_default()
    {
        $request = Request::create('/');
        $response = $this->service->dispatcher->dispatch($request);

        $this->assertEquals('Default/default', $response->getContent());
    }

    function test_dispatch_default_action()
    {
        $request = Request::create('/index');
        $response = $this->service->dispatcher->dispatch($request);

        // Index#default がなければ Default#index のはず
        $this->assertEquals('Default/index', $response->getContent());
    }

    function test_dispatch_default1()
    {
        $request = Request::create('/defaults/default1/hoge/fuga/piyo');
        $response = $this->service->dispatcher->dispatch($request);

        // 3つあるときは Hoge\Fuga#piyo のはず
        $this->assertEquals('Defaults/Default1/Hoge/Fuga/piyo', $response->getContent());
    }

    function test_dispatch_default2()
    {
        $request = Request::create('/defaults/default2/hoge/fuga/piyo');
        $response = $this->service->dispatcher->dispatch($request);

        // Hoge\Fuga#piyo がなければ Hoge\Fuga\Piyo#default のはず
        $this->assertEquals('Defaults/Default2/Hoge/Fuga/Piyo/default', $response->getContent());
    }

    function test_dispatch_default3()
    {
        $request = Request::create('/defaults/default3/hoge/fuga/piyo');
        $response = $this->service->dispatcher->dispatch($request);

        // Hoge\Fuga\Piyo#default がなければ Hoge\Fuga\Piyo\Default#default のはず
        $this->assertEquals('Defaults/Default3/Hoge/Fuga/Piyo/Default/default', $response->getContent());
    }

    function test_dispatch_nodefault()
    {
        $service = $this->service;

        $response = $service->dispatcher->dispatch(Request::create('routing/default-on'));
        $this->assertEquals('defaultOn', $response->getContent());

        $response = $service->dispatcher->dispatch(Request::create('routing/custom-on'));
        $this->assertEquals('defaultOff', $response->getContent());

        $this->assertStatusCode(404, function () use ($service) {
            $service->dispatcher->dispatch(Request::create('routing/default-off'));
        });

        $this->assertStatusCode(404, function () use ($service) {
            $service->dispatcher->dispatch(Request::create('routing/defaultdefault'));
        });
    }

    function test_dispatch_scope()
    {
        $response = $this->service->dispatcher->dispatch(Request::create('/sub-sub/99/index'));
        $this->assertEquals('index_action: 99', $response->getContent());

        $response = $this->service->dispatcher->dispatch(Request::create('/sub-sub/scoped/tokyo/'));
        $this->assertEquals('default: tokyo', $response->getContent());

        $response = $this->service->dispatcher->dispatch(Request::create('/sub-sub/scoped/tokyo/hoge'));
        $this->assertEquals('hoge: tokyo', $response->getContent());

        $response = $this->service->dispatcher->dispatch(Request::create('/sub-sub/scoped/tokyo/null'));
        $this->assertEquals('hoge: "tokyo"', $response->getContent());

        $response = $this->service->dispatcher->dispatch(Request::create('/sub-sub/scoped/null'));
        $this->assertEquals('hoge: null', $response->getContent());

        $this->assertStatusCode(404, function () {
            $this->service->dispatcher->dispatch(Request::create('/sub-sub/scoped/hoge'));
        });
    }

    function test_dispatch_regex()
    {
        $service = $this->service;

        // 複数ある場合の優先度は「名前 -> 連番」
        $service->router->regex('/-test1/(?<arg1>[a-z]+)/([a-z]+/)?(?<arg2>[0-9]+)', HogeController::class, 'actionRegex');
        $response = $this->service->dispatcher->dispatch(Request::create('/-test1/hoge/dummy/123'));
        $this->assertEquals('hoge/123', $response->getContent());
        $response = $this->service->dispatcher->dispatch(Request::create('/-test1/hoge/123'));
        $this->assertEquals('hoge/123', $response->getContent());

        // 名前がマッチしなくても連番でマッチ
        $service->router->regex('/-test2/(?<arg1>[a-z]+)/([a-z]+/)?(?<argX>[0-9]+)', HogeController::class, 'actionRegex');
        $response = $this->service->dispatcher->dispatch(Request::create('/-test2/hoge/dummy/123'));
        $this->assertEquals('hoge/dummy/', $response->getContent());

        // 名前さえマッチすれば順番は問わない
        $service->router->regex('/-test3/(?<arg2>[a-z]+)/([a-z]+/)?(?<arg1>[0-9]+)', HogeController::class, 'actionRegex');
        $response = $this->service->dispatcher->dispatch(Request::create('/-test3/hoge/dummy/123'));
        $this->assertEquals('123/hoge', $response->getContent());

        // 極論すると順番が正しければ名前も不要
        $service->router->regex('/-test4/([a-z]+)/([a-z]+/)?([0-9]+)', HogeController::class, 'actionRegex');
        $response = $this->service->dispatcher->dispatch(Request::create('/-test4/hoge/dummy/123'));
        $this->assertEquals('hoge/dummy/', $response->getContent());
    }

    function test_dispatch_response()
    {
        $service = $this->service;
        $service->router->redirect('/from', '/to');

        $route = $service->dispatcher->dispatch(Request::create('/from'));
        $this->assertTrue($route->isRedirect('/to'));

        $route = $service->dispatcher->dispatch(Request::create('/mapping/redirect1'));
        $this->assertTrue($route->isRedirect('/url/all/redirect'));
        $this->assertEquals(301, $route->getStatusCode());

        $route = $service->dispatcher->dispatch(Request::create('/mapping/redirect2'));
        $this->assertTrue($route->isRedirect('/url/all/redirect'));
        $this->assertEquals(302, $route->getStatusCode());

        $route = $service->dispatcher->dispatch(Request::create('/mapping/redirect3'));
        $this->assertTrue($route->isRedirect('/url/all/redirect'));
        $this->assertEquals(302, $route->getStatusCode());
    }

    function test_dispatch_multiple()
    {
        $service = $this->provideService([
            'controllerLocation' => [
                'ryunosuke\\Test\\stub\\Controller\\'  => __DIR__ . '/../../stub/Controller/',
                'ryunosuke\\Test\\stub\\Controller2\\' => __DIR__ . '/../../stub/Controller2/',
            ],
        ]);

        $response = $service->dispatcher->dispatch(Request::create('/'));
        $this->assertEquals('Default/default', $response->getContent());

        $response = $service->dispatcher->dispatch(Request::create('/hoge'));
        $this->assertEquals('hoge2', $response->getContent());
    }

    function test_error()
    {
        $service = $this->provideService([
            'logger' => new MockLogger(function ($level, $message, $context) use (&$logs) {
                if (isset($context['exception'])) {
                    echo $context['exception']->getMessage(), "\n";
                }
            }),
        ]);

        $request = Request::create('hoge/action_throw');
        $response = $service->dispatcher->dispatch($request);
        $this->assertEquals('error', $response->getContent());

        ob_start();
        $service->dispatcher->error(new \Exception('handling-ex'), $request);
        $this->assertEquals("handling-ex\n", ob_get_clean());
        $this->assertEquals(new \Exception('handling-ex'), $service->dispatcher->lastException);

        ob_start();
        $this->assertException(new \Exception('DefaultController throws Exception.'), function () use ($service, $request) {
            $service->dispatcher->error(new \DomainException('unhandling-ex'), $request);
        });
        $this->assertEquals("unhandling-ex\n", ob_get_clean());
    }

    function test_error_http()
    {
        $response = $this->service->dispatcher->error(new HttpException(404, '', null, ['X-Custom' => 123]), new Request());
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('123', $response->headers->get('X-Custom'));
    }

    function test_error_top()
    {
        $service = $this->provideService([
            'controllerLocation' => [
                'ryunosuke\\Test\\stub\\Controller2\\' => __DIR__ . '/../../stub/Controller2/',
            ],
        ]);
        $service->cacher->clear();

        $this->assertException(new \Exception('DefaultController is notfound.'), function () use ($service) {
            $service->dispatcher->error(new \Exception(), $this->service->request);
        });
    }

    function test_error_uncatch()
    {
        $this->assertException(new \Exception('uncatch'), function () {
            $this->service->dispatcher->dispatch(Request::create('dispatch/thrown2'));
        });
    }

    function test_finish_content_type_array()
    {
        $service = $this->provideService([
            'parameterContexts' => [
                'csv' => 'text/csv',
            ],
        ]);

        $ctype_null = ['', 200, ['Content-Type' => null]];
        $ctype_hoge = ['', 200, ['Content-Type' => 'hoge/fuga; piyo']];
        $context_null = [[], [], ['context' => null]];
        $context_csv = [[], [], ['context' => 'csv']];

        // 何もしてなければ null（未設定のはず）
        $response = $service->dispatcher->finish(new Response(...$ctype_null), new Request(...$context_null));
        $this->assertEquals(null, $response->headers->get('Content-Type'));

        // 明示的に指定されていれば当然それが返るはず
        $response = $service->dispatcher->finish(new Response(...$ctype_hoge), new Request(...$context_null));
        $this->assertEquals('hoge/fuga; piyo', $response->headers->get('Content-Type'));

        // 自動で text/csv が設定されるはず
        $response = $service->dispatcher->finish(new Response(...$ctype_null), new Request(...$context_csv));
        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));

        // 明示的に指定されていれば自動設定されずそれが返るはず
        $response = $service->dispatcher->finish(new Response(...$ctype_hoge), new Request(...$context_csv));
        $this->assertEquals('hoge/fuga; piyo', $response->headers->get('Content-Type'));
    }

    function test_finish_content_type_callback()
    {
        $service = $this->provideService([
            'parameterContexts' => function () {
                return function ($context) {
                    if ($context === 'csv') {
                        return 'text/csv';
                    }
                };
            },
        ]);

        $ctype_null = ['', 200, ['Content-Type' => null]];
        $ctype_hoge = ['', 200, ['Content-Type' => 'hoge/fuga; piyo']];
        $context_null = [[], [], ['context' => null]];
        $context_csv = [[], [], ['context' => 'csv']];

        // 何もしてなければ null（未設定のはず）
        $response = $service->dispatcher->finish(new Response(...$ctype_null), new Request(...$context_null));
        $this->assertEquals(null, $response->headers->get('Content-Type'));

        // 明示的に指定されていれば当然それが返るはず
        $response = $service->dispatcher->finish(new Response(...$ctype_hoge), new Request(...$context_null));
        $this->assertEquals('hoge/fuga; piyo', $response->headers->get('Content-Type'));

        // 自動で text/csv が設定されるはず
        $response = $service->dispatcher->finish(new Response(...$ctype_null), new Request(...$context_csv));
        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));

        // 明示的に指定されていれば自動設定されずそれが返るはず
        $response = $service->dispatcher->finish(new Response(...$ctype_hoge), new Request(...$context_csv));
        $this->assertEquals('hoge/fuga; piyo', $response->headers->get('Content-Type'));
    }

    function test_finish_301()
    {
        $response = $this->service->dispatcher->finish(new RedirectResponse('/', 301), new Request());
        $this->assertTrue($response->headers->getCacheControlDirective('no-store'));

        $response = $this->service->dispatcher->finish(new RedirectResponse('/', 302), new Request());
        $this->assertNull($response->headers->getCacheControlDirective('no-store'));
    }

    function test_dispatchedController()
    {
        $service = $this->service;

        $service->handle(Request::create('hoge'));
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->dispatchedController);
        $this->assertEquals('default', $service->dispatcher->dispatchedController->action);

        $service->handle(Request::create('notfound'));
        $this->assertInstanceOf(DefaultController::class, $service->dispatcher->dispatchedController);
        $this->assertEquals('error', $service->dispatcher->dispatchedController->action);
    }

    function test_resolveController()
    {
        $dispatcher = $this->service->dispatcher;
        $this->assertEquals(HogeController::class, $dispatcher->resolveController(new HogeController($this->service, '')));
        $this->assertEquals(HogeController::class, $dispatcher->resolveController(HogeController::class));
        $this->assertEquals(HogeController::class, $dispatcher->resolveController('Hoge'));
        $this->assertEquals(SubSub\FooBarController::class, $dispatcher->resolveController('SubSub/FooBar'));
        $this->assertEquals(SubSub\DefaultController::class, $dispatcher->resolveController('SubSub'));
        $this->assertEquals(DefaultController::class, $dispatcher->resolveController(''));
        $this->assertEquals(null, $dispatcher->resolveController('SubSubSub'));
    }

    function test_shortenController()
    {
        $dispatcher = $this->service->dispatcher;
        $this->assertEquals('Hoge', $dispatcher->shortenController(new HogeController($this->service, '')));
        $this->assertEquals('Hoge', $dispatcher->shortenController(HogeController::class));
        $this->assertEquals('SubSub\\FooBar', $dispatcher->shortenController(SubSub\FooBarController::class));
        $this->assertEquals('SubSub\Default', $dispatcher->shortenController(SubSub\DefaultController::class));
        $this->assertEquals('Default', $dispatcher->shortenController(DefaultController::class));
    }

    function test_findController()
    {
        $this->assertEquals([HogeController::class, 'default'], $this->service->dispatcher->findController('Hoge', 'default'));
    }

    function test_findController_notfound()
    {
        $namespace = array_key_first($this->service->controllerLocation);

        $ca = $this->service->dispatcher->findController('@@@', 'test');
        $this->assertEquals([404, "{$namespace}@@@Controller class doesn't exist."], $ca);

        $ca = $this->service->dispatcher->findController('notfoundclass', 'test');
        $this->assertEquals([404, "{$namespace}notfoundclassController class doesn't exist."], $ca);

        $ca = $this->service->dispatcher->findController('Abstract', 'test');
        $this->assertEquals([404, "{$namespace}AbstractController class is abstract."], $ca);

        $ca = $this->service->dispatcher->findController('Hoge', 'notfound');
        $this->assertEquals([404, "{$namespace}HogeController class doesn't have notfound."], $ca);
    }

    function test_loadController()
    {
        $service = $this->provideService([]);

        $request = Request::create('', 'POST');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_origin', $request));
        $request->headers->set('origin', 'http://allowed2.host:1234');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_origin', $request));
        $request->headers->set('origin', 'http://hogera.allowed.host');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_origin', $request));

        $request = Request::create('', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.0');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_ip_allow', $request));
        $request->server->set('REMOTE_ADDR', '203.0.114.0');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_ip_deny', $request));

        $request = Request::create('', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_ajax', $request));

        $request = Request::create('', 'GET');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'nopost', $request));

        $request = Request::create('', 'GET');
        $request->attributes->set('context', 'json');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_andcontext', $request));

        $request = Request::create('', 'GET');
        $request->attributes->set('context', 'json');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_anycontext', $request));
        $request->attributes->set('context', 'hoge');
        $this->assertInstanceOf(HogeController::class, $service->dispatcher->loadController(HogeController::class, 'action_anycontext', $request));
    }

    function test_loadController_notallowed()
    {
        $service = $this->provideService();

        $this->assertException("is not allowed Origin", function () use ($service) {
            $request = Request::create('', 'POST');
            $request->headers->set('origin', 'http://unknown.host');
            $service->dispatcher->loadController(HogeController::class, 'action_origin', $request);
        });

        $this->assertException("is not allowed from", function () use ($service) {
            $request = Request::create('', 'GET');
            $request->server->set('REMOTE_ADDR', '203.0.114.0');
            $service->dispatcher->loadController(HogeController::class, 'action_ip_allow', $request);
        });

        $this->assertException("is denied from", function () use ($service) {
            $request = Request::create('', 'GET');
            $request->server->set('REMOTE_ADDR', '203.0.113.0');
            $service->dispatcher->loadController(HogeController::class, 'action_ip_deny', $request);
        });

        $this->assertException("only accepts XmlHttpRequest", function () use ($service) {
            $request = Request::create('', 'GET');
            $service->dispatcher->loadController(HogeController::class, 'action_ajax', $request);
        });

        $this->assertException("doesn't allow POST method", function () use ($service) {
            $request = Request::create('', 'POST');
            $service->dispatcher->loadController(HogeController::class, 'nopost', $request);
        });

        $this->assertException("doesn't allow 'html' context", function () use ($service) {
            $request = Request::create('', 'GET');
            $request->attributes->set('context', 'html');
            $service->dispatcher->loadController(HogeController::class, 'action_emptycontext', $request);
        });
    }
}
