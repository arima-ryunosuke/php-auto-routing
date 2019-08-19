<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

use Symfony\Component\HttpFoundation\Response;

class DefaultController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function indexAction()
    {
        return "index_action";
    }

    public function errorAction(\Exception $ex)
    {
        return Response::create(__METHOD__);
    }
}
