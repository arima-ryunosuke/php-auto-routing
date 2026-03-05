<?php
namespace ryunosuke\Test\stub\mvc\Defaults\Default1\Hoge\Fuga;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
{
    public function piyoAction()
    {
        return $this->location();
    }
}
