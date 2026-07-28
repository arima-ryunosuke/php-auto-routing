<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Callback extends AbstractAttribute
{
    private mixed $callback;

    public function __construct(mixed $callback)
    {
        $this->callback = $callback;
    }

    public function merge(array &$result)
    {
        $result[] = $this->callback;
    }
}
