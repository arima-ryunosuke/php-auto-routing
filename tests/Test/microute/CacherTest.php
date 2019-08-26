<?php
namespace ryunosuke\Test\microute;

use ryunosuke\microute\Cacher;

class CacherTest extends \ryunosuke\Test\AbstractTestCase
{
    /** @var Cacher */
    protected $cacher;

    function setUp()
    {
        // overrite parent (because don't use $service)

        $this->cacher = new Cacher(__DIR__ . '/../../tmp');
        $this->cacher->clear();
    }

    function test_save()
    {
        $cacher = new Cacher(__DIR__ . '/../../tmp');
        $cacher->set('hoge', 'actual');
        $cacher->clear();
        $this->assertFileNotExists(__DIR__ . '/../../tmp/all.cache');
        unset($cacher);
        $this->assertFileExists(__DIR__ . '/../../tmp/all.cache');
    }

    function test_has_get_set_delete()
    {
        $this->assertEquals(false, $this->cacher->has('hoge'));

        $this->assertEquals('default', $this->cacher->get('hoge', 'default'));

        $this->assertEquals(true, $this->cacher->set('hoge', 'actual'));
        $this->assertEquals(true, $this->cacher->has('hoge'));
        $this->assertEquals('actual', $this->cacher->get('hoge', 'default'));

        $this->assertEquals(true, $this->cacher->delete('hoge'));
        $this->assertEquals(false, $this->cacher->has('hoge'));
        $this->assertEquals('default', $this->cacher->get('hoge', 'default'));

        $this->assertEquals(false, $this->cacher->delete('fuga'));
    }

    function test_set_ttl()
    {
        $this->assertEquals(true, $this->cacher->set('hoge', 'actual', 2));
        $this->assertEquals(true, $this->cacher->set('fuga', 'actual', \DateInterval::createFromDateString('+2 seconds')));

        sleep(1);

        $this->assertEquals(true, $this->cacher->has('hoge'));
        $this->assertEquals('actual', $this->cacher->get('fuga', 'default'));

        sleep(1);

        $this->assertEquals(false, $this->cacher->has('hoge'));
        $this->assertEquals('default', $this->cacher->get('fuga', 'default'));
        $this->assertEquals(false, $this->cacher->delete('hoge'));
        $this->assertEquals(false, $this->cacher->delete('fuga'));
    }

    function test_get_set_delete_multiple()
    {
        $this->assertEquals([
            'hoge' => 'default',
            'fuga' => 'default',
            'piyo' => 'default',
        ], $this->cacher->getMultiple(['hoge', 'fuga', 'piyo'], 'default'));

        $this->assertEquals(true, $this->cacher->setMultiple([
            'hoge' => 'HOGE',
            'fuga' => 'FUGA',
            'piyo' => 'PIYO',
        ]));

        $this->assertEquals([
            'hoge' => 'HOGE',
            'fuga' => 'FUGA',
            'piyo' => 'PIYO',
        ], $this->cacher->getMultiple(['hoge', 'fuga', 'piyo'], 'default'));

        $this->assertEquals(true, $this->cacher->deleteMultiple(['fuga', 'piyo']));

        $this->assertEquals([
            'hoge' => 'HOGE',
            'fuga' => 'default',
            'piyo' => 'default',
        ], $this->cacher->getMultiple(['hoge', 'fuga', 'piyo'], 'default'));

        if (DIRECTORY_SEPARATOR === '/') {
            $this->markTestSkipped('this test is windows only.');
        }

        $this->assertEquals([
            'hoge' => 'HOGE',
            'fuga' => 'default',
            'piyo' => 'default',
        ], $this->cacher->getMultiple(['hoge', 'fuga', 'piyo'], 'default'));

        $this->assertEquals(false, $this->cacher->deleteMultiple(['fuga', 'piyo']));

        $this->assertEquals([
            'hoge' => 'HOGE',
            'fuga' => 'default',
            'piyo' => 'default',
        ], $this->cacher->getMultiple(['hoge', 'fuga', 'piyo'], 'default'));
    }

    function test_clear()
    {
        $this->assertEquals(true, $this->cacher->set('hoge', 'HOGE'));
        $this->assertEquals(true, $this->cacher->set('fuga', 'FUGA'));
        $this->assertEquals(true, $this->cacher->clear());
    }
}
