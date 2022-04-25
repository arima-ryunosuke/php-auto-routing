<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Redirect extends AbstractAttribute
{
    private string $from_url;
    private int    $status_code;

    public function __construct(string $from_url, $status_code = 302)
    {
        $this->from_url = $from_url;
        $this->status_code = $status_code;
    }

    public function merge(array &$result)
    {
        $result[$this->from_url] = [
            'status' => $this->status_code,
        ];
    }
}
