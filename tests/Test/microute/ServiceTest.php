<?php
namespace ryunosuke\Test\microute;

use MockLogger;
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
                public function getContentType(): ?string { return 'json'; }

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
        $this->assertIsCallable($this->service->requestFactory);
    }

    function test_trustedProxies()
    {
        $localfile = tempnam(sys_get_temp_dir(), 'tp') . '.json';
        file_put_contents($localfile, json_encode(['100.100.102.0/24']));

        $service = $this->provideService([
            'trustedProxies' => [
                'mynetwork',
                'private',
                '100.100.101.0/24',
                $localfile,
                'cloudfront' => [
                    'url'    => constant('CLOUDFRONT_IPS'),
                    'filter' => fn($contents) => array_merge($contents['CLOUDFRONT_GLOBAL_IP_LIST'], $contents['CLOUDFRONT_REGIONAL_EDGE_IP_LIST']),
                ],
            ],
            'request'        => new Request([], [], [], [], [], [
                'SERVER_ADDR' => '127.0.0.1',
                'REMOTE_ADDR' => '127.0.0.9',
            ]),
        ]);

        $proxies = $service->request->getTrustedProxies();
        $this->assertContains('127.0.0.1/8', $proxies);         // by mynetwork true
        $this->assertContains('10.0.0.0/8', $proxies);          // by private true
        $this->assertContains('172.16.0.0/12', $proxies);       // by private true
        $this->assertContains('192.168.0.0/16', $proxies);      // by private true
        $this->assertContains('100.100.101.0/24', $proxies);    // by cidr true
        $this->assertContains('100.100.102.0/24', $proxies);    // by localfile
        $this->assertContains('120.52.22.96/27', $proxies);     // by cloudfront

        $service->request->headers->set('x-forwarded-for', "1.1.1.1, 120.52.22.96, 100.100.101.1");
        $this->assertEquals('1.1.1.1', $service->request->getClientIp());

        $service->request->headers->set('x-forwarded-for', "1.1.1.1, 1.1.1.2, 120.52.22.96, 100.100.101.1");
        $this->assertEquals('1.1.1.2', $service->request->getClientIp());

        $service->request->headers->set('x-forwarded-for', "1.1.1.1, 120.52.22.96, 1.1.1.3, 100.100.101.1");
        $this->assertEquals('1.1.1.3', $service->request->getClientIp());

        $service->request->headers->set('x-forwarded-for', "1.1.1.1, 120.52.22.96, 100.100.101.1, 1.1.1.4");
        $this->assertEquals('1.1.1.4', $service->request->getClientIp());
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
            'logger' => new MockLogger(function ($level, $message, $context) use (&$logs) {
                if (isset($context['exception'])) {
                    $logs[] = $context['exception']->getMessage();
                }
            }),
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
            'logger'         => new MockLogger(function ($level, $message, $context) use (&$logs) {
                $logs[] = $context['exception'];
            }),
        ]);
        $service->run();
        $this->assertInstanceOf(\Throwable::class, $logs[0]);
    }
}
