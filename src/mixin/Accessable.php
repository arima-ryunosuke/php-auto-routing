<?php
namespace ryunosuke\microute\mixin;

/**
 * private フィールドを readonly にするトレイト
 *
 * $service で Sevice フィールドがあることが前提。
 */
trait Accessable
{
    public function __isset($name)
    {
        return isset($this->$name) || isset($this->service->$name);
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        if (isset($this->service->$name)) {
            return $this->service->$name;
        }
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }

    public function __set($name, $value)
    {
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }
}
