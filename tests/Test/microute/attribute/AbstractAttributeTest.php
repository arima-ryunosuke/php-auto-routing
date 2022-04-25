<?php
namespace ryunosuke\Test\microute\attribute;

use Attribute;
use ryunosuke\microute\attribute\AbstractAttribute;
use ryunosuke\microute\attribute\NoInheritance;

class AbstractAttributeTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_by_inherit()
    {
        $this->assertEquals([
            ChildClass::class,
            ParentClass::class,
        ], ConcreteAttribute::by(new \ReflectionClass(ChildClass::class)));

        $this->assertEquals([
            ChildClass::class . '::m',
            ChildClass::class,
            ParentClass::class . '::m',
            ParentClass::class,
        ], ConcreteAttribute::by((new \ReflectionClass(ChildClass::class))->getMethod('m')));

        $this->assertEquals([
            ChildClass::class . '::n',
        ], ConcreteAttribute::by((new \ReflectionClass(ChildClass::class))->getMethod('n')));
    }
}

#[Attribute]
class ConcreteAttribute extends AbstractAttribute
{
    private $arg;

    public function __construct($arg)
    {
        $this->arg = $arg;
    }

    public function merge(array &$result)
    {
        $result[] = $this->arg;
    }
}

#[ConcreteAttribute(__CLASS__)]
class AncestorClass
{
    #[ConcreteAttribute(__METHOD__)]
    function m()
    {
    }

    #[ConcreteAttribute(__METHOD__)]
    function n()
    {
    }
}

#[ConcreteAttribute(__CLASS__)]
#[NoInheritance()]
class ParentClass extends AncestorClass
{
    #[ConcreteAttribute(__METHOD__)]
    function m()
    {
    }

    #[ConcreteAttribute(__METHOD__)]
    function n()
    {
    }
}

#[ConcreteAttribute(__CLASS__)]
#[NoInheritance('OtherAttribute')]
class ChildClass extends ParentClass
{
    #[ConcreteAttribute(__METHOD__)]
    function m()
    {
    }

    #[NoInheritance(ConcreteAttribute::class)]
    #[ConcreteAttribute(__METHOD__)]
    function n()
    {
    }
}
