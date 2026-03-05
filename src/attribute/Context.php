<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Context extends AbstractAttribute
{
    private array $allow_extensions;

    public function __construct(string ...$allow_extensions)
    {
        $this->allow_extensions = $allow_extensions;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, $this->allow_extensions);
    }

    public static function checkContext(array $contexts, Request $request): string
    {
        if (!in_array('*', $contexts, true) && !preg_grep('#^' . $request->attributes->get('context') . '$#i', $contexts)) {
            return "not allow '{$request->attributes->get('context')}' context.";
        }
        return "";
    }
}
