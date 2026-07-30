<?php

namespace example\application\attribute;

use Attribute;
use ryunosuke\microute\attribute\Aspect;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AspectDemo extends Aspect
{
    public function enter()
    {
        echo __FUNCTION__, "\n";
    }

    public function try()
    {
        echo __FUNCTION__, "\n";
    }

    public function action(\Closure $invoke)
    {
        return $invoke(...$this->context['arguments']) . '-appedix';
    }

    public function done()
    {
        echo __FUNCTION__, "\n";
    }

    public function catch()
    {
        echo __FUNCTION__, "\n";
    }

    public function finally()
    {
        echo __FUNCTION__, "\n";
    }

    public function return()
    {
        echo __FUNCTION__, "\n";
    }
}
