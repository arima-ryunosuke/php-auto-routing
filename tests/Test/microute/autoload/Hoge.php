<?php
namespace ryunosuke\Test\microute\autoload;

class Hoge
{
    public static $newCount = 0;

    public function __construct()
    {
        self::$newCount++;
    }
}
