<?php
namespace ryunosuke\Test\stub\Controller;

class ResolverController extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function action1Action(int $id)
    {
        return __FUNCTION__;
    }
}
