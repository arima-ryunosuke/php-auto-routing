<?php
namespace ryunosuke\Test\stub\mvc\SubSub\Default;

use ryunosuke\Test\stub\mvc\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[\ryunosuke\microute\attribute\Scope('(?<id>[0-9]+)/')]
class Controller extends AbstractController
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
