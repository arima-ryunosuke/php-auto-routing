<?php
namespace ryunosuke\microute\mixin;

/**
 * private フィールドを readonly にするトレイト
 */
trait Accessable
{
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \DomainException(get_class($this) . "::$name is undefined.");
    }
}
