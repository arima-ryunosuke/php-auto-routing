<?php
namespace ryunosuke\Test\stub\mvc\Defaults\Default1\Hoge\Fuga\Piyo;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
{
    public function defaultAction()
    {
        return $this->location();
    }
}
