<?php
namespace ryunosuke\Test\stub\Controller2;

class DefaultController extends AbstractController
{
    public function defaultAction()
    {
        return $this->location() . "2";
    }

    public function hogeAction()
    {
        return 'hoge2';
    }
}
