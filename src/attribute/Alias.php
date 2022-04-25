<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Alias extends AbstractAttribute
{
    private string $alias_prefix;

    public function __construct(string $alias_prefix)
    {
        $this->alias_prefix = $alias_prefix;
    }

    public function merge(array &$result)
    {
        $result[$this->alias_prefix] = [];
    }
}
