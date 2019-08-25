<?php
namespace ryunosuke\Test\microute;

use ryunosuke\microute\Service;
use ryunosuke\Test\stub\Controller\DefaultController;
use Symfony\Component\HttpFoundation\Request;
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
            $this->assertContains("\$$property", $doccomment);
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
            'controllerLocation' => DefaultController::class,
        ]);

        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\', $service->controllerNamespace);
        $this->assertEquals(realpath(__DIR__ . '/../../stub/Controller') . DIRECTORY_SEPARATOR, $service->controllerDirectory);
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
            $service->handle(Request::create('none'), Service::MASTER_REQUEST, false);
        });
    }

    function test_handle_error()
    {
        $service = $this->service;
        $response = $service->handle(Request::create('sub-sub/foo-bar/notfound'));
        $this->assertEquals('ryunosuke\\Test\\stub\\Controller\\SubSub\\DefaultController::errorAction', $response->getContent());
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
        $service = $this->provideService([
            'request' => Request::create('none'),
        ]);
        ob_start();
        $service->run();
        $this->assertEquals('Symfony\\Component\\HttpKernel\\Exception\\HttpException', ob_get_clean());
    }
}
