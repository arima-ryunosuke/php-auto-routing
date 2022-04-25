<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Queryable extends AbstractAttribute
{
    private bool $queryable;

    public function __construct(bool $queryable = true)
    {
        $this->queryable = $queryable;
    }

    public function merge(array &$result)
    {
        $result[] = $this->queryable;
    }
}
