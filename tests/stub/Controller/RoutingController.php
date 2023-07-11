<?php
namespace ryunosuke\Test\stub\Controller;

#[\ryunosuke\microute\attribute\DefaultRoute(false)]
#[\ryunosuke\microute\attribute\Queryable(false)]
class RoutingController extends AbstractController
{
    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\DefaultRoute(true)]
    public function defaultOnAction()
    {
        return 'defaultOn';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\DefaultRoute(false)]
    #[\ryunosuke\microute\attribute\Rewrite('/routing/custom-on')]
    public function defaultOffAction()
    {
        return 'defaultOff';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function defaultdefaultAction()
    {
        return 'defaultdefaul';
    }

    public function queryableDefault(int $param)
    {
        return 'queryableDefault' . $param;
    }

    #[\ryunosuke\microute\attribute\Queryable(true)]
    public function queryableTrue(int $param)
    {
        return 'queryableTrue' . $param;
    }

    #[\ryunosuke\microute\attribute\Queryable(false)]
    public function queryableFalse(int $param)
    {
        return 'queryableFalse' . $param;
    }
}
