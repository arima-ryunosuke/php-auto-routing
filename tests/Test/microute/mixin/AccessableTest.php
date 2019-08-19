<?php
namespace ryunosuke\Test\microute\mixin;

use ryunosuke\microute\mixin\Accessable;
use ryunosuke\microute\Service;

class AccessableTest extends \ryunosuke\Test\AbstractTestCase
{
    function test___isset()
    {
        $object = new AccessableClass($this->service);
        $this->assertFalse(isset($object->privateNull));
        $this->assertTrue(isset($object->privateInt));
        $this->assertFalse(isset($object->hoge));
    }

    function test___get()
    {
        $object = new AccessableClass($this->service);
        $this->assertNull($object->privateNull);
        $this->assertIsInt($object->privateInt);
        $this->assertIsBool($object->debug);

        $this->assertException('hoge is undefined', function () use ($object) {
            $object->hoge;
        });
    }

    function test___set()
    {
        $object = new AccessableClass($this->service);
        $this->assertException('hoge is undefined', function () use ($object) {
            $object->hoge = 123;
        });
    }
}

/**
 * @property-read $privateNull
 * @property-read $privateInt
 */
class AccessableClass
{
    use Accessable;

    /** @var Service */
    private $service;

    private $privateNull = null;
    private $privateInt  = 123;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }
}
