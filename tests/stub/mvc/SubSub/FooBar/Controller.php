<?php
namespace ryunosuke\Test\stub\mvc\SubSub\FooBar;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionTestAction()
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function thrownAction()
    {
        throw new \UnexpectedValueException('un');
    }

    public function noAction() { }

    public function catch(\Throwable $t)
    {
        return null;
    }
}
