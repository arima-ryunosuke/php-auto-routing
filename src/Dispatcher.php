<?php
namespace ryunosuke\microute;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ディスパッチャクラス
 *
 * コントローラへの移譲を行う。
 *
 * @property-read Controller $dispatchedController
 * @property-read ?\Throwable $lastException
 */
class Dispatcher
{
    use mixin\Accessable;

    const CACHE_KEY = 'Dispatcher' . Service::CACHE_VERSION;

    private Service $service;

    // コントローラが引けなくてもエラーコントローラのために名前空間を覚えておく必要がある
    private string     $dispatchingNamespace;
    private Controller $dispatchedController;

    private ?\Throwable $lastException = null;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function dispatch(Request $request): Response
    {
        $matched = $this->service->router->match($request);
        if ($matched instanceof Response) {
            return $matched;
        }

        $this->dispatchingNamespace = $matched['controller'];
        $controller_action = $this->findController($matched['controller'], $matched['action']);

        if (is_string($controller_action[0])) {
            [$controller_class, $action_name] = $controller_action;
            $this->dispatchedController = $controller = $this->loadController($controller_class, $action_name, $request);
            $metadata = $controller::metadata($this->service->cacher);

            if ($matched['route'] === 'default' && !$metadata['actions'][$action_name]['@default-route']) {
                throw new HttpException(404, "$controller_class::$action_name is not allowed default routhing.");
            }

            return $this->service->trigger('dispatch', $controller) ?? $controller->dispatch($matched['parameters']);
        }

        throw new HttpException(...$controller_action);
    }

    public function error(\Throwable $t, Request $request): Response
    {
        $this->lastException = $t;

        if (!$t instanceof HttpException) {
            $this->service->logger->error('failed to request {exception}', ['exception' => $t, 'request' => $request]);
        }

        // 下層から順繰りにエラーコントローラを探す
        $controller = null;
        $ns = $this->shortenController($this->dispatchingNamespace ?? '');
        while (true) {
            $controller_action = $this->findController("$ns\\Default", 'error');
            if (is_string($controller_action[0])) {
                $this->dispatchedController = $controller = $this->loadController($controller_action[0], 'error', $request);
                break;
            }
            if ($ns === '') {
                break;
            }
            $ns = substr($ns, 0, strrpos($ns, '\\'));
        }

        if ($controller === null) {
            throw new \Exception('DefaultController is notfound.', 0, $t);
        }

        try {
            $response = $controller->dispatch([$t], false);
            if (in_array(get_class($response), [Response::class, \ryunosuke\microute\http\Response::class], true)) {
                if ($t instanceof HttpException) {
                    $response->setStatusCode($t->getStatusCode());
                    $response->headers->add($t->getHeaders());
                }
                else {
                    $response->setStatusCode(500);
                }
            }
            return $response;
        }
        catch (\Throwable $t) {
            throw new \Exception('DefaultController throws Exception.', 0, $t);
        }
    }

    public function finish(Response $response, Request $request): Response
    {
        if (!strlen($response->headers->get('Content-Type'))) {
            $contexts = $this->service->parameterContexts;
            $context = $request->attributes->get('context');
            if (is_callable($contexts) && ($cx = $contexts($context))) {
                $response->headers->set('Content-Type', $cx);
            }
            elseif (is_array($contexts) && isset($contexts[$context])) {
                $response->headers->set('Content-Type', $contexts[$context]);
            }
        }
        if ($response->getStatusCode() === 301) {
            $response->setCache([
                'no_store' => true,
            ]);
        }
        return $response;
    }

    /**
     * アプリ固有のコントローラ名から完全修飾クラス名に変換する
     */
    public function resolveController(string $controller_class): ?string
    {
        $controllerClass = $this->service->controllerClass;
        if (is_subclass_of($controller_class, $controllerClass)) {
            return $controller_class;
        }

        $namespace = $this->service->controllerNamespace;
        $controller_class = trim(str_replace('/', '\\', $controller_class), '\\');
        $controller_name = $namespace . $controller_class . $controllerClass::CONTROLLER_SUFFIX;
        if (!class_exists($controller_name)) {
            $controller_name = $namespace . ltrim("$controller_class\\Default", '\\') . $controllerClass::CONTROLLER_SUFFIX;
            if (!class_exists($controller_name)) {
                return null;
            }
        }
        return $controller_name;
    }

    /**
     * 完全修飾クラス名からアプリ固有のコントローラ名に変換する
     */
    public function shortenController(?string $controller_class): string
    {
        $prefix = preg_quote($this->service->controllerNamespace, '#');
        $suffix = $this->service->controllerClass::CONTROLLER_SUFFIX;
        return preg_replace("#^($prefix)|($suffix)$#", '', ltrim($controller_class, '\\'));
    }

    /**
     * コントローラを探す
     *
     * @return array [見つかったクラス名, アクション名] or [投げるべき例外コード, メッセージ]
     */
    public function findController(string $controller_name, string $action_name): array
    {
        $cachekey = self::CACHE_KEY . '.ca.' . strtr("$controller_name+$action_name", [
                '\\' => '%',
                '{'  => '#',
                '}'  => '#',
                '('  => '#',
                ')'  => '#',
                '/'  => '#',
                '@'  => '#',
                ':'  => '#',
            ]);
        $result = $this->service->cacher->get($cachekey, null);
        if ($result) {
            return $result;
        }

        // hoge/fuga/piyo だとして・・・
        $uname = ucfirst($action_name);
        $controller_action = [
            trim("$controller_name", '\\')                  => $action_name, // Hoge\FugaController#piyoAction になる
            trim("$controller_name\\Default", '\\')         => $action_name, // Hoge\Fuga\DefaultController#piyoAction になる
            trim("$controller_name\\$uname", '\\')          => 'default',    // Hoge\Fuga\PiyoController#defaultAction になる
            trim("$controller_name\\$uname\\Default", '\\') => 'default',    // Hoge\Fuga\piyo\DefaultController#defaultAction になる
        ];

        foreach ($controller_action as $cname => $aname) {
            $class_name = $this->service->controllerNamespace . $cname . $this->service->controllerClass::CONTROLLER_SUFFIX;

            if (!class_exists($class_name)) {
                $result = $result ?? [404, "$class_name class doesn't exist."];
                continue;
            }

            $metadata = $class_name::metadata($this->service->cacher);

            if ($metadata['abstract']) {
                $result = $result ?? [404, "$class_name class is abstract."];
                continue;
            }
            if (!isset($metadata['actions'][$aname])) {
                $result = $result ?? [404, "$class_name class doesn't have $aname."];
                continue;
            }

            // 無限に溜まってしまうので見つかったときのみキャッシュする
            $result = [$class_name, $aname];
            $this->service->cacher->set($cachekey, $result);
            return $result;
        }

        return $result;
    }

    /**
     * コントローラを読み込む
     */
    public function loadController(string $controller_class, string $action_name, Request $request): Controller
    {
        /** @var Controller::class $controller_class */
        $action_data = $controller_class::metadata($this->service->cacher)['actions'][$action_name];
        $is_error = $action_name === 'error';

        if (!$this->service->debug && !$is_error) {
            $origins = $this->service->origin;
            if ($origins instanceof \Closure) {
                $origins = $origins($this->service);
            }
            $origins = array_merge($origins, $action_data['@origin']);
            if ($origins && !$request->isMethodSafe()) {
                if (strlen($origin = $request->headers->get('origin'))) {
                    foreach ($origins as $allowed) {
                        if (fnmatch($allowed, $origin)) {
                            goto OK;
                        }
                    }
                    throw new HttpException(403, "$origin is not allowed Origin.");
                    OK:
                }
            }
        }

        if (!$this->service->debug && !$is_error) {
            $ajaxable = $action_data['@ajaxable'];
            if ($ajaxable !== null && !$request->isXmlHttpRequest()) {
                throw new HttpException($ajaxable ?: 400, "$action_name only accepts XmlHttpRequest.");
            }
        }

        if (!$is_error) {
            $method = $request->getMethod();
            $allows = $action_data['@method'];
            if ($allows && !preg_grep('#^' . $method . '$#i', $allows)) {
                throw new HttpException(405, "$action_name doesn't allow $method method.");
            }
        }

        if (!$is_error) {
            $context = $request->attributes->get('context');
            $contexts = $action_data['@context'];
            if (!in_array('*', $contexts, true) && !preg_grep('#^' . $context . '$#i', $contexts)) {
                throw new HttpException(404, "$action_name doesn't allow '$context' context.");
            }
        }

        return new $controller_class($this->service, $action_name, $request);
    }
}
