<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Cache extends AbstractAttribute
{
    private int $cache_seconds;

    public function __construct(int $cache_seconds = 60)
    {
        $this->cache_seconds = $cache_seconds;
    }

    public function merge(array &$result)
    {
        $result[] = $this->cache_seconds;
    }
}
