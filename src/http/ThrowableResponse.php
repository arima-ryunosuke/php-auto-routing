<?php
namespace ryunosuke\microute\http;

use Exception;
use Symfony\Component\HttpFoundation;
use Throwable;

/**
 * @mixin HttpFoundation\Response
 */
class ThrowableResponse extends Exception implements Throwable
{
    private HttpFoundation\Response $response;

    public function __construct(HttpFoundation\Response $response)
    {
        parent::__construct(get_class($response), $response->getStatusCode());

        $this->response = $response;
    }

    public function __get(string $name): mixed
    {
        return $this->response->$name;
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->response->$name(...$arguments);
    }

    public function response(): HttpFoundation\Response
    {
        return $this->response;
    }
}
