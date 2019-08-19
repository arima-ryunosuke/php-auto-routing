<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

class FooBarController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    /**
     * @action get
     */
    public function actionTestAction() { }

    /**
     * @action get
     */
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
