<?php
namespace ryunosuke\Test\stub\mvc\Defaults\Default2\Hoge\Fuga\Piyo\Default;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
{
    public function defaultAction()
    {
        return $this->location();
    }
}
