<?php

error_reporting(~E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

class MockLogger extends \Psr\Log\AbstractLogger
{
    private \Closure $callback;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function log($level, $message, array $context = []): void
    {
        ($this->callback)($level, $message, $context);
    }
}
