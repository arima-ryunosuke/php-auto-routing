<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Ajaxable extends AbstractAttribute
{
    private int $response_code;

    public function __construct(int $response_code = 400)
    {
        $this->response_code = $response_code;
    }

    public function merge(array &$result)
    {
        $result[] = $this->response_code;
    }
}
