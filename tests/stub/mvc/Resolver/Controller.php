<?php
namespace ryunosuke\Test\stub\mvc\Resolver;

use ryunosuke\Test\stub\mvc\AbstractController;

class Controller extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function action1Action(int $id)
    {
        return __FUNCTION__;
    }
}
