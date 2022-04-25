<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Method extends AbstractAttribute
{
    private array $allow_method;

    public function __construct(string ...$allow_method)
    {
        $this->allow_method = $allow_method;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, array_map(fn($v) => strtoupper($v), $this->allow_method));
    }
}
