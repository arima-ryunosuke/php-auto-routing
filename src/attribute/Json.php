<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Json extends AbstractAttribute
{
    private int $jsonOptions;

    public function __construct(int $jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
    {
        $this->jsonOptions = $jsonOptions;
    }

    public function merge(array &$result)
    {
        $result[] = $this->jsonOptions;
    }
}
