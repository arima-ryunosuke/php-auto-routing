<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Argument extends AbstractAttribute
{
    private array $argumets;

    public function __construct(string ...$argumets)
    {
        $this->argumets = $argumets;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, array_map(fn($v) => strtoupper($v), $this->argumets));
    }
}
