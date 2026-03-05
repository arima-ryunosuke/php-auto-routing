<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Method extends AbstractAttribute
{
    const ACTION_MAP = [
        'GET'    => ['query', 'attributes'],                     // GET で普通は body は来ない
        'POST'   => ['request', 'files', 'query', 'attributes'], // POST はかなり汎用的なのですべて見る
        'PUT'    => ['request', 'query', 'attributes'],          // PUT は body が単一みたいなもの（symfony が面倒見てくれてる）
        'DELETE' => ['query', 'attributes'],                     // DELETE で普通は body は来ない
        '*'      => ['query', 'request', 'files', 'attributes'], // 全部
    ];

    private array $allow_method;

    public function __construct(string ...$allow_method)
    {
        $this->allow_method = $allow_method;
    }

    public function merge(array &$result)
    {
        $result = array_merge($result, array_map(fn($v) => strtoupper($v), $this->allow_method));
    }

    public static function checkMethod(array $methods, Request $request): string
    {
        if ($methods && !preg_grep('#^' . $request->getMethod() . '$#i', $methods)) {
            return "not allow {$request->getMethod()} method.";
        }
        return "";
    }

    public static function getArguments(array $methods, Request $request): array
    {
        $result = [];

        foreach ($methods as $action) {
            foreach (self::ACTION_MAP[$action] ?? [] as $source) {
                $result += $request->$source->all();
            }
        }

        return $result;
    }
}
