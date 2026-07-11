<?php

if (getenv('SYMFONY_VERSION')) {
    require_once __DIR__ . '/versions/' . getenv('SYMFONY_VERSION') . '/vendor/autoload.php';
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

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Caching extends \ryunosuke\microute\attribute\Aspect
{
    private mixed $cache;

    public function enter()
    {
        $this->context['service']->logger->log('test', 'Caching:enter');
        if (isset($this->cache)) {
            return $this->cache;
        }
    }

    public function action(\Closure $invoke)
    {
        $this->context['service']->logger->log('test', 'Caching:action');
        return $invoke();
    }

    public function done()
    {
        $this->cache = $this->context['return'];
        return new \ryunosuke\microute\http\Response($this->context['return']);
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Transaction extends \ryunosuke\microute\attribute\Aspect
{
    public function try()
    {
        $this->context['service']->logger->log('test', 'Transaction:try');
    }

    public function done()
    {
        $this->context['service']->logger->log('test', 'Transaction:done');
    }

    public function catch()
    {
        $this->context['service']->logger->log('test', 'Transaction:catch');
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Logging extends \ryunosuke\microute\attribute\Aspect
{
    public function enter()
    {
        $this->context['service']->logger->log('test', 'Logging:enter');
    }

    public function catch()
    {
        $this->context['service']->logger->log('test', 'Logging:catch');
    }

    public function return()
    {
        $this->context['service']->logger->log('test', 'Logging:return');
    }
}
