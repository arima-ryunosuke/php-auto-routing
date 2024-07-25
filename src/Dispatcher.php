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
 * @property-read \Throwable $lastException
 */
class Dispatcher
{
    use mixin\Accessable;

    const CACHE_KEY = 'Dispatcher' . Service::CACHE_VERSION;

    /** @var Service */
    private $service;

    /** @var Controller ディスパッチされたコントローラ(存在するとは限らない) */
    private $dispatchedController;

    /** @var \Throwable */
    private $lastException;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function dispatch(Request $request)
    {
        $matched = $this->service->router->match($request);
        if ($matched instanceof Response) {
            return $matched;
        }

        $this->dispatchedController = $matched['controller'];
        $controller_action = $this->findController($matched['controller'], $matched['action']);

        if (is_string($controller_action[0])) {
            [$controller_class, $action_name] = $controller_action;
            $this->dispatchedController = $controller = $this->loadController($controller_class, $action_name, $request);
            $metadata = $controller::metadata($this->service->cacher);

            if ($matched['route'] === 'default' && !$metadata['actions'][$action_name]['@default-route']) {
                throw new HttpException(404, "$controller_class::$action_name is not allowed default routhing.");
            }

            return $this->service->trigger('dispatch', $controller) ?? $controller->dispatch($this->detectArgument($controller, $matched['parameters']));
        }

        throw new HttpException(...$controller_action);
    }

    public function error(\Throwable $t, Request $request)
    {
        $this->lastException = $t;

        if (!$t instanceof HttpException) {
            $this->service->logger->error('failed to request {exception}', ['exception' => $t, 'request' => $request]);
        }

        // 下層から順繰りにエラーコントローラを探す
        $controller = null;
        $ns = $this->shortenController($this->dispatchedController);
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

    public function finish(Response $response, Request $request)
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
     *
     * @param string $controller_class
     * @return string|Controller
     */
    public function resolveController($controller_class)
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
     *
     * @param string|Controller $controller_class
     * @return string
     */
    public function shortenController($controller_class)
    {
        $prefix = preg_quote($this->service->controllerNamespace, '#');
        $suffix = $this->service->controllerClass::CONTROLLER_SUFFIX;
        $classname = is_object($controller_class) ? get_class($controller_class) : $controller_class;
        return preg_replace("#^($prefix)|($suffix)$#", '', ltrim($classname, '\\'));
    }

    /**
     * コントローラを探す
     *
     * @param string $controller_name コントローラ名
     * @param string $action_name アクション名
     * @return array [見つかったクラス名, アクション名] or [投げるべき例外コード, メッセージ]
     */
    public function findController($controller_name, $action_name)
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
     *
     * @param Controller|string $controller_class コントローラクラス
     * @param string $action_name アクション名
     * @param Request $request リクエストオブジェクト
     * @return Controller
     */
    public function loadController($controller_class, $action_name, $request)
    {
        // サブリクエストはあらゆる制約を見ない（例えば ajaxable なアクションでも forward 可能）
        if ($request->attributes->get('request-type', Service::MAIN_REQUEST) === Service::SUB_REQUEST) {
            return new $controller_class($this->service, $action_name, $request);
        }

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
            $allows = $action_data['@action'];
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

    public function detectArgument($controller, $args)
    {
        /** @var Controller $controller */
        $metadata = $controller::metadata($this->service->cacher);
        $request = $controller->request;
        $action_name = $controller->action;

        $datasources = [];

        // @argument に基いて見るべきパラメータを導出
        $argumentmap = [
            'GET'    => ['query'],
            'POST'   => ['request'],
            'FILE'   => ['files'],
            'COOKIE' => ['cookies'],
            'ATTR'   => ['attributes'],
        ];
        foreach ($metadata['actions'][$action_name]['@argument'] as $argument) {
            foreach ($argumentmap[$argument] ?? [] as $source) {
                $datasources += $request->$source->all();
            }
        }
        // @action に基いて見るべきパラメータを導出
        $actionmap = [
            'GET'    => ['query', 'attributes'],                     // GET で普通は body は来ない
            'POST'   => ['request', 'files', 'query', 'attributes'], // POST はかなり汎用的なのですべて見る
            'PUT'    => ['request', 'query', 'attributes'],          // PUT は body が単一みたいなもの（symfony が面倒見てくれてる）
            'DELETE' => ['query', 'attributes'],                     // DELETE で普通は body は来ない
            '*'      => ['query', 'request', 'files', 'attributes'], // 全部
        ];
        foreach ($metadata['actions'][$action_name]['@action'] ?: ['*'] as $action) {
            foreach ($actionmap[$action] ?? [] as $source) {
                $datasources += $request->$source->all();
            }
        }

        // ReflectionParameter に基いてパラメータを確定
        $arrayable = $this->service->parameterArrayable;
        $parameters = [];
        foreach ($metadata['actions'][$action_name]['parameters'] as $i => $parameter) {
            $name = $parameter['name'];
            // GET/POST などのリクエスト引数から来ている
            if (array_key_exists($name, $datasources)) {
                $value = $datasources[$name];
            }
            // 基本的には通常配列で来るが、正規表現ルートでは連想配列で来ることがある
            elseif (array_key_exists($name, $args)) {
                $value = $args[$name];
            }
            // /path/hoge のようなアクションパラメータから来ている
            elseif (array_key_exists($i, $args)) {
                $value = $args[$i];
            }
            // 上記に引っかからなかったらメソッド引数のデフォルト値を使う
            elseif ($parameter['defaultable']) {
                $value = $parameter['default'];
            }
            // それでも引っかからないならパラメータが不正・足りない
            else {
                throw new HttpException(404, 'parameter is not match.');
            }

            // キャストや配列のチェック
            $type = $parameter['type'];
            if ($type) {
                $firsttype = array_key_first($type);
                if (!$arrayable && !isset($type['array']) && is_array($value)) {
                    throw new HttpException(404, 'parameter is not match type.');
                }
                if ($firsttype !== "null" && !isset($type[strtolower(gettype($value))]) && !class_exists($firsttype)) {
                    @settype($value, $firsttype);
                }
            }
            // 型指定が存在しないかつ配列が来たら 404
            elseif (!$arrayable && is_array($value)) {
                throw new HttpException(404, 'parameter is not match type.');
            }

            $parameters[$name] = $value;
        }

        // 利便性が高いので attribute に入れておく
        $request->attributes->set('parameter', $parameters);

        return array_values($parameters);
    }
}
