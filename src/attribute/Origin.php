<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Origin extends AbstractAttribute
{
    private array $origins;

    public function __construct(string ...$origins)
    {
        $this->origins = $origins;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, $this->origins);
    }
}
