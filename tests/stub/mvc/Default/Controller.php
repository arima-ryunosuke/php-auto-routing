<?php
namespace ryunosuke\Test\stub\mvc\Default;

use ryunosuke\microute\attribute\TrailingSlash;
use ryunosuke\Test\stub\mvc\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[\ryunosuke\microute\attribute\DefaultSlash]
class Controller extends AbstractController
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

    #[TrailingSlash(true)]
    public function endSlashAction()
    {
        return $this->location();
    }

    #[TrailingSlash(false)]
    public function noendSlashAction()
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
