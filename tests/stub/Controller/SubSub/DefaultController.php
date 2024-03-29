<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

use Symfony\Component\HttpFoundation\Response;

#[\ryunosuke\microute\attribute\Scope('(?<id>[0-9]+)/')]
class DefaultController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function indexAction($id)
    {
        return "index_action: $id";
    }

    public function errorAction(\Throwable $t)
    {
        return new Response(__METHOD__);
    }
}
