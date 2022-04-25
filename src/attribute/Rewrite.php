<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Rewrite extends AbstractAttribute
{
    private string $rewrite_url;

    public function __construct(string $rewrite_url)
    {
        $this->rewrite_url = $rewrite_url;
    }

    public function merge(array &$result)
    {
        $result[$this->rewrite_url] = [];
    }
}
