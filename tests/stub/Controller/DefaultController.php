<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    /**
     * @action get
     */
    public function defaultAction()
    {
        return $this->location();
    }

    public function indexAction()
    {
        return $this->location();
    }

    public function errorAction(\Exception $ex)
    {
        if ($ex instanceof \DomainException) {
            throw $ex;
        }

        return Response::create(get_class($ex));
    }
}
