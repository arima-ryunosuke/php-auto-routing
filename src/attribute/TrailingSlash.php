<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class TrailingSlash extends AbstractAttribute
{
    private bool $required;
    private int  $status;

    public function __construct(bool $required, int $status = 308)
    {
        $this->required = $required;
        $this->status = $status;
    }

    public function merge(array &$result)
    {
        $result[] = [$this->required, $this->status];
    }
}
