<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

class FooBarController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionTestAction() { }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function thrownAction()
    {
        throw new \UnexpectedValueException('un');
    }

    public function noAction() { }

    public function error(\Exception $ex)
    {
        return null;
    }
}
