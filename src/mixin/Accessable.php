<?php
namespace ryunosuke\microute\mixin;

/**
 * private フィールドを readonly にするトレイト
 */
trait Accessable
{
    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }

    public function __set($name, $value)
    {
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }
}
