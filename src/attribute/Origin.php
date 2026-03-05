<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Origin extends AbstractAttribute
{
    private array $origins;

    public function __construct(string ...$origins)
    {
        $this->origins = $origins;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, $this->origins);
    }

    public static function checkOrigin(array $origins, Request $request): string
    {
        if (!($origins && !$request->isMethodSafe())) {
            return "";
        }

        $origin = $request->headers->get('origin') ?? '';
        if (!strlen($origin)) {
            return "";
        }

        foreach ($origins as $allowed) {
            if (fnmatch($allowed, $origin)) {
                return "";
            }
        }
        return "$origin is not allowed Origin.";
    }
}
