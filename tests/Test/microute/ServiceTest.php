<?php
namespace ryunosuke\Test\microute;

use ryunosuke\microute\Controller;
use ryunosuke\microute\Service;
use ryunosuke\Test\stub\Controller\DefaultController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_annotation()
    {
        // @property-read を忘れがちなのでざっくりとチェックする
        $ref = new \ReflectionClass($this->service);
        $doccomment = $ref->getDocComment();
        $values = $ref->getProperty('values');
        $values->setAccessible(true);
        foreach ($values->getValue($this->service) as $property => $value) {
            $this->assertStringContainsString("\$$property", $doccomment);
        }
    }

    function test___construct()
    {
        spl_autoload_register(function ($class) {
            $localname = str_replace('ryunosuke\\Test\\stub\\Controller\\', '', $class);
            $localpath = str_replace('\\', DIRECTORY_SEPARATOR, $localname);
            $fullpath = __DIR__ . '/../../stub/Controller/' . $localpath . '.php';
            if (file_exists($fullpath)) {
                include $fullpath;
            }
        });
        $service = $this->provideService([
            'debug'              => true,
            'requestClass'       => new class extends Request {
                public function getContentType() { return 'json'; }

                public function getContent(bool $asResource = false) { return json_encode(['A' => ['B' => ['C' => 'Z']]]); }
            },
            'controllerLocation' => DefaultController::class,
        ]);

        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\', $service->controllerNamespace);
        $this->assertEquals(realpath(__DIR__ . '/../../stub/Controller') . DIRECTORY_SEPARATOR, $service->controllerDirectory);
        $this->assertEquals(['A' => ['B' => ['C' => 'Z']]], $service->request->request->all());
    }

    function test___isset()
    {
        $this->assertTrue(isset($this->service->debug));
        $this->assertFalse(isset($this->service->hoge));
    }

    function test___get()
    {
        $this->assertFalse($this->service->debug);
        $this->assertIsCallable($this->service->logger);
    }

    function test_handle()
    {
        $service = $this->service;
        $response = $service->handle(Request::create('dispatch/main'));
        $this->assertEquals('mainafterfinish', $response->getContent());
    }

    function test_handle_nocatch()
    {
        $service = $this->service;
        $this->assertException(new HttpException(404), function () use ($service) {
            $service->handle(Request::create('none'), Service::MAIN_REQUEST, false);
        });
    }

    function test_handle_error()
    {
        $service = $this->service;
        $response = $service->handle(Request::create('sub-sub/foo-bar/notfound'));
        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\SubSub\\DefaultController::errorAction', $response->getContent());
    }

    function test_event()
    {
        $logs = [];
        $self = $this;
        $service = $this->provideService([
            'logger' => function () use (&$logs) {
                return function (\Throwable $t) use (&$logs) {
                    $logs[] = $t->getMessage();
                };
            },
            'events' => [
                'request'  => [
                    function (Request $request) use ($self) {
                        $self->assertInstanceOf(Service::class, $this);
                        $request->attributes->set('a', 'A');
                        return false;
                    },
                    function (Request $request) use ($self) {
                        $self->fail();
                    },
                ],
                'dispatch' => [
                    function (Controller $controller) use ($self) {
                        $self->assertInstanceOf(Service::class, $this);
                        throw new \Exception('on dispatch');
                    },
                    function (Controller $controller) use ($self) {
                        $self->fail();
                    },
                ],
                'error'    => function (\Throwable $t) use ($self) {
                    $self->assertInstanceOf(Service::class, $this);
                },
                'response' => [
                    function (Response $response) use ($self) {
                        $self->assertInstanceOf(Service::class, $this);
                        $response->setContent('X');
                    },
                    function (Response $response) use ($self) {
                        $self->assertInstanceOf(Service::class, $this);
                        return new Response($response->getContent() . 'Y');
                    },
                ],
            ],
        ]);
        $response = $service->handle($service->request);
        $this->assertEquals('XY', $response->getContent());
        $this->assertEquals('A', $service->request->attributes->get('a'));
        $this->assertEquals('on dispatch', $logs[0]);

        $service = $this->provideService([
            'events' => [
                'request'  => function (Request $request) {
                    return new Response('on request');
                },
                'dispatch' => function () use ($self) {
                    $self->fail();
                },
                'error'    => function () use ($self) {
                    $self->fail();
                },
                'response' => function (Response $response) {
                    $response->setStatusCode(201);
                },
            ],
        ]);
        $response = $service->handle($service->request);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('on request', $response->getContent());
    }

    function test_run()
    {
        $service = $this->provideService([
            'request' => Request::create('dispatch/main'),
        ]);
        ob_start();
        $service->run();
        $this->assertEquals('mainafterfinish', ob_get_clean());
    }

    function test_run_error()
    {
        $logs = [];
        $service = $this->provideService([
            'requestFactory' => function () {
                return function ($query, $request, $attributes, $cookies, $files, $server, $content) {
                    throw new \TypeError();
                };
            },
            'logger'         => function () use (&$logs) {
                return function ($t) use (&$logs) {
                    $logs[] = $t;
                };
            },
        ]);
        $service->run();
        $this->assertInstanceOf(\Throwable::class, $logs[0]);
    }
}
