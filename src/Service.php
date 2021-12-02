<?php
namespace ryunosuke\microute;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

// @formatter:off
/**
 * サービスロケータクラス
 *
 * @property-read bool                    $debug
 * @property-read CacheInterface          $cacher
 * @property-read \Closure                $logger
 * @property-read callable[][]            $events
 * @property-read string[]|\Closure       $origin
 * @property-read string[]                $priority
 *
 * @property-read Router                  $router
 * @property-read Dispatcher              $dispatcher
 * @property-read Resolver                $resolver
 * @property-read Controller              $controllerClass
 * @property-read string                  $controllerNamespace
 * @property-read string                  $controllerDirectory
 *
 * @property-read callable                $requestFactory
 * @property-read Request                 $requestClass
 * @property-read Request                 $request
 * @property-read callable[]              $requestTypes
 * @property-read SessionStorageInterface $sessionStorage
 * @property-read string                  $parameterDelimiter
 * @property-read string                  $parameterSeparator
 * @property-read bool                    $parameterArrayable
 * @property-read array|\Closure          $parameterContexts
 *
 * @property-read array|\Closure          $authenticationProvider
 * @property-read \Closure                $authenticationComparator
 * @property-read \Closure                $authenticationNoncer
 */
// @formatter:on
class Service implements HttpKernelInterface
{
    /** @var string キャッシュバージョン。本体のバージョンと同期する必要はないがキャッシュ形式を変えたらアップする */
    const CACHE_VERSION = '1.0.0';

    private $values = [];
    private $frozen = [];

    public function __construct($values = [])
    {
        $this->values['debug'] = $values['debug'] ?? false;
        $this->values['cacher'] = $values['cacher'] ?? new Cacher();
        $this->values['logger'] = $values['logger'] ?? function () { return function ($ex, $request) { }; };
        $this->values['origin'] = $values['origin'] ?? [];
        $this->values['priority'] = $values['priority'] ?? [Router::ROUTE_REWRITE, Router::ROUTE_REDIRECT, Router::ROUTE_ALIAS, Router::ROUTE_REGEX, Router::ROUTE_SCOPE, Router::ROUTE_DEFAULT];
        $this->values['events'] = $values['events'] ?? [];

        $this->values['router'] = $values['router'] ?? function () { return new Router($this); };
        $this->values['dispatcher'] = $values['dispatcher'] ?? function () { return new Dispatcher($this); };
        $this->values['resolver'] = $values['resolver'] ?? function () { return new Resolver($this); };
        $this->values['controllerClass'] = $values['controllerClass'] ?? Controller::class;

        $this->values['requestFactory'] = $values['requestFactory'] ?? function () {
                return function ($query, $request, $attributes, $cookies, $files, $server, $content) {
                    $requestClass = $this->requestClass;
                    $request = new $requestClass($query, $request, $attributes, $cookies, $files, $server, $content);

                    $conv = $this->requestTypes[$request->getContentType()] ?? null;
                    if ($conv !== null) {
                        $request->request->replace($conv($request->getContent()) ?? []);
                    }

                    $request->setSessionFactory(function () { return new Session($this->sessionStorage); });
                    return $request;
                };
            };
        $this->values['requestClass'] = $values['requestClass'] ?? \ryunosuke\microute\http\Request::class;
        $this->values['request'] = $values['request'] ?? function () { return $this->requestClass::createFromGlobals(); };
        $this->values['requestTypes'] = $values['requestTypes'] ?? [
                'json' => function ($content) {
                    return json_decode($content, true);
                },
            ];
        $this->values['sessionStorage'] = $values['sessionStorage'] ?? function () { return new NativeSessionStorage(); };
        $this->values['parameterDelimiter'] = $values['parameterDelimiter'] ?? '?';
        $this->values['parameterSeparator'] = $values['parameterSeparator'] ?? '&';
        $this->values['parameterArrayable'] = $values['parameterArrayable'] ?? false;
        $this->values['parameterContexts'] = $values['parameterContexts'] ?? [];

        $this->values['authenticationProvider'] = $values['authenticationProvider'] ?? [];
        $this->values['authenticationComparator'] = $values['authenticationComparator'] ?? function () {
                return function ($valid_password, $password) { return $valid_password === $password; };
            };
        $this->values['authenticationNoncer'] = $values['authenticationNoncer'] ?? function () {
                return function ($nonce) { return $nonce === null ? sha1(openssl_random_pseudo_bytes(40)) : null; };
            };

        if (is_array($values['controllerLocation'])) {
            foreach ($values['controllerLocation'] as $ns => $dir) {
                $this->values['controllerNamespace'] = trim($ns, '\\') . '\\';
                $this->values['controllerDirectory'] = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR;
                spl_autoload_register(function ($class) {
                    $localname = str_replace($this->controllerNamespace, '', $class);
                    $localpath = str_replace('\\', DIRECTORY_SEPARATOR, $localname);
                    $fullpath = $this->controllerDirectory . $localpath . '.php';
                    if (file_exists($fullpath)) {
                        include $fullpath;
                    }
                });
            }
        }
        else {
            $ref = new \ReflectionClass($values['controllerLocation']);
            $this->values['controllerNamespace'] = $ref->getNamespaceName() . '\\';
            $this->values['controllerDirectory'] = dirname($ref->getFileName()) . DIRECTORY_SEPARATOR;
        }

        Request::setFactory($this->requestFactory);

        if ($this->debug) {
            $this->cacher->clear();
        }
    }

    public function __isset($name)
    {
        return isset($this->values[$name]);
    }

    public function __get($name)
    {
        assert(array_key_exists($name, $this->values), get_class($this) . "::$name is undefined.");

        if (!(isset($this->frozen[$name]) || array_key_exists($name, $this->frozen))) {
            $value = $this->values[$name];
            $this->frozen[$name] = $value instanceof \Closure ? $value($this) : $value;
        }
        return $this->frozen[$name];
    }

    public function trigger($name, ...$args)
    {
        $events = $this->events[$name] ?? [];
        if (is_callable($events)) {
            $events = [$events];
        }

        foreach ($events as $listener) {
            $return = \Closure::fromCallable($listener)->call($this, ...$args);
            if ($return === false) {
                break;
            }
            if ($return instanceof Response) {
                return $return;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true)
    {
        $dispacher = $this->dispatcher;

        try {
            $response = $this->trigger('request', $request) ?? $dispacher->dispatch($request);
        }
        catch (\Exception $ex) {
            if (!$catch) {
                throw $ex;
            }
            $response = $this->trigger('error', $ex) ?? $dispacher->error($ex, $request);
        }
        $response = $this->trigger('response', $response) ?? $dispacher->finish($response, $request);

        $response->prepare($request);

        return $response;
    }

    public function run()
    {
        try {
            $response = $this->handle($this->request);
        }
        catch (\Throwable $t) {
            ($this->logger)($t);
            $response = new Response('', 400);
        }
        session_write_close();
        $response->send();
        return $this;
    }
}
