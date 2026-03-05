<?php

error_reporting(~E_DEPRECATED);

if (getenv('PHPVERSION')) {
    require_once __DIR__ . '/versions/' . getenv('PHPVERSION') . '/vendor/autoload.php';
}
else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

printf("test symfony/http-kernel: %s\n", \Symfony\Component\HttpKernel\Kernel::VERSION);

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
