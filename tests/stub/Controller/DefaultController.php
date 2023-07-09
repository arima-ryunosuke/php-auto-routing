<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function defaultAction()
    {
        return $this->location();
    }

    public function indexAction()
    {
        return $this->location();
    }

    public function errorAction(\Throwable $t)
    {
        if ($t instanceof \DomainException) {
            throw $t;
        }

        return new Response(get_class($t));
    }
}
