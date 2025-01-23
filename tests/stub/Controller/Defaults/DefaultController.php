<?php
namespace ryunosuke\Test\stub\Controller\Defaults;

#[\ryunosuke\microute\attribute\DefaultSlash]
class DefaultController extends \ryunosuke\Test\stub\Controller\AbstractController
{
    public function defaultAction()
    {
        return $this->location();
    }
}
