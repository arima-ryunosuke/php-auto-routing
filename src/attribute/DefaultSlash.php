<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DefaultSlash extends AbstractAttribute
{
    private bool $enable_default_slash;

    public function __construct(bool $enable_default_slash = true)
    {
        $this->enable_default_slash = $enable_default_slash;
    }

    public function merge(array &$result)
    {
        $result[] = $this->enable_default_slash;
    }
}
