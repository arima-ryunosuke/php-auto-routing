<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Scope extends AbstractAttribute
{
    private string $scope_regex;

    public function __construct(string $scope_regex)
    {
        $this->scope_regex = $scope_regex;
    }

    public function merge(array &$result)
    {
        $result[$this->scope_regex] = [];
    }
}
