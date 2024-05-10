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
                $attributes = array_merge($attributes, $refmethod->getAttributes(static::class));

                if ($ifbreak($refmethod->getAttributes(NoInheritance::class)[0] ?? null)) {
                    break;
                }
            }
            $attributes = array_merge($attributes, $refclass->getAttributes(static::class));

            if ($ifbreak($refclass->getAttributes(NoInheritance::class)[0] ?? null)) {
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
