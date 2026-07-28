<?php
namespace ryunosuke\Test\stub\mvc\Api;

use ryunosuke\microute\attribute\Callback;
use ryunosuke\microute\http\Request;
use ryunosuke\Test\stub\mvc\AbstractController;

#[Callback('self::route')]
class Controller extends AbstractController
{
    public static function route(Request $request)
    {
        $service = $request->attributes->get('@service');
        $parameters = $request->getPathParameters($service->resolver->url(self::class), true);
        if ($parameters === null) {
            return null;
        }

        return [
            'controller' => self::class,
            'action'     => strtolower(implode('_', array_keys($parameters))),
            'parameters' => array_values($parameters),
        ];
    }
}
