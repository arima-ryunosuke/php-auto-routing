<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DefaultRoute extends AbstractAttribute
{
    private bool $enable_default_route;

    public function __construct(bool $enable_default_route = true)
    {
        $this->enable_default_route = $enable_default_route;
    }

    public function merge(array &$result)
    {
        $result[] = $this->enable_default_route;
    }
}
