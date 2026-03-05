<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Argument extends AbstractAttribute
{
    const ARGUMENT_MAP = [
        'GET'    => ['query'],
        'POST'   => ['request'],
        'FILE'   => ['files'],
        'COOKIE' => ['cookies'],
        'ATTR'   => ['attributes'],
    ];

    private array $argumets;

    public function __construct(string ...$argumets)
    {
        $this->argumets = $argumets;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, array_map(fn($v) => strtoupper($v), $this->argumets));
    }

    public static function getArguments(array $arguments, Request $request): array
    {
        $result = [];

        foreach ($arguments as $argument) {
            foreach (self::ARGUMENT_MAP[$argument] ?? [] as $source) {
                $result += $request->$source->all();
            }
        }

        return $result;
    }
}
