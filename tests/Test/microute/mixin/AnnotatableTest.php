<?php
namespace ryunosuke\Test\microute\mixin;

use ryunosuke\microute\mixin\Annotatable;

class AnnotatableTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_reflect()
    {
        $this->assertInstanceOf('\ReflectionClass', MiscClass::reflect());
        $this->assertInstanceOf('\ReflectionProperty', MiscClass::reflect('$c'));
        $this->assertInstanceOf('\ReflectionMethod', MiscClass::reflect('m'));

        $this->assertException(new \ReflectionException('nothing'), function () {
            MiscClass::reflect('nothing');
        });
    }

    function test_getAnnotationAll()
    {
        $this->assertSame([
            'p-multiple1',
            'p-multiple2',
        ], MiscClass::getAnnotationAll('prop', '$c'));

        $this->assertSame([
            'int $a this is a value',
            'int|string|null $b this is b value',
            '\stdclass $c',
        ], MiscClass::getAnnotationAll('param', 'f'));
    }

    function test_getAnnotationAsBool()
    {
        $this->assertSame(false, MiscClass::getAnnotationAsBool('bool', 'type'));
        $this->assertSame(true, MiscClass::getAnnotationAsBool('bool', 'm_type'));
        $this->assertSame(true, MiscClass::getAnnotationAsBool('bool', 'm_type'));

        $this->assertSame(null, MiscClass::getAnnotationAsBool('not', 'type', null));
        $this->assertSame(null, MiscClass::getAnnotationAsBool('not', 'm_type', null));
    }

    function test_getAnnotationAsInt()
    {
        $this->assertSame(123, MiscClass::getAnnotationAsInt('int', 'type'));
        $this->assertSame(123, MiscClass::getAnnotationAsInt('int', 'm_type'));

        $this->assertSame(null, MiscClass::getAnnotationAsInt('not', 'type', null));
        $this->assertSame(null, MiscClass::getAnnotationAsInt('not', 'm_type', null));
    }

    function test_getAnnotationAsString()
    {
        $this->assertSame('this is annotation comment', MiscClass::getAnnotationAsString('string', 'type'));
        $this->assertSame('this is annotation comment1', MiscClass::getAnnotationAsString('string', 'm_type'));

        $this->assertSame(null, MiscClass::getAnnotationAsString('not', 'type', null));
        $this->assertSame(null, MiscClass::getAnnotationAsString('not', 'm_type', null));
    }

    function test_getAnnotationAsList()
    {
        $this->assertSame(['a', 'b', 'c'], MiscClass::getAnnotationAsList('list', ' ', 'type'));
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], MiscClass::getAnnotationAsList('list', ' ', 'm_type'));

        $this->assertSame(null, MiscClass::getAnnotationAsList('not', 'type', null));
        $this->assertSame(null, MiscClass::getAnnotationAsList('not', 'm_type', null));
    }

    function test_getAnnotationAsHash()
    {
        $this->assertSame([
            ['int', '$a', 'this', 'is', 'a', 'value'],
            ['int|string|null', '$b', 'this', 'is', 'b', 'value'],
            ['\\stdclass', '$c'],
        ], MiscClass::getAnnotationAsHash('param', [], 'f'));

        $this->assertSame([
            [
                2 => 'int',
                1 => '$a',
                0 => 'this is a value',
            ],
            [
                2 => 'int|string|null',
                1 => '$b',
                0 => 'this is b value',
            ],
            [
                2 => '\\stdclass',
                1 => '$c',
                0 => null,
            ],
        ], MiscClass::getAnnotationAsHash('param', [2, 1, 0], 'f'));

        $this->assertSame([
            '$a' => [
                'type'    => 'int',
                'comment' => 'this is a value',
            ],
            '$b' => [
                'type'    => 'int|string|null',
                'comment' => 'this is b value',
            ],
            '$c' => [
                'type'    => '\\stdclass',
                'comment' => null,
            ],
        ], MiscClass::getAnnotationAsHash('param', ['type', null, 'comment'], 'f'));

        $this->assertSame([
            '$a' => [
                'type'    => 'int',
                'comment' => 'this',
                'a'       => 'is',
                'b'       => 'a',
                'c'       => 'value',
                'd'       => null,
            ],
            '$b' => [
                'type'    => 'int|string|null',
                'comment' => 'this',
                'a'       => 'is',
                'b'       => 'b',
                'c'       => 'value',
                'd'       => null,
            ],
            '$c' => [
                'type'    => '\\stdclass',
                'comment' => null,
                'a'       => null,
                'b'       => null,
                'c'       => null,
                'd'       => null,
            ],
        ], MiscClass::getAnnotationAsHash('param', ['type', null, 'comment', 'a', 'b', 'c', 'd'], 'f'));

        $this->assertSame(null, MiscClass::getAnnotationAsHash('not', [], 'type', null));
        $this->assertSame(null, MiscClass::getAnnotationAsHash('not', [], 'm_type', null));
    }

    function test_inherit()
    {
        /// 自身のもの
        $this->assertEquals('AncestorClass', AncestorClass::getAnnotationAsString('class'));
        $this->assertEquals('ParentClass', ParentClass::getAnnotationAsString('class'));
        $this->assertEquals('ChildClass', ChildClass::getAnnotationAsString('class'));

        // 継承ツリーを辿る
        // inherit1 は AncestorClass にしか無いので全部同じ
        $this->assertEquals('AncestorClass::inherit1', AncestorClass::getAnnotationAsString('inherit1'));
        $this->assertEquals('AncestorClass::inherit1', ParentClass::getAnnotationAsString('inherit1'));
        $this->assertEquals('AncestorClass::inherit1', ChildClass::getAnnotationAsString('inherit1'));
        // inherit2 は ParentClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('inherit2'));
        $this->assertEquals('ParentClass::inherit2', ParentClass::getAnnotationAsString('inherit2'));
        $this->assertEquals('ParentClass::inherit2', ChildClass::getAnnotationAsString('inherit2'));
        // inherit3 は ChildClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('inherit3'));
        $this->assertEquals(null, ParentClass::getAnnotationAsString('inherit3'));
        $this->assertEquals('ChildClass::inherit3', ChildClass::getAnnotationAsString('inherit3'));
    }

    function test_inherit_member()
    {
        /// 自身のもの
        $this->assertEquals('AncestorClass::method', AncestorClass::getAnnotationAsString('method', 'm'));
        $this->assertEquals('ParentClass::method', ParentClass::getAnnotationAsString('method', 'm'));
        $this->assertEquals('ChildClass::method', ChildClass::getAnnotationAsString('method', 'm'));

        /// 継承のもの（一足飛び）
        $this->assertEquals('AncestorClass::method', AncestorClass::getAnnotationAsString('method', 'n'));
        $this->assertEquals('ChildClass::method', ChildClass::getAnnotationAsString('method', 'n'));

        /// 継承のもの
        // method1 は AncestorClass にしか無いので全部同じ
        $this->assertEquals('AncestorClass::method1', AncestorClass::getAnnotationAsString('method1', 'm'));
        $this->assertEquals('AncestorClass::method1', ParentClass::getAnnotationAsString('method1', 'm'));
        $this->assertEquals('AncestorClass::method1', ChildClass::getAnnotationAsString('method1', 'm'));
        // method2 は ParentClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('method2', 'm'));
        $this->assertEquals('ParentClass::method2', ParentClass::getAnnotationAsString('method2', 'm'));
        $this->assertEquals('ParentClass::method2', ChildClass::getAnnotationAsString('method2', 'm'));
        // method3 は ChildClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('method3', 'm'));
        $this->assertEquals(null, ParentClass::getAnnotationAsString('method3', 'm'));
        $this->assertEquals('ChildClass::method3', ChildClass::getAnnotationAsString('method3', 'm'));

        /// class のもの
        // inherit1 は AncestorClass にしか無いので全部同じ
        $this->assertEquals('AncestorClass::inherit1', AncestorClass::getAnnotationAsString('inherit1', 'm'));
        $this->assertEquals('AncestorClass::inherit1', ParentClass::getAnnotationAsString('inherit1', 'm'));
        $this->assertEquals('AncestorClass::inherit1', ChildClass::getAnnotationAsString('inherit1', 'm'));
        // inherit2 は ParentClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('method2', 'm'));
        $this->assertEquals('ParentClass::inherit2', ParentClass::getAnnotationAsString('inherit2', 'm'));
        $this->assertEquals('ParentClass::inherit2', ChildClass::getAnnotationAsString('inherit2', 'm'));
        // inherit3 は ChildClass にしか無いのでそれ以下が同じ
        $this->assertEquals(null, AncestorClass::getAnnotationAsString('inherit3', 'm'));
        $this->assertEquals(null, ParentClass::getAnnotationAsString('inherit3', 'm'));
        $this->assertEquals('ChildClass::inherit3', ChildClass::getAnnotationAsString('inherit3', 'm'));

        // 固有＋ class 継承のもの
        $this->assertEquals('AncestorClass::inherit1', ChildClass::getAnnotationAsString('inherit1', 'l'));
    }
}

/**
 * @multiple multiple1
 * @multiple multiple2
 */
class MiscClass
{
    use Annotatable;

    /**
     * @prop p-multiple1
     * @prop p-multiple2
     */
    public $c;

    /**
     * @param int $a this is a value
     * @param int|string|null $b this is b value
     * @param \stdclass $c
     */
    function f($a, $b, $c) { }

    /**
     * @param $a
     * @method m-multiple1
     * @method m-multiple2
     */
    function m($a) { }

    /**
     * @bool off
     * @int 123
     * @string this is annotation comment
     * @list a b c
     */
    function type() { }

    /**
     * @bool true
     * @bool off
     * @int 123
     * @int 456
     * @string this is annotation comment1
     * @string this is annotation comment2
     * @list a b c
     * @list d e f
     */
    function m_type() { }
}

/**
 * @class AncestorClass
 * @inherit1 AncestorClass::inherit1
 */
class AncestorClass
{
    use Annotatable;

    /**
     * @method AncestorClass::method
     * @method1 AncestorClass::method1
     */
    function m() { }

    /**
     * @method AncestorClass::method
     */
    function n() { }
}

/**
 * @class ParentClass
 * @inherit2 ParentClass::inherit2
 */
class ParentClass extends AncestorClass
{
    /**
     * @method ParentClass::method
     * @method2 ParentClass::method2
     */
    function m() { }
}

/**
 * @class ChildClass
 * @inherit3 ChildClass::inherit3
 */
class ChildClass extends ParentClass
{
    function l() { }

    /**
     * @method ChildClass::method
     * @method3 ChildClass::method3
     */
    function m() { }

    /**
     * @method ChildClass::method
     */
    function n() { }

    /**
     * @multiple ChildClass::method1,ChildClass::method2
     * @multiple ChildClass::method3
     */
    function v() { }
}
