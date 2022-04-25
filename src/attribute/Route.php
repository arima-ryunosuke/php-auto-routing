<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route extends AbstractAttribute
{
    private string $route_name;

    public function __construct(string $route_name)
    {
        $this->route_name = $route_name;
    }

    public function merge(array &$result)
    {
        $result[$this->route_name] = [];
    }
}
