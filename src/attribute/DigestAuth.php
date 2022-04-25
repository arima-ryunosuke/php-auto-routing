<?php
namespace ryunosuke\microute\attribute;

use Attribute;

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
}
