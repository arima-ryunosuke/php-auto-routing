<?php

use ryunosuke\polyfill\attribute\Provider;

error_reporting(~E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

class MockLogger extends \Psr\Log\AbstractLogger
{
    private \Closure $callback;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function log($level, $message, array $context = [])
    {
        ($this->callback)($level, $message, $context);
    }
}

Provider::setCacheConfig(new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\PhpFilesAdapter()));
