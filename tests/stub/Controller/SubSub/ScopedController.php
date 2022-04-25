<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

#[\ryunosuke\microute\attribute\Scope('(?<type>[a-z]+)/')]
class ScopedController extends \ryunosuke\Test\stub\Controller\AbstractController
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
