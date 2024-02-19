<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ReflectionAttribute;
use ReflectionMethod;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
abstract class AbstractAttribute
{
    public static function by($reflection)
    {
        $getAttributes = function ($reflection, ?string $name = null, int $flags = 0): array {
            if (method_exists($reflection, 'getAttributes')) {
                return $reflection->getAttributes($name, $flags); // @codeCoverageIgnore
            }

            if (class_exists(\ryunosuke\polyfill\attribute\Provider::class)) {
                static $provider = null;
                $provider ??= new \ryunosuke\polyfill\attribute\Provider();
                return $provider->getAttributes($reflection, $name, $flags);
            }

            throw new \LogicException("failed to getAttributes. require php >= 8.0 or polyfill-attribute"); // @codeCoverageIgnore
        };

        if ($reflection instanceof ReflectionMethod) {
            $methodname = $reflection->getName();
            $refclass = $reflection->getDeclaringClass();
        }
        else {
            $methodname = null;
            $refclass = $reflection;
        }

        $ifbreak = function (?ReflectionAttribute $noinheritance) {
            if ($noinheritance) {
                if (!$noinheritance->getArguments()) {
                    return true;
                }
                foreach ($noinheritance->getArguments() as $argument) {
                    if ($argument === static::class) {
                        return true;
                    }
                }
            }
            return false;
        };

        $attributes = [];
        do {
            if ($methodname !== null && $refclass->hasMethod($methodname)) {
                $refmethod = $refclass->getMethod($methodname);
                $attributes = array_merge($attributes, $getAttributes($refmethod, static::class));

                if ($ifbreak($getAttributes($refmethod, NoInheritance::class)[0] ?? null)) {
                    break;
                }
            }
            $attributes = array_merge($attributes, $getAttributes($refclass, static::class));

            if ($ifbreak($getAttributes($refclass, NoInheritance::class)[0] ?? null)) {
                break;
            }
        } while ($refclass = $refclass->getParentClass());

        $result = [];
        foreach ($attributes as $attribute) {
            $attribute->newInstance()->merge($result);
        }
        return $result;
    }

    abstract public function merge(array &$result);
}
