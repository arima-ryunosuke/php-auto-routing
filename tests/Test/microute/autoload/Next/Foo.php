<?php
namespace ryunosuke\Test\microute\autoload\Next;

class Foo
{
    public static $newCount = 0;

    public $ctor_args;

    public function __construct()
    {
        self::$newCount++;
        $this->ctor_args = func_get_args();
    }
}
