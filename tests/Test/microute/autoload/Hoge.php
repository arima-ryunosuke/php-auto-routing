<?php
namespace ryunosuke\Test\microute\autoload;

class Hoge
{
    public static $newCount = 0;

    public $ctor_args;

    public function __construct()
    {
        self::$newCount++;
        $this->ctor_args = func_get_args();
    }
}
