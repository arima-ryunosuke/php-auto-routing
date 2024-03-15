<?php
namespace ryunosuke\microute\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RateLimit extends AbstractAttribute
{
    private int   $count;
    private int   $second;
    private array $request_keys = [];

    public function __construct(int $count, int $second, $request_keys = 'ip')
    {
        $this->count = $count;
        $this->second = $second;

        foreach ((array) $request_keys as $request_key) {
            [$request, $key] = explode(':', "$request_key:");
            $this->request_keys[] = [strtolower(trim($request)), trim($key)];
        }
    }

    public function merge(array &$result)
    {
        $result[] = [
            'count'        => $this->count,
            'second'       => $this->second,
            'request_keys' => $this->request_keys,
        ];
    }
}
