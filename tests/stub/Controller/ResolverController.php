<?php
namespace ryunosuke\Test\stub\Controller;

class ResolverController extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    public function action1Action(int $id) { return __FUNCTION__; }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Queryable(false)]
    public function action2Action(int $id) { return __FUNCTION__; }
}
