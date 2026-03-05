<?php
namespace ryunosuke\Test\stub\mvc\Routing;

use ryunosuke\Test\stub\mvc\AbstractController;

#[\ryunosuke\microute\attribute\DefaultRoute(false)]
class Controller extends AbstractController
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
}
