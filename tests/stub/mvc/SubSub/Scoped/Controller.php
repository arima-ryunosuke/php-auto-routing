<?php
namespace ryunosuke\Test\stub\mvc\SubSub\Scoped;

use ryunosuke\Test\stub\mvc\AbstractController;

#[\ryunosuke\microute\attribute\Scope('(?<type>[a-z]+)/')]
class Controller extends AbstractController
{
    public function defaultAction($type)
    {
        return "default: $type";
    }

    public function hogeAction($type)
    {
        return "hoge: $type";
    }

    public function nullAction($type = null)
    {
        return "hoge: " . json_encode($type);
    }
}
