<?php
namespace ryunosuke\Test\stub\Controller\Defaults\Default2\Hoge\Fuga;

class PiyoController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function defaultAction()
    {
        return $this->location();
    }
}
