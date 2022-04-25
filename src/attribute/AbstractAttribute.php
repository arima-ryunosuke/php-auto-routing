<?php
namespace ryunosuke\microute\attribute;

use Attribute;
use ReflectionAttribute;
use ReflectionMethod;
use ryunosuke\polyfill\attribute\Provider;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
abstract class AbstractAttribute
{
    public static function by($reflection)
    {
        $provider = new Provider();

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
                $attributes = array_merge($attributes, $provider->getAttributes($refmethod, static::class));

                if ($ifbreak($provider->getAttribute($refmethod, NoInheritance::class))) {
                    break;
                }
            }
            $attributes = array_merge($attributes, $provider->getAttributes($refclass, static::class));

            if ($ifbreak($provider->getAttribute($refclass, NoInheritance::class))) {
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
