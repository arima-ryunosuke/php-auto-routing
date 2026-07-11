<?php
namespace ryunosuke\Test\stub\mvc\Aspect;

use RuntimeException;
use ryunosuke\microute\attribute\NoInheritance;
use ryunosuke\microute\http\ThrowableResponse;
use ryunosuke\Test\stub\mvc\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[\Transaction]
#[\Logging]
class Controller extends AbstractController
{
    #[\Caching]
    public function cacheAction()
    {
        return uniqid("", true);
    }

    public function doneAction()
    {
        return new Response(__FUNCTION__);
    }

    public function catchAction()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    #[NoInheritance(\Transaction::class)]
    public function noneTransactionAction()
    {
        return new Response(__FUNCTION__);
    }

    #[NoInheritance(\Logging::class)]
    public function noneLoggingAction()
    {
        return new Response(__FUNCTION__);
    }

    #[NoInheritance(\Transaction::class, \Logging::class)]
    public function noneBothAction()
    {
        return new Response(__FUNCTION__);
    }

    protected function catch(\Throwable $t)
    {
        return new Response($t->getMessage());
    }
}
