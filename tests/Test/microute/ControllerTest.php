<?php
namespace ryunosuke\Test\microute;

use ryunosuke\microute\Controller;
use ryunosuke\Test\microute\autoload\Hoge;
use ryunosuke\Test\stub\Controller\DispatchController;
use ryunosuke\Test\stub\Controller\EventController;
use ryunosuke\Test\stub\Controller\HogeController;
use ryunosuke\Test\stub\Controller\SubSub\FooBarController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class ControllerTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_metadata()
    {
        $metadata = new \ReflectionProperty(Controller::class, 'metadata');
        $metadata->setAccessible(true);

        $this->service->cacher->clear();
        $metadata->setValue([]);
        $controller = new HogeController($this->service, '');
        $this->assertNotEmpty($controller::metadata($this->service->cacher));

        $metadata->setValue([]);
        $controller = new HogeController($this->service, '');
        $this->assertNotEmpty($controller::metadata($this->service->cacher));

        $mdata = $controller::metadata($this->service->cacher);
        $this->assertEquals(['', 'json'], $mdata['actions']['action_andcontext']['@context']);
        $this->assertEquals(['*'], $mdata['actions']['action_anycontext']['@context']);
        $this->assertEquals(['GET', 'COOKIE'], $mdata['actions']['argument']['@argument']);
    }

    function test_autoload()
    {
        $namespace = 'ryunosuke\\Test\\microute\\autoload';
        $this->assertEquals([$namespace], Controller::autoload($namespace, ['a', 'b', 'c']));

        $controller1 = new HogeController($this->service, 'default');
        $controller2 = new FooBarController($this->service, 'default');
        $this->assertSame($controller1->Hoge, $controller2->Hoge);
        $this->assertInstanceOf(Hoge::class, $controller1->Hoge);
        $this->assertEquals(['a', 'b', 'c'], $controller1->Hoge->ctor_args);
        $this->assertEquals(1, Hoge::$newCount);

        $this->assertException(new \DomainException('hoge is undefined'), function () use ($controller1) {
            return $controller1->hoge;
        });
    }

    function test___get()
    {
        $controller = new HogeController($this->service, 'action-a');
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Session\Session::class, $controller->session);
        $this->assertInstanceOf(Request::class, $controller->request);
        $this->assertInstanceOf(Response::class, $controller->response);
        $this->assertEquals('action-a', $controller->action);
        $this->assertSame($this->service, $controller->service);
    }

    function test___toString()
    {
        $controller = new HogeController($this->service, 'action');
        $this->assertException('__toString is not supported', [$controller, '__toString']);
    }

    function test_location()
    {
        $controller = new HogeController($this->service, 'default');
        $this->assertEquals('Hoge/default', $controller->location());

        $controller = new FooBarController($this->service, 'actionTest');
        $this->assertEquals('SubSub/FooBar/actionTest', $controller->location());
    }

    function test_cookie()
    {
        $controller = new HogeController($this->service, '');

        $cookie1 = $controller->cookie(['name' => 'cname']);
        $cookie2 = $controller->cookie([], 'cname');
        $this->assertEquals($cookie1, $cookie2);
        $this->assertEquals('cname', $cookie1->getName());

        $cookie1 = $controller->cookie(['name' => 'cname', 'secure' => true]);
        $cookie2 = $controller->cookie([], 'cname', null, 0, '/', null, true, true, false, null);
        $this->assertEquals($cookie1, $cookie2);
        $this->assertEquals('cname', $cookie1->getName());
        $this->assertEquals(true, $cookie1->isSecure());
    }

    function test_background()
    {
        $controller = new HogeController($this->service, '');

        $callback = $controller->background(function () { return 123; });
        $this->assertEquals(123, $callback());
    }

    function test_authenticate_basic()
    {
        foreach ([true, false] as $bool) {
            $service = $this->provideService([
                'authenticationProvider'   => ['user' => password_hash('pass', PASSWORD_DEFAULT)],
                'authenticationComparator' => function () {
                    return function ($valid_password, $password) { return password_verify($password, $valid_password); };
                },
                'controllerAnnotation'     => $bool, // for compatible
            ]);
            $service->cacher->clear();
            $metadata = new \ReflectionProperty(Controller::class, 'metadata');
            $metadata->setAccessible(true);
            $metadata->setValue([]);

            $request = new Request();

            $controller = new HogeController($service, 'basic', $request);
            $response = $controller->dispatch();
            $this->assertEquals(null, $request->attributes->get('authname'));
            $this->assertEquals('Basic realm="This page is required BASIC auth"', $response->headers->get('www-authenticate'));
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertEquals('', $response->getContent());

            $request->server->set('PHP_AUTH_USER', 'dummy');
            $request->server->set('PHP_AUTH_PW', 'dummy');
            $controller = new HogeController($service, 'basic', $request);
            $response = $controller->dispatch();
            $this->assertEquals(null, $request->attributes->get('authname'));
            $this->assertEquals('Basic realm="This page is required BASIC auth"', $response->headers->get('www-authenticate'));
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertEquals('', $response->getContent());

            $request->server->set('PHP_AUTH_USER', 'user');
            $request->server->set('PHP_AUTH_PW', 'pass');
            $controller = new HogeController($service, 'basic', $request);
            $response = $controller->dispatch();
            $this->assertEquals('user', $request->attributes->get('authname'));
            $this->assertEquals(null, $response->headers->get('www-authenticate'));
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('basic', $response->getContent());
        }
    }

    function test_authenticate_digest()
    {
        $digest = function ($authheader, $username, $password) {
            $md5implode = function ($_) { return md5(implode(':', func_get_args())); };

            $authheader = explode(' ', $authheader, 2)[1];
            $auth = [];
            foreach (array_map('trim', str_getcsv($authheader)) as $token) {
                [$key, $value] = explode('=', $token, 2);
                $auth[$key] = trim($value, '"');
            }
            $response = $md5implode(
                $md5implode($username, $auth['realm'], $password),
                $auth['nonce'],
                '00000001',
                '0123456789abcdef',
                $auth['qop'],
                $md5implode('GET', '/path')
            );
            return implode(', ', [
                'username="' . $username . '"',
                'realm="' . $auth['realm'] . '"',
                'nonce="' . $auth['nonce'] . '"',
                'uri="/path"',
                'algorithm=MD5',
                'response="' . $response . '"',
                'qop=' . $auth['qop'],
                'nc=00000001',
                'cnonce="0123456789abcdef"',
            ]);
        };

        foreach ([true, false] as $bool) {
            $service = $this->provideService([
                'authenticationProvider' => function () {
                    return function ($username) { return strtoupper($username); };
                },
                'controllerAnnotation'   => $bool,
            ]);
            $service->cacher->clear();
            $metadata = new \ReflectionProperty(Controller::class, 'metadata');
            $metadata->setAccessible(true);
            $metadata->setValue([]);

            $request = new Request();
            $request->server->set('REQUEST_URI', '/path');

            $controller = new HogeController($service, 'digest', $request);
            $response = $controller->dispatch();
            $this->assertEquals(null, $request->attributes->get('authname'));
            $this->assertStringStartsWith('Digest realm="This page is required DIGEST auth"', $response->headers->get('www-authenticate'));
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertEquals('', $response->getContent());

            $request->server->set('PHP_AUTH_DIGEST', $digest($response->headers->get('www-authenticate'), 'hoge', 'fuga'));
            $controller = new HogeController($service, 'digest', $request);
            $response = $controller->dispatch();
            $this->assertEquals(null, $request->attributes->get('authname'));
            $this->assertStringStartsWith('Digest realm="This page is required DIGEST auth"', $response->headers->get('www-authenticate'));
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertEquals('', $response->getContent());

            $request->server->set('PHP_AUTH_DIGEST', $digest($response->headers->get('www-authenticate'), 'aaa', 'AAA'));
            $controller = new HogeController($service, 'digest', $request);
            $response = $controller->dispatch();
            $this->assertEquals('aaa', $request->attributes->get('authname'));
            $this->assertEquals(null, $response->headers->get('www-authenticate'));
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('digest', $response->getContent());
        }
    }

    function test_authenticate_misc()
    {
        $metadata = new \ReflectionProperty(Controller::class, 'metadata');
        $metadata->setAccessible(true);
        $metadata->setValue([]);

        $service = $this->provideService([
            'controllerAnnotation' => true,
        ]);
        $controller = new HogeController($service, 'realm', new Request());
        $this->assertException(new \DomainException('realm should not be contains'), [$controller, 'dispatch']);

        $controller = new HogeController($service, 'hogera', new Request());
        $this->assertException(new \DomainException('hogera is not supported'), [$controller, 'dispatch']);

        $metadata = new \ReflectionProperty(Controller::class, 'metadata');
        $metadata->setAccessible(true);
        $metadata->setValue([]);

        $service = $this->provideService([
            'controllerAnnotation' => false,
        ]);
        $controller = new HogeController($service, 'realm', new Request());
        $this->assertException(new \DomainException('realm should not be contains'), [$controller, 'dispatch']);

    }

    function test_session()
    {
        $storage = new MockFileSessionStorage(sys_get_temp_dir() . '/service-session');
        $service = $this->provideService([
            'sessionStorage' => $storage,
        ]);

        // セッションをいじるまでは始まらない
        $this->assertFalse($storage->isStarted());

        $controller = new HogeController($service, 'action-a');
        $controller->session->set('key', 'value');
        $this->assertEquals('value', $controller->session->get('key'));

        // セッションをいじると開始される
        $this->assertTrue($storage->isStarted());
    }

    function test_redirect()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirect('http://localhost/');
        $this->assertTrue($response->isRedirect('http://localhost/'));
    }

    function test_redirectInternal()
    {
        $controller = new HogeController($this->service, 'action-a', Request::create('http://remotehost'));
        $response = $controller->redirectInternal('http://remotehost/');
        $this->assertTrue($response->isRedirect('http://remotehost/'));
        $response = $controller->redirectInternal('http://evilhost/', '/default');
        $this->assertTrue($response->isRedirect('/default'));
    }

    function test_redirectRoute()
    {
        $this->service->router
            ->route('routeName', HogeController::class, 'default')
            ->regex('/(?<name>[a-z0-9]+)/detail/(?<seq>[0-9]+)', HogeController::class, 'default');
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirectRoute('routeName', ['name' => 'hoge', 'seq' => 789]);
        $this->assertTrue($response->isRedirect('/hoge/detail/789'));
    }

    function test_redirectThis_simple()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirectThis('actionB');
        $this->assertTrue($response->isRedirect('/hoge/action-b'));
    }

    function test_redirectThis_self()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirectThis(['test' => 123]);
        $this->assertTrue($response->isRedirect('/hoge/action-a?test=123'));
    }

    function test_redirectThis_other()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirectThis(['test' => 123], 'actionB');
        $this->assertTrue($response->isRedirect('/hoge/action-b?test=123'));
    }

    function test_redirectThis_full()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->redirectThis(['test' => 123], 'actionTest', FooBarController::class);
        $this->assertTrue($response->isRedirect('/sub-sub/foo-bar/action-test?test=123'));
    }

    function test_redirectCurrent()
    {
        $controller = new HogeController($this->provideService([
            'request' => Request::create('/path/to/current'),
        ]), 'action-a');
        $response = $controller->redirectCurrent(['test' => 123]);
        $this->assertTrue($response->isRedirect('/path/to/current?test=123'));
    }

    function test_json()
    {
        $controller = new HogeController($this->service, 'action-a');

        $response = $controller->json(['status' => true, 'message' => 'ok']);
        $this->assertEquals('{"status":true,"message":"ok"}', $response->getContent());

        $response = $controller->json(['status' => true, 'message' => 'ok'], JSON_PRETTY_PRINT);
        $this->assertEquals('{
    "status": true,
    "message": "ok"
}', $response->getContent());
    }

    function test_content()
    {
        // 普通に投げれば普通にレスポンスが返ってくるはず
        $request = Request::createFromGlobals();
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__);
        $response->prepare($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/x-php; charset=UTF-8', $response->headers->get('content-type'));
        $this->assertEquals('private, must-revalidate', $response->headers->get('cache-control'));
        $this->assertTrue($response->headers->has('last-modified'));
        ob_start();
        $response->send();
        $actual = ob_get_clean();
        $this->assertStringEqualsFile(__FILE__, $actual);

        // If-Modified-Since を設定すると空の304が返ってくるはず
        $request = Request::createFromGlobals();
        $request->headers->set('if-modified-since', date('r'));
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__);
        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());

        // cache を有効でかつ public だと cache-control が public になるはず
        $request = Request::createFromGlobals();
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__, true, true);
        $this->assertEquals('public', $response->headers->get('cache-control'));
        $this->assertTrue($response->headers->has('last-modified'));

        // cache を無効なら last-modified はつかないはず
        $request = Request::createFromGlobals();
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__, false);
        $this->assertEquals('no-cache, private', $response->headers->get('cache-control'));
        $this->assertFalse($response->headers->has('last-modified'));

        // cache を無効なら public は意味を為さないはず（これ assert の方が良いと思う）
        $request = Request::createFromGlobals();
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__, false, true);
        $this->assertEquals('no-cache, private', $response->headers->get('cache-control'));
        $this->assertFalse($response->headers->has('last-modified'));

        // POST でキャッシュは使用されないはず
        $request = Request::createFromGlobals();
        $request->setMethod('POST');
        $request->headers->set('if-modified-since', date('r'));
        $controller = new HogeController($this->service, 'action-a', $request);
        $response = $controller->content(__FILE__);
        $this->assertEquals(200, $response->getStatusCode());
        ob_start();
        $response->send();
        $actual = ob_get_clean();
        $this->assertStringEqualsFile(__FILE__, $actual);
    }

    function test_content_ex()
    {
        // ディレクトリは 403 のはず
        $this->assertStatusCode(403, function () {
            $controller = new HogeController($this->service, 'action-a');
            $controller->content(__DIR__);
        });

        // 見つからないなら 404 のはず
        $this->assertStatusCode(404, function () {
            $controller = new HogeController($this->service, 'action-a');
            $controller->content(__FILE__ . '/notfound');
        });
    }

    function test_download_file()
    {
        $controller = new HogeController($this->service, 'action-a');

        $response = $controller->download(new \SplFileInfo(__FILE__), 'filename.txt');
        $this->assertEquals("attachment; filename=filename.txt", $response->headers->get('Content-Disposition'));
        ob_start();
        $response->send();
        $actual = ob_get_clean();
        $this->assertEquals(file_get_contents(__FILE__), $actual);

        $response = $controller->download(new \SplFileInfo(__FILE__));
        $this->assertEquals('attachment; filename=ControllerTest.php', $response->headers->get('Content-Disposition'));
        ob_start();
        $response->send();
        $actual = ob_get_clean();
        $this->assertEquals(file_get_contents(__FILE__), $actual);
    }

    function test_download_closure()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->download(function () {
            echo str_repeat('heavy', '3');
        }, 'filename.txt');
        $this->assertEquals('attachment; filename=filename.txt', $response->headers->get('Content-Disposition'));
        ob_start();
        $response->send();
        $actual = ob_get_clean();
        $this->assertEquals('heavyheavyheavy', $actual);

        $this->assertException(new \InvalidArgumentException('$filename must be not null.'), function () use ($controller) {
            $controller->download(function () { });
        });
    }

    function test_download_direct()
    {
        $controller = new HogeController($this->service, 'action-a');
        $response = $controller->download('content', 'filename.txt');
        $this->assertEquals('attachment; filename=filename.txt', $response->headers->get('Content-Disposition'));
        $this->assertEquals('content', $response->getContent());

        $this->assertException(new \InvalidArgumentException('$filename must be not null.'), function () use ($controller) {
            $controller->download('');
        });
    }

    function test_push()
    {
        $controller = new HogeController($this->service, 'action-a');

        ob_start();
        $controller->push(function () {
            yield [1, 2, 3];
        }, 1, true)->sendContent();
        $output = ob_get_clean();
        $this->assertStringContainsString('data: [1,2,3]', $output);

        ob_start();
        $controller->push(function () {
            yield "string data1\nstring data2";
        }, 1, true)->sendContent();
        $output = ob_get_clean();
        $this->assertStringContainsString('data: string data1', $output);
        $this->assertStringContainsString('data: string data2', $output);

        ob_start();
        $controller->push(function () {
            yield (object) [
                'id'    => 123,
                'event' => 'receive',
                'retry' => new class {
                    public function __toString() { return '1000'; }
                },
                'data'  => (object) ["object data1", "object data2"],
            ];
        }, 1, true)->sendContent();
        $output = ob_get_clean();
        $this->assertStringContainsString('id: 123', $output);
        $this->assertStringContainsString('event: receive', $output);
        $this->assertStringContainsString('retry: 1000', $output);
        $this->assertStringContainsString('data: {"0":"object data1","1":"object data2"}', $output);

        ob_start();
        $controller->push(function () {
            return [];
        }, 0.5, true)->sendContent();
        $output = ob_get_clean();
        $this->assertEquals('', $output);
    }

    function test_dispatch()
    {
        $request = Request::createFromGlobals();
        $controller = new DispatchController($this->service, 'main', $request);
        $response = $controller->dispatch();

        $this->assertTrue($request->attributes->has('init'));
        $this->assertTrue($request->attributes->has('before'));
        $this->assertEquals('mainafterfinish', $response->getContent());
    }

    function test_dispatch_init()
    {
        $request = Request::createFromGlobals();
        $request->attributes->set('init-response', true);
        $controller = new DispatchController($this->service, 'main', $request);
        $response = $controller->dispatch();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('init-response', json_decode($response->getContent()));
    }

    function test_dispatch_finish()
    {
        $request = Request::createFromGlobals();
        $request->attributes->set('finish-response', true);
        $controller = new DispatchController($this->service, 'main', $request);
        $response = $controller->dispatch();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('finish-response', json_decode($response->getContent()));
    }

    function test_dispatch_error()
    {
        $request = Request::createFromGlobals();
        $controller = new DispatchController($this->service, 'thrown1', $request);
        $response = $controller->dispatch();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('error-response', json_decode($response->getContent()));

        $this->assertException(new \RuntimeException('catch'), function () use ($controller) {
            $controller->dispatch([], false);
        });
    }

    function test_dispatch_noresponse()
    {
        $this->assertException(new \RuntimeException('Controller#error is must be return Response'), function () {
            $controller = new FooBarController($this->service, 'thrown');
            $controller->dispatch();
        });
    }

    function test_action_ajax()
    {
        $request = Request::createFromGlobals();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $controller = new HogeController($this->service, 'action_ajax', $request);
        $response = $controller->action([]);
        $this->assertEquals('ajaxed', $response->getContent());
    }

    function test_action_context()
    {
        $request = Request::createFromGlobals();
        $request->attributes->set('context', 'json');
        $controller = new HogeController($this->service, 'action_context', $request);
        $response = $controller->action([]);
        $this->assertEquals('json_context', $response->getContent());
    }

    function test_action_string()
    {
        $controller = new HogeController($this->service, 'action_raw');
        $response = $controller->action([]);
        $this->assertEquals('string', $response->getContent());
    }

    function test_action_response()
    {
        $controller = new HogeController($this->service, 'action_response');
        $response = $controller->action([]);
        $this->assertEquals('response', $response->getContent());
    }

    function test_action_unknown()
    {
        $controller = new HogeController($this->service, 'action_unknown');
        $response = $controller->action([]);
        $this->assertEquals('["unknown"]', $response->getContent());
    }

    function test_event_cache()
    {
        $request = Request::createFromGlobals();
        $request->setMethod('POST');

        // POSTリクエストを送ると・・・
        $controller = new EventController($this->service, 'cache', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // LastModified は未設定のはず
        $this->assertEquals(null, $response->getLastModified());
        // Expires は未設定のはず
        $this->assertEquals(null, $response->getExpires());
        // 中身はあるはず
        $this->assertStringContainsString('cached_response', $response->getContent());

        // If-Modified-Since を設定しても・・・
        $controller = new EventController($this->service, 'cache', $request);
        $request->headers->set('if-modified-since', date('r'));
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // 中身はあるはず
        $this->assertStringContainsString('cached_response', $response->getContent());
    }

    function test_event_cache_1()
    {
        $request = Request::createFromGlobals();

        // 普通にリクエストを送ると・・・
        $controller = new EventController($this->service, 'cache1', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // LastModified が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getLastModified());
        // Expires が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getExpires());
        // 中身はあるはず
        $this->assertStringContainsString('cached_response', $response->getContent());

        // If-Modified-Since を設定すると・・・
        $controller = new EventController($this->service, 'cache1', $request);
        $request->headers->set('if-modified-since', date('r'));
        $response = $controller->action([]);
        // 304 のはず
        $this->assertEquals(304, $response->getStatusCode());
        // 中身は空のはず
        $this->assertEquals('', $response->getContent());

        // If-Modified-Since を過去に設定すると・・・
        $controller = new EventController($this->service, 'cache1', $request);
        $request->headers->set('if-modified-since', date('r', time() - 3600));
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // 中身はあるはず
        $this->assertStringContainsString('cached_response', $response->getContent());
    }

    function test_event_cache_direct()
    {
        $request = Request::createFromGlobals();

        // 普通にリクエストを送ると・・・
        $controller = new EventController($this->service, 'cacheDirect', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // LastModified が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getLastModified());
        // Expires が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getExpires());

        // If-Modified-Since を設定すると・・・
        $controller = new EventController($this->service, 'cacheDirect', $request);
        $request->headers->set('if-modified-since', date('r'));
        $response = $controller->action([]);
        // 304 のはず
        $this->assertEquals(304, $response->getStatusCode());
        // 中身は空のはず
        $this->assertEquals('', $response->getContent());
    }

    function test_event_cache_debug()
    {
        $service = $this->provideService([
            'debug' => true,
        ]);

        $request = Request::createFromGlobals();

        // 普通にリクエストを送ると・・・
        $controller = new EventController($service, 'cache1', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // debug 中は null のはず
        $this->assertNull($response->getLastModified());
        // debug 中は null のはず
        $this->assertNull($response->getExpires());
        // 中身はあるはず
        $this->assertStringContainsString('cached_response', $response->getContent());
    }

    function test_event_post_public()
    {
        $request = Request::createFromGlobals();
        $request->setMethod('POST');

        // POSTリクエストを送ると・・・
        $controller = new EventController($this->service, 'public', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // LastModified は未設定のはず
        $this->assertEquals(null, $response->getLastModified());
        // Expires は未設定のはず
        $this->assertEquals(null, $response->getExpires());
        // 中身はあるはず
        $this->assertEquals('publiced_response', $response->getContent());

        // If-Modified-Since を設定しても・・・
        $controller = new EventController($this->service, 'public', $request);
        $request->headers->set('if-modified-since', date('r'));
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // 中身はあるはず
        $this->assertEquals('publiced_response', $response->getContent());
    }

    function test_event_get_public()
    {
        $request = Request::create('/public.html');
        $request->server->set('DOCUMENT_ROOT', realpath(__DIR__ . '/../../stub/public'));
        $request->setMethod('GET');

        // GETリクエストを送ると・・・
        $controller = new EventController($this->service, 'public', $request);
        $response = $controller->action([]);
        // 200 のはず
        $this->assertEquals(200, $response->getStatusCode());
        // LastModified が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getLastModified());
        // Expires が設定されているはず
        $this->assertInstanceOf('DateTime', $response->getExpires());
        // 中身はあるはず
        $this->assertEquals('publiced_response', $response->getContent());
        // 公開ディレクトリにファイルが出来ているはず
        $this->assertStringEqualsFile(__DIR__ . '/../../stub/public/public.html', $response->getContent());
    }

    function test_event_other()
    {
        // 普通にリクエストを送ると・・・
        $request = Request::createFromGlobals();
        $controller = new EventController($this->service, 'other', $request);
        $response = $controller->action([]);
        // 普通の action レスポンスが返るはず
        $this->assertStringContainsString('other_event', $response->getContent());

        // other1:pre イベントにマッチするように送ると・・・
        $request = Request::createFromGlobals();
        $request->query->set('other1:pre', "1, 2, 3");
        $controller = new EventController($this->service, 'other', $request);
        $response = $controller->action([]);
        // other1:pre が返るはず
        $this->assertEquals('other1:pre', $response->getContent());

        // other1:post イベントにマッチするように送ると・・・
        $request = Request::createFromGlobals();
        $request->query->set('other1:post', "1, 2, 3");
        $controller = new EventController($this->service, 'other', $request);
        $response = $controller->action([]);
        // other1:post が返るはず
        $this->assertEquals('other1:post', $response->getContent());

        // other2:pre イベントにマッチするように送ると・・・
        $request = Request::createFromGlobals();
        $request->query->set('other2:pre', "4, 5, 6");
        $controller = new EventController($this->service, 'other', $request);
        $response = $controller->action([]);
        // other2:pre が返るはず
        $this->assertEquals('other2:pre', $response->getContent());

        // other2:post イベントにマッチするように送ると・・・
        $request = Request::createFromGlobals();
        $request->query->set('other2:post', "4, 5, 6");
        $controller = new EventController($this->service, 'other', $request);
        $response = $controller->action([]);
        // other2:post が返るはず
        $this->assertEquals('other2:post', $response->getContent());
    }

    function test_event_alternate()
    {
        $metadata = EventController::metadata();
        $this->assertEquals(['cache' => ['100']], $metadata['actions']['alternate']['@events']);
    }

    function test_event_unknown()
    {
        $this->assertException(new \DomainException('unknown event'), function () {
            $request = Request::createFromGlobals();
            $controller = new EventController($this->service, 'unknown', $request);
            $response = $controller->action([]);
            $this->assertStringContainsString('other_event', $response->getContent());
        });
    }
}
