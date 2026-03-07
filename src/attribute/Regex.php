<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Regex extends AbstractAttribute
{
    private string $regex;
    private bool   $slug;

    public function __construct(string $regex, bool $slug = false)
    {
        $this->regex = $regex;
        $this->slug = $slug;
    }

    public function merge(array &$result)
    {
        $result[$this->regex] = [
            'slug' => $this->slug,
        ];
    }
}
