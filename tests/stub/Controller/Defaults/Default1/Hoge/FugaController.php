<?php
namespace ryunosuke\Test\stub\Controller\Defaults\Default1\Hoge;

class FugaController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function piyoAction()
    {
        return $this->location();
    }
}
