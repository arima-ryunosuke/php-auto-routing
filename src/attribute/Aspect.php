<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
abstract class Aspect extends AbstractAttribute
{
    public array $context = [];

    public function merge(array &$result)
    {
        $result[] = $this;
    }

    public function enter() { }

    public function try() { }

    public function action(\Closure $invoke) { }

    public function done() { }

    public function catch() { }

    public function finally() { }

    public function return() { }
}
