<?php
namespace ryunosuke\Test;

use PHPUnit\Framework\TestCase;
use ryunosuke\microute\Service;
use ryunosuke\SimpleCache\StreamCache;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractTestCase extends TestCase
{
    /** @var Service */
    protected $service;

    function setUp(): void
    {
        parent::setUp();

        $this->service = $this->provideService();
        $this->service->cacher->clear();
    }

    function provideService($options = [])
    {
        $defaults = [
            'cacher'                   => new StreamCache(sys_get_temp_dir() . '/microute'),
            'controllerLocation'       => [
                '\\ryunosuke\\Test\\stub\\Controller\\' => __DIR__ . '/../stub/Controller/',
            ],
            'defaultActionAsDirectory' => true,
            'controllerAnnotation'     => false,
        ];
        return new Service($options + $defaults);
    }

    public static function assertJsonStringEquals($expected, string $actualJson, string $message = ''): void
    {
        static::assertJsonStringEqualsJsonString($actualJson, json_encode($expected), $message);
    }

    public static function assertException($e, $callback)
    {
        if (is_string($e)) {
            if (class_exists($e)) {
                $ref = new \ReflectionClass($e);
                $e = $ref->newInstanceWithoutConstructor();
            }
            else {
                $e = new \Exception($e);
            }
        }

        $args = array_slice(func_get_args(), 2);
        $message = json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        try {
            $callback(...$args);
        }
        catch (\Throwable $ex) {
            // 型は常に判定
            self::assertInstanceOf(get_class($e), $ex, $message);
            // コードは指定されていたときのみ
            if ($e->getCode() > 0) {
                self::assertEquals($e->getCode(), $ex->getCode(), $message);
            }
            // メッセージも指定されていたときのみ
            if (strlen($e->getMessage()) > 0) {
                self::assertStringContainsString($e->getMessage(), $ex->getMessage(), $message);
            }
            return;
        }
        self::fail(get_class($e) . ' is not thrown.' . $message);
    }

    public static function assertStatusCode($expectedCode, $callback, ...$args)
    {
        try {
            call_user_func($callback, ...$args);
            self::fail('HttpException is not thrown.');
        }
        catch (HttpException $hx) {
            if (is_array($expectedCode)) {
                $message = reset($expectedCode);
                $code = key($expectedCode);
                self::assertEquals($code, $hx->getStatusCode());
                self::assertStringContainsString($message, $hx->getMessage());
            }
            else {
                self::assertEquals($expectedCode, $hx->getStatusCode());
            }
            return $hx;
        }
    }
}
