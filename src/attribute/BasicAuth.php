<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class BasicAuth extends AbstractAttribute
{
    private string $realm;

    public function __construct(string $realm = 'Enter username and password')
    {
        $this->realm = $realm;
    }

    public function merge(array &$result)
    {
        $result[] = [
            'realm' => $this->realm,
        ];
    }

    public static function authenticate(Request $request, callable $provider, callable $comparator): ?string
    {
        $username = $request->server->get('PHP_AUTH_USER');
        $password = $request->server->get('PHP_AUTH_PW');
        return $comparator($provider($username) ?? '', $password ?? '') ? $username : null;
    }

    public static function getHeader(string $realm): string
    {
        return sprintf('Basic realm="%s"', $realm);
    }
}
