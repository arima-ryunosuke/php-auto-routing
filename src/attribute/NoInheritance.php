<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class NoInheritance
{
    /**
     * @codeCoverageIgnore
     */
    public function __construct(string ...$attribute_name)
    {
        // This constructor is only for type hints
        // This attribute is never instantiated
    }
}
