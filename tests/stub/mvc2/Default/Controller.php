<?php
namespace ryunosuke\Test\stub\mvc2\Default;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
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
