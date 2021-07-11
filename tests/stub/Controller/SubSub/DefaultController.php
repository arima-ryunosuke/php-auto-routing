<?php
namespace ryunosuke\Test\stub\Controller\SubSub;

use Symfony\Component\HttpFoundation\Response;

/**
 * @scope (?<id>[0-9]+)/
 */
class DefaultController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function indexAction($id)
    {
        return "index_action: $id";
    }

    public function errorAction(\Exception $ex)
    {
        return new Response(__METHOD__);
    }
}
