<?php
namespace ryunosuke\Test\stub\mvc\Defaults\Default;

use ryunosuke\Test\stub\mvc\AbstractController;

#[\ryunosuke\microute\attribute\DefaultSlash]
class Controller extends AbstractController
{
    public function defaultAction()
    {
        return $this->location();
    }
}
