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

    public function __construct(string|array ...$allow_method)
    {
        $this->allow_method = [];

        foreach ($allow_method as $methods) {
            foreach ((array) $methods as $method => $allow) {
                if (is_int($method)) {
                    $method = $allow;
                    $allow = true;
                }

                if (strtolower($method) === '@safe') {
                    $this->allow_method['HEAD'] = $allow;
                    $this->allow_method['OPTIONS'] = $allow;
                    $this->allow_method['GET'] = $allow;
                    $this->allow_method['*'] = !$allow;
                }
                elseif (strtolower($method) === '@unsafe') {
                    $this->allow_method['HEAD'] = !$allow;
                    $this->allow_method['OPTIONS'] = !$allow;
                    $this->allow_method['GET'] = !$allow;
                    $this->allow_method['*'] = $allow;
                }
                else {
                    $this->allow_method[strtoupper($method)] = $allow;
                }
            }
        }
    }

    public function merge(array &$result)
    {
        $result = array_replace($result, $this->allow_method);
    }

    public static function checkMethod(array $methods, Request $request): string
    {
        // そもそも Method 属性がない場合は全許可
        if (!$methods) {
            return "";
        }
        // 明示されてるならそれに従う
        if (isset($methods[$request->getMethod()])) {
            $allowed = $methods[$request->getMethod()];
        }
        // 明示されてないなら * に従う
        else {
            $allowed = $methods['*'] ?? false;
        }
        return $allowed ? "" : "not allow {$request->getMethod()} method.";
    }

    public static function getArguments(array $methods, Request $request): array
    {
        if (!$methods) {
            $methods = ['*' => true];
        }

        $result = [];

        foreach ($methods as $method => $allow) {
            if ($allow) {
                foreach (self::ACTION_MAP[$method] ?? [] as $source) {
                    $result += $request->$source->all();
                }
            }
        }

        return $result;
    }
}
