<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Event extends AbstractAttribute
{
    private string $event_name;
    private array  $args;

    public function __construct(string $event_name, ...$args)
    {
        $this->event_name = $event_name;
        $this->args = $args;
    }

    public function merge(array &$result)
    {
        $result[$this->event_name] = $this->args;
    }
}
