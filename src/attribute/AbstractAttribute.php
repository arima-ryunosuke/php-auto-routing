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

        $flags = (new \ReflectionClass(static::class))->isAbstract() ? ReflectionAttribute::IS_INSTANCEOF : 0;

        $attributes = [];
        $noInheritances = [];
        do {
            if ($methodname !== null && $refclass->hasMethod($methodname)) {
                $refmethod = $refclass->getMethod($methodname);
                $attributes = array_merge($attributes, $refmethod->getAttributes(static::class, $flags));

                $noInheritances = array_merge($noInheritances, array_map(fn($ni) => [count($attributes), $ni], $refmethod->getAttributes(NoInheritance::class)));
            }
            $attributes = array_merge($attributes, $refclass->getAttributes(static::class, $flags));

            $noInheritances = array_merge($noInheritances, array_map(fn($ni) => [count($attributes), $ni], $refclass->getAttributes(NoInheritance::class)));
        } while ($refclass = $refclass->getParentClass());

        $attributes = array_filter($attributes, function ($attribute, $n) use ($noInheritances) {
            foreach ($noInheritances as [$index, $noInheritance]) {
                if ($index <= $n) {
                    $targets = $noInheritance->getArguments();
                    if (!$targets) {
                        return false;
                    }
                    foreach ($targets as $target) {
                        if ($target === $attribute->getName()) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $result = [];
        foreach ($attributes as $attribute) {
            $attribute->newInstance()->merge($result);
        }
        return $result;
    }

    abstract public function merge(array &$result);
}
