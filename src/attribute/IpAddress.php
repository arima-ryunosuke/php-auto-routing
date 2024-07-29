<?php
namespace ryunosuke\microute\attribute;

use Attribute;

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
}
