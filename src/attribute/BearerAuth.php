<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class BearerAuth extends AbstractAttribute
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

    public static function authenticate(Request $request, callable $provider): array
    {
        $authorization = $request->headers->get('Authorization') ?? '';
        if (preg_match('#Bearer\s(\S+)#i', $authorization, $matches)) {
            $username = $provider($matches[1]);
            if ($username === null) {
                return [null, true];
            }
            return [$username, null];
        }
        return [null, false];
    }

    public static function getHeader(string $realm, bool $exists): string
    {
        return sprintf('Bearer realm="%s", error="%s"', $realm, $exists ? 'invalid_token' : 'token_required');
    }
}
