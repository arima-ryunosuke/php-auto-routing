<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DigestAuth extends AbstractAttribute
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

    public static function authenticate(string $realm, Request $request, callable $provider, callable $noncer): ?string
    {
        $md5implode = static fn($_) => md5(implode(':', func_get_args()));
        $keys = ['response', 'nonce', 'nc', 'cnonce', 'qop', 'uri', 'username'];

        $digest = $request->server->get('PHP_AUTH_DIGEST') ?? '';

        preg_match_all('@(' . implode('|', $keys) . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);
        $data = array_reduce($matches, static function ($data, $m) {
            $data[$m[1]] = $m[3] ?: $m[4];
            return $data;
        }, array_fill_keys($keys, ''));

        $ncount = $noncer($data['nonce']);
        $ncount = $ncount === null ? $data['nc'] : sprintf('%08x', $ncount);

        $username = $data['username'];
        $password = $provider($username);
        $response = $md5implode(
            $md5implode($username, $realm, $password),
            $data['nonce'],
            $ncount,
            $data['cnonce'],
            $data['qop'],
            $md5implode($request->getMethod(), $request->getRequestUri()),
        );
        return hash_equals($response, $data['response']) ? $data['username'] : null;
    }

    public static function getHeader(string $realm, callable $noncer): string
    {
        return sprintf('Digest realm="%s", nonce="%s", algorithm=MD5, qop="auth"', $realm, $noncer(null));
    }
}
