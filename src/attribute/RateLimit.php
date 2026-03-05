<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use Symfony\Component\HttpFoundation\IpUtils;
use ryunosuke\microute\http\Request;

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

    public static function getRate(array $ratelimit, Request $request): array
    {
        $result = [];
        foreach ($ratelimit['request_keys'] as [$property, $key]) {
            if ($property === 'ip') {
                $value = $request->getClientIp();
                if ($key !== '' && $key !== '*' && !IpUtils::checkIp($value, $key)) {
                    return [];
                }
            }
            else {
                $value = $request->{$property}->get($key);
                if (!is_scalar($value) || strlen($value) > 64) {
                    return [];
                }
            }
            $result["$property:$key"] = $value;
        }
        return $result;
    }

    public static function checkLimit(array $ratelimit, array &$times): ?int
    {
        $now = microtime(true);
        $times[] = $now;
        $count = count($times);

        if (($now - $times[0]) <= $ratelimit['second'] && $count > $ratelimit['count']) {
            return intval($ratelimit['second'] - ($now - $times[0])) + 1;
        }

        $times = array_slice($times, max(0, $count - $ratelimit['count']));
        return null;
    }
}
