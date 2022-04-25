<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Context extends AbstractAttribute
{
    private array $allow_extensions;

    public function __construct(string ...$allow_extensions)
    {
        $this->allow_extensions = $allow_extensions;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, $this->allow_extensions);
    }
}
