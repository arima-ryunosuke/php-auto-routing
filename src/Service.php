<?php
namespace ryunosuke\microute;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
 * @property-read LoggerInterface         $logger
 * @property-read callable[][]            $events
 * @property-read string[]|\Closure       $origin
 * @property-read string[]                $priority
 *
 * @property-read Router                  $router
 * @property-read Dispatcher              $dispatcher
 * @property-read Resolver                $resolver
 * @property-read array                   $trustedProxies
 * @property-read Controller              $controllerClass
 * @property-read string                  $controllerNamespace
 * @property-read string                  $controllerDirectory
 * @property-read array|Controller        $controllerLocation
 * @property-read array                   $controllerAutoload
 *
 * @property-read callable                $requestFactory
 * @property-read Request                 $requestClass
 * @property-read Request                 $request
 * @property-read callable[]              $requestTypes
 * @property-read SessionStorageInterface $sessionStorage
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
    const CACHE_VERSION = '1-1-0';
    const CACHE_KEY     = 'Service' . Service::CACHE_VERSION;

    private array $values;
    private array $frozen = [];

    public function __construct(array $values = [])
    {
        assert(($values['cacher'] ?? null) instanceof \Psr\SimpleCache\CacheInterface, 'requires cacher(\\Psr\\SimpleCache\\CacheInterface)');

        $values['debug'] ??= false;
        $values['logger'] ??= new NullLogger();
        $values['origin'] ??= [];
        $values['priority'] ??= [Router::ROUTE_REWRITE, Router::ROUTE_REDIRECT, Router::ROUTE_ALIAS, Router::ROUTE_REGEX, Router::ROUTE_SCOPE, Router::ROUTE_DEFAULT];
        $values['events'] ??= [];

        $values['router'] ??= fn() => new Router($this);
        $values['dispatcher'] ??= fn() => new Dispatcher($this);
        $values['resolver'] ??= fn() => new Resolver($this);
        $values['trustedProxies'] ??= [];
        $values['controllerClass'] ??= Controller::class;
        $values['controllerAutoload'] ??= [];

        foreach ($values['controllerAutoload'] as $namespace => $ctor_args) {
            $values['controllerClass']::autoload($namespace, $ctor_args);
        }

        $values['requestFactory'] ??= fn() => function ($query, $request, $attributes, $cookies, $files, $server, $content) {
            $requestClass = $this->requestClass;
            $request = new $requestClass($query, $request, $attributes, $cookies, $files, $server, $content);

            $conv = $this->requestTypes[$request->getContentType()] ?? null;
            if ($conv !== null) {
                $request->request->replace($conv($request->getContent()) ?? []);
            }

            $request->setSessionFactory(fn() => new Session($this->sessionStorage));
            return $request;
        };
        $values['requestClass'] ??= \ryunosuke\microute\http\Request::class;
        $values['request'] ??= fn() => $this->requestClass::createFromGlobals();
        $values['requestTypes'] ??= [
            'json' => fn($content) => json_decode($content, true),
        ];
        $values['sessionStorage'] ??= fn() => new NativeSessionStorage();
        $values['parameterContexts'] ??= [];

        $values['authenticationProvider'] ??= [];
        $values['authenticationComparator'] ??= fn() => fn($valid_password, $password) => $valid_password === $password;
        $values['authenticationNoncer'] ??= fn() => fn($nonce) => $nonce === null ? sha1(openssl_random_pseudo_bytes(40)) : null;

        if (is_array($values['controllerLocation'])) {
            foreach ($values['controllerLocation'] as $ns => $dir) {
                $values['controllerNamespace'] = trim($ns, '\\') . '\\';
                $values['controllerDirectory'] = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR;
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
            $values['controllerNamespace'] = $ref->getNamespaceName() . '\\';
            $values['controllerDirectory'] = dirname($ref->getFileName()) . DIRECTORY_SEPARATOR;
        }

        $this->values = $values;

        Request::setFactory($this->requestFactory);

        if ($this->trustedProxies) {
            $proxies = [];
            foreach ($this->trustedProxies as $key => $proxy) {
                if ($proxy === 'mynetwork') {
                    $selfaddr = $this->request->server->get('SERVER_ADDR', '');
                    $addrmap = array_column(array_merge(...array_column(net_get_interfaces(), 'unicast')), 'netmask', 'address');
                    if (isset($addrmap[$selfaddr])) {
                        $netmask = strspn(decbin(ip2long($addrmap[$selfaddr])), '1');
                        $proxies[] = "$selfaddr/$netmask";
                    }
                }
                elseif ($proxy === 'private') {
                    $proxies[] = "10.0.0.0/8";
                    $proxies[] = "172.16.0.0/12";
                    $proxies[] = "192.168.0.0/16";
                }
                elseif (is_string($proxy) && filter_var(explode('/', $proxy, 2)[0], FILTER_VALIDATE_IP)) {
                    $proxies[] = $proxy;
                }
                elseif (is_string($proxy) || is_array($proxy)) {
                    $proxy = array_replace([
                        'ttl'    => 60 * 60 * 24,
                        'filter' => fn($v) => $v,
                    ], is_string($proxy) ? ['url' => $proxy] : $proxy);

                    $cachekey = self::CACHE_KEY . '.trustedProxy.' . $key;
                    $list = $this->cacher->get($cachekey, $this);
                    if ($list === $this) {
                        $this->cacher->set($cachekey, $list = (function () use ($proxy) {
                            $contents = file_get_contents($proxy['url']);

                            $ext = pathinfo($proxy['url'], PATHINFO_EXTENSION);
                            if (!strlen($ext)) {
                                $http_response_header ??= ["content-type:" . mime_content_type($proxy['url'])];
                                $ctypes = preg_filter('#^content-type:\s*(.*)#i', '$1', $http_response_header);
                                $ext = (string) $this->request->getFormat(end($ctypes));
                            }
                            $conv = $this->requestTypes[strtolower($ext ?: 'json')] ?? fn() => null;
                            $list = $conv($contents);
                            return $list ? $proxy['filter']($list) : [];
                        })(), $proxy['ttl']);
                    }
                    $proxies = array_merge($proxies, $list);
                }
            }
            Request::setTrustedProxies(array_merge(Request::getTrustedProxies(), $proxies), Request::getTrustedHeaderSet());
        }

        if ($this->debug) {
            $this->cacher->clear();
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __get(string $name): mixed
    {
        assert(array_key_exists($name, $this->values), get_class($this) . "::$name is undefined.");

        if (!(isset($this->frozen[$name]) || array_key_exists($name, $this->frozen))) {
            $value = $this->values[$name];
            $this->frozen[$name] = $value instanceof \Closure ? $value($this) : $value;
        }
        return $this->frozen[$name];
    }

    public function trigger(string $name, ...$args)
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
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true)
    {
        $request->attributes->set('request-type', $type);

        $dispacher = $this->dispatcher;

        try {
            $response = $this->trigger('request', $request) ?? $dispacher->dispatch($request);
        }
        catch (\Throwable $t) {
            if (!$catch) {
                throw $t;
            }
            $response = $this->trigger('error', $t) ?? $dispacher->error($t, $request);
        }
        $response = $this->trigger('response', $response) ?? $dispacher->finish($response, $request);

        $response->prepare($request);

        return $response;
    }

    public function run(): static
    {
        try {
            $request = $this->request;
            $response = $this->handle($request);
        }
        catch (\Throwable $t) {
            $this->logger->critical('failed to run {exception}', ['exception' => $t, 'request' => $request ?? null]);
            $response = new Response('', 400);
        }
        session_write_close();
        $response->send();
        return $this;
    }
}
