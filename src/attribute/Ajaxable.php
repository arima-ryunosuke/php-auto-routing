<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

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

    public static function checkAjax(?int $ajaxable, Request $request): string
    {
        if ($ajaxable !== null && !$request->isAsynchronous()) {
            return "only accepts AsynchronousRequest.";
        }
        return "";
    }
}
