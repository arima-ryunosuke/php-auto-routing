<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use Symfony\Component\HttpFoundation\IpUtils;
use ryunosuke\microute\http\Request;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IpAddress extends AbstractAttribute
{
    private array $addresses;
    private bool  $defaultDeny;

    public function __construct(array $addresses, bool $defaultDeny = true)
    {
        $this->addresses = $addresses;
        $this->defaultDeny = $defaultDeny;
    }

    public function merge(array &$result)
    {
        $result[] = [
            'addresses'   => $this->addresses,
            'defaultDeny' => $this->defaultDeny,
        ];
    }

    public static function checkIpAddress(array $addresses, Request $request): string
    {
        foreach ($addresses as $rule) {
            $matched = IpUtils::checkIp($request->getClientIp(), $rule['addresses']);
            if (($rule['defaultDeny'] && !$matched) || (!$rule['defaultDeny'] && $matched)) {
                $result = $rule['defaultDeny'] ? 'not allowed' : 'denied';
                return "$result from {$request->getClientIp()}.";
            }
        }
        return "";
    }
}
