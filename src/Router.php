<?php
namespace ryunosuke\microute;

use ryunosuke\microute\mixin\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * ルータクラス
 *
 * ルート情報の管理。
 */
class Router
{
    use Utility;

    const CACHE_KEY = 'Router' . Service::CACHE_VERSION;

    const ROUTE_DEFAULT  = 'default';
    const ROUTE_ROUTE    = 'route';
    const ROUTE_ALIAS    = 'alias';
    const ROUTE_SCOPE    = 'scope';
    const ROUTE_REWRITE  = 'rewrite';
    const ROUTE_REDIRECT = 'redirect';
    const ROUTE_REGEX    = 'regex';

    /** @var Service */
    private $service;

    /** @var array ルーティング一覧 */
    private $routings;

    public function __construct(Service $service)
    {
        $this->service = $service;

        $cachekey = self::CACHE_KEY . '.routings';
        if (!$this->routings = $this->service->cacher->get($cachekey, [])) {
            $this->routings = [
                self::ROUTE_ROUTE    => [/* [name, controller, action] */],
                self::ROUTE_ALIAS    => [/* [prefix, controller] */],
                self::ROUTE_SCOPE    => [/* [regex, controller] */],
                self::ROUTE_REWRITE  => [/* [from_url, to_url, action] */],
                self::ROUTE_REDIRECT => [/* [from_url, to_url, action, status] */],
                self::ROUTE_REGEX    => [/* [regex, controller, action] */],
            ];
            foreach ($this->getControllers() as $controller) {
                $metadata = $controller::metadata($this->service->cacher);
                foreach ($metadata['@alias'] as $prefix => $option) {
                    $this->alias($prefix, $controller);
                }
                foreach ($metadata['@scope'] as $regex => $option) {
                    $this->scope($regex, $controller);
                }
                foreach ($metadata['actions'] as $action => $action_data) {
                    foreach ($action_data["@route"] as $name => $option) {
                        $this->route($name, $controller, $action);
                    }
                    foreach ($action_data["@redirect"] as $from => $option) {
                        $this->redirect($from, $controller, $option['status'], $action);
                    }
                    foreach ($action_data["@rewrite"] as $from => $option) {
                        $this->rewrite($from, $controller, $action);
                    }
                    foreach ($action_data["@regex"] as $regex => $option) {
                        $this->regex($regex, $controller, $action);
                    }
                }
            }
            $this->service->cacher->set($cachekey, $this->routings);
        }
    }

    public function match(Request $request)
    {
        $path = $request->getPathInfo();
        $query = $request->server->get('QUERY_STRING'); // getQueryString は正規化されてるので使ってはならない

        $parentpath = rtrim(preg_replace('#((.+)/)+(.*)#', '$1', preg_replace('#\\.[^/.]*$#', '', $path)), '/');
        $parsed = $this->parse($path, $query);
        $parsed['route'] = null;
        $request->attributes->set('context', $parsed['context']);

        $priority = $this->service->priority;
        foreach ($priority as $prefer) {
            switch ($prefer) {
                default:
                    throw new \UnexpectedValueException("$prefer is not defined route method");
                case self::ROUTE_DEFAULT:
                    if (is_string($this->service->dispatcher->findController($parsed['controller'], $parsed['action'])[0])) {
                        $this->service->logger->info("match " . self::ROUTE_DEFAULT . "({$parsed['controller']}@{$parsed['action']}): $path");
                        $parsed['route'] = $parsed['route'] ?? self::ROUTE_DEFAULT;
                        return $parsed;
                    }
                    break;
                case self::ROUTE_REWRITE:
                    foreach ($this->routings[self::ROUTE_REWRITE] as $from => $routing) {
                        if (preg_match("#$from#", $path)) {
                            $this->service->logger->info("match " . self::ROUTE_REWRITE . "($from): $path");
                            [$controller, $action] = $routing;
                            if ($action !== null) {
                                $controller = $this->service->resolver->url($controller, $action, [], '');
                            }
                            $path = preg_replace("#$from#", $controller, $path);
                            $parentpath = rtrim(preg_replace('#((.+)/)+(.*)#', '$1', preg_replace('#\\.[^/.]*$#', '', $path)), '/');
                            $parsed = $this->parse($path, $query);
                            $parsed['route'] = self::ROUTE_REWRITE;
                            break;
                        }
                    }
                    break;
                case self::ROUTE_REDIRECT:
                    foreach ($this->routings[self::ROUTE_REDIRECT] as $from => $routing) {
                        if ($from === $path) {
                            $this->service->logger->info("match " . self::ROUTE_REDIRECT . "($from): $path");
                            [$controller, $action, $status] = $routing;
                            if ($action !== null) {
                                $action = $action . (strlen($parsed['context']) ? '.' . $parsed['context'] : '');
                                $controller = $this->service->resolver->url($controller, $action, $parsed['parameters']);
                            }
                            return new RedirectResponse($controller, $status);
                        }
                    }
                    break;
                case self::ROUTE_ALIAS:
                    foreach ($this->routings[self::ROUTE_ALIAS] as $from => $to) {
                        if ($from === $parentpath) {
                            $this->service->logger->info("match " . self::ROUTE_ALIAS . "($from): $path");
                            if ($parsed['controller'] === '' || $parentpath === $path) {
                                $parsed['action'] = 'default';
                            }
                            $parsed['controller'] = $this->service->dispatcher->shortenController($to);
                            $parsed['route'] = self::ROUTE_ALIAS;
                            return $parsed;
                        }
                    }
                    break;
                case self::ROUTE_SCOPE:
                    foreach ($this->routings[self::ROUTE_SCOPE] as $regex => $routing) {
                        if (preg_match("#^$regex#u", $path, $matches)) {
                            $this->service->logger->info("match " . self::ROUTE_SCOPE . "($regex): $path");
                            $path2 = preg_replace("#^$regex#u", '', $path, 1, $count);
                            $parsed2 = $this->parse($path2);
                            $parsed['controller'] = $this->service->dispatcher->shortenController($routing);
                            $parsed['action'] = $parsed2['action'];
                            if (strlen($parsed['action']) === 0) {
                                $parsed['action'] = 'default';
                            }
                            // スコープルートは parameters を完全上書き（どうせ渡ってこない）
                            array_shift($matches);
                            $parsed['parameters'] = $matches;
                            $parsed['route'] = self::ROUTE_SCOPE;
                            return $parsed;
                        }
                    }
                    break;
                case self::ROUTE_REGEX:
                    foreach ($this->routings[self::ROUTE_REGEX] as $regex => $routing) {
                        if (preg_match("#^$regex$#u", $path, $matches)) {
                            $this->service->logger->info("match " . self::ROUTE_REGEX . "($regex): $path");
                            [$controller, $action] = $routing;
                            $parsed['controller'] = $this->service->dispatcher->shortenController($controller);
                            $parsed['action'] = $action;
                            // 正規表現ルートは parameters を完全上書き（どうせ渡ってこない）
                            array_shift($matches);
                            $parsed['parameters'] = $matches;
                            $parsed['route'] = self::ROUTE_REGEX;
                            return $parsed;
                        }
                    }
                    break;
            }
        }

        return $parsed;
    }

    /**
     * url を controller, action, context, parameters に分解
     *
     * @param string $path URL のパス部分
     * @param string|null $query URL のクエリ部分
     * @return array controller/action/context/parameters を含む配列
     */
    public function parse($path, $query = null)
    {
        $delimiter = $this->service->parameterDelimiter;
        $separator = $this->service->parameterSeparator;
        $useRFC3986 = $this->service->parameterUseRFC3986;

        $context = pathinfo($path, PATHINFO_EXTENSION);
        $path = preg_replace('#\\.' . preg_quote($context) . '$#', '', $path);

        // constroller/action に分解
        $paths = strtr(ucwords($path, " \t\r\n\f\v-"), ['-' => '']);
        $parts = array_filter(explode('/', $paths), 'strlen');
        $action_name = lcfirst(array_pop($parts));
        $controller_name = implode('\\', array_map('ucfirst', $parts));
        $parameters = [];

        // delimiter が '/' の時は挙動がまるで異なる（もともとパス区切りだから）
        if ($delimiter === '/') {
            if ($separator === '/') {
                $dispatcher = $this->service->dispatcher;
                // コントローラ/アクションが見つからない時、パラメータ指定とみなして左にずらして再検索
                while (true) {
                    if (is_string(($ca = $dispatcher->findController($controller_name, $action_name))[0])) {
                        [$controller_name, $action_name] = $ca;
                        $controller_name = $dispatcher->shortenController($controller_name);
                        break;
                    }
                    array_unshift($parameters, $action_name);
                    $action_name = lcfirst(array_pop($parts));
                    $controller_name = implode('\\', array_map('ucfirst', $parts));
                }
            }
            elseif ($parts) {
                $parameters = explode($separator, $action_name);
                $action_name = lcfirst(array_pop($parts));
                $controller_name = implode('\\', array_map('ucfirst', $parts));
            }
        }
        // 完全なる RFC 的な区切りはパスパラメータとしては空
        elseif ($useRFC3986) {
            $parameters = [];
        }
        // delimiter が '?' の時もまるで異なる（? は RFC 的なクエリ区切りだから）
        elseif ($delimiter === '?' && strlen($query)) {
            $parameters = explode($separator, $query);
        }
        // 上記以外は $action_name を分割すれば良い
        elseif (count($parts = explode($delimiter, $action_name, 2)) === 2) {
            $action_name = $parts[0];
            $parameters = explode($separator, $parts[1]);
        }

        return [
            'controller' => $controller_name,
            'action'     => $action_name,
            'context'    => $context,
            'parameters' => $parameters,
        ];
    }

    /**
     * ルート名を追加
     *
     * @param string $name ルート名
     * @param string $controller コントローラ名
     * @param string $action アクション名
     * @return $this
     */
    public function route($name, $controller, $action)
    {
        $this->service->logger->debug(self::ROUTE_ROUTE . " $name: $controller@$action");
        $this->routings[self::ROUTE_ROUTE][$name] = [$controller, $action];
        return $this;
    }

    /**
     * エイリアスルート定義
     *
     * @param string $prefix URL
     * @param string $controller コントローラ名
     * @return $this
     */
    public function alias($prefix, $controller)
    {
        $this->service->logger->debug(self::ROUTE_ALIAS . " $prefix: $controller");
        $this->routings[self::ROUTE_ALIAS][$prefix] = $controller;
        return $this;
    }

    /**
     * スコープルート定義
     *
     * @param string $regex 正規表現
     * @param string $controller コントローラ名
     * @return $this
     */
    public function scope($regex, $controller)
    {
        if ($regex[0] !== '/') {
            $regex = rtrim($this->service->resolver->url($controller, '', [], ''), '/') . '/' . $regex;
        }
        $this->service->logger->debug(self::ROUTE_SCOPE . " $regex: $controller");
        $this->routings[self::ROUTE_SCOPE][$regex] = $controller;
        return $this;
    }

    /**
     * リライトルート定義
     *
     * @param string $from_url 元 URL
     * @param string $to_url 先 URL
     * @param string|null $action アクション名（内部向け）
     * @return $this
     */
    public function rewrite($from_url, $to_url, $action = null)
    {
        $this->service->logger->debug(self::ROUTE_REWRITE . " $from_url: $to_url@$action");
        $this->routings[self::ROUTE_REWRITE][$from_url] = [$to_url, $action];
        return $this;
    }

    /**
     * リダイレクトルート定義
     *
     * @param string $from_url 元 URL
     * @param string $to_url 先 URL
     * @param int $status_code 302 などのステータスコード
     * @param string|null $action アクション名（内部向け）
     * @return $this
     */
    public function redirect($from_url, $to_url, $status_code = 302, $action = null)
    {
        if (!(300 <= $status_code && $status_code < 400)) {
            $status_code = 302;
        }
        $this->service->logger->debug(self::ROUTE_REDIRECT . " $from_url: $to_url($status_code)@$action");
        $this->routings[self::ROUTE_REDIRECT][$from_url] = [$to_url, $action, $status_code];
        return $this;
    }

    /**
     * 正規表現ルート定義
     *
     * @param string $regex 正規表現
     * @param string $controller コントローラ名
     * @param string $action アクション名
     * @return $this
     */
    public function regex($regex, $controller, $action)
    {
        if ($regex[0] !== '/') {
            $regex = rtrim($this->service->resolver->url($controller, '', [], ''), '/') . '/' . $regex;
        }
        $this->service->logger->debug(self::ROUTE_REGEX . " $regex: $controller@$action");
        $this->routings[self::ROUTE_REGEX][$regex] = [$controller, $action];
        return $this;
    }

    /**
     * ディスパッチ中のルート名を返す
     */
    public function currentRoute()
    {
        $controller = $this->service->dispatcher->dispatchedController;
        [$controller, $action] = [get_class($controller), $controller->action];
        $rname = array_search([$controller, $action], $this->routings[self::ROUTE_ROUTE]);
        return $rname ?: "$controller::$action";
    }

    /**
     * リバースルーティング
     *
     * ルート名とパラメーターで URL を生成。
     *
     * @param string $name ルート名
     * @param array $params パラメーター
     * @return string URL
     */
    public function reverseRoute($name, array $params = [])
    {
        $cachekey = self::CACHE_KEY . '.reverse.' . sha1($name . '?' . json_encode($params));
        $cache = $this->service->cacher->get($cachekey);
        if (!$this->service->debug && $cache !== null) {
            return $cache;
        }

        if (isset($this->routings[self::ROUTE_ROUTE][$name])) {
            $controller_action = $this->routings[self::ROUTE_ROUTE][$name];
        }
        else {
            $controller_action = explode('::', $name, 2);
            if (count($controller_action) < 2) {
                throw new \UnexpectedValueException("route name '$name' is not defined.");
            }
        }

        $priority = $this->service->priority;
        foreach ($priority as $prefer) {
            switch ($prefer) {
                default:
                    throw new \UnexpectedValueException("$prefer is not defined route method");
                // 旨味がないしもともと暫定ルーティングのつもりだったので対応しない
                case self::ROUTE_DEFAULT:
                case self::ROUTE_REWRITE:
                case self::ROUTE_REDIRECT:
                case self::ROUTE_ALIAS:
                    break;
                case self::ROUTE_SCOPE:
                    foreach ($this->routings[self::ROUTE_SCOPE] as $regex => $rc) {
                        if ($rc === $controller_action[0]) {
                            $action = $this->actionMethodToAction($controller_action[1]);
                            $url = $this->reverseRegex($regex, $params);
                            $querystring = $params ? '?' . http_build_query($params) : '';
                            $cache = $this->service->request->getBasePath() . $url . $action . $querystring;
                            $this->service->cacher->set($cachekey, $cache);
                            return $cache;
                        }
                    }
                    break;
                case self::ROUTE_REGEX:
                    foreach ($this->routings[self::ROUTE_REGEX] as $regex => $rca) {
                        if ($rca === $controller_action) {
                            $url = $this->reverseRegex($regex, $params);
                            $querystring = $params ? '?' . http_build_query($params) : '';
                            $cache = $this->service->request->getBasePath() . $url . $querystring;
                            $this->service->cacher->set($cachekey, $cache);
                            return $cache;
                        }
                    }
                    break;
            }
        }

        [$controller, $action] = $controller_action;
        $controller = $this->service->dispatcher->resolveController($controller);

        $cache = $this->service->resolver->url($controller, $action, $params);
        $this->service->cacher->set($cachekey, $cache);
        return $cache;
    }

    /**
     * 存在する URL を返す
     *
     * @return array URL 配列
     */
    public function urls()
    {
        $gather = function (&$receiver, $route, $url, $controller, $action) {
            if ($action === 'error') {
                return;
            }
            if ($action === null) {
                $receiver[$url] = [
                    'route'  => $route,
                    'name'   => null,
                    'target' => $controller,
                    'method' => [],
                ];
                return;
            }
            /** @var Controller|string $controller */
            $action_data = $controller::metadata($this->service->cacher)['actions'][$action];
            $target = "$controller::$action";
            $rname = array_search([$controller, $action], $this->routings[self::ROUTE_ROUTE]);
            $queryable = $action_data['@queryable'];

            // スコープルートのベースパラメータは差っ引いておく必要がある
            if ($route === self::ROUTE_SCOPE) {
                $baseparams = $this->regexParameter($url);
                $action_data['parameters'] = array_filter($action_data['parameters'], fn($param) => !isset($baseparams[$param['name']]));
            }
            // パラメータ（regex はルート自体がパラメータ付きのようなものなので除外）
            $pathinfo = '';
            if ($action_data['parameters'] && $route !== self::ROUTE_REGEX) {
                if ($queryable) {
                    $delimiter = '?';
                    $separator = '&';
                }
                else {
                    $delimiter = $this->service->parameterDelimiter;
                    $separator = $this->service->parameterSeparator;
                }
                $args = [];
                foreach ($action_data['parameters'] as $param) {
                    $name = $param['name'];
                    $type = [];
                    foreach ($param['type'] as $tn => $true) {
                        $type[] = $tn . ($param['defaultable'] ? '(' . json_encode($param['default']) . ')' : '');
                    }
                    $type = implode('|', $type) ?: 'mixed';
                    $args[] = $queryable ? "$name=$type" : "\$$name@$type";
                }
                $pathinfo = $delimiter . implode($separator, $args);
            }

            $contexts = $action_data['@context'];
            foreach ($contexts as $context) {
                $context = $context === '' ? '' : '.' . $context;
                $contexturl = $url;
                if (($pathinfo[0] ?? '') === '/') {
                    $contexturl .= $pathinfo . $context;
                }
                else {
                    $contexturl .= $context . $pathinfo;
                }
                $receiver[$contexturl] = [
                    'route'  => $route,
                    'name'   => $rname ?: $target,
                    'target' => $target . $controller::ACTION_SUFFIX,
                    'method' => $action_data['@action'],
                ];
            }
        };

        /** @var Controller $controller */
        $result = [];
        foreach ($this->routings[self::ROUTE_REDIRECT] as $from => $routing) {
            $gather($result, self::ROUTE_REDIRECT, $this->service->request->getBasePath() . $from, ...$routing);
        }
        foreach ($this->routings[self::ROUTE_REWRITE] as $from => $routing) {
            $gather($result, self::ROUTE_REWRITE, $this->service->request->getBasePath() . $from, ...$routing);
        }
        foreach ($this->routings[self::ROUTE_REGEX] as $from => $routing) {
            $gather($result, self::ROUTE_REGEX, $this->service->request->getBasePath() . $from, ...$routing);
        }
        foreach ($this->routings[self::ROUTE_ALIAS] as $alias => $controller) {
            $metadata = $controller::metadata($this->service->cacher);
            foreach ($metadata['actions'] as $action => $action_data) {
                $gather($result, self::ROUTE_ALIAS, $this->service->resolver->url($controller, $action, [], $alias), $controller, $action);
            }
        }
        foreach ($this->routings[self::ROUTE_SCOPE] as $scope => $controller) {
            $metadata = $controller::metadata($this->service->cacher);
            foreach ($metadata['actions'] as $action => $action_data) {
                $gather($result, self::ROUTE_SCOPE, $this->service->request->getBasePath() . $scope . $this->actionMethodToAction($action), $controller, $action);
            }
        }
        foreach ($this->getControllers() as $controller) {
            $metadata = $controller::metadata($this->service->cacher);
            foreach ($metadata['actions'] as $action => $action_data) {
                if ($action_data['@default-route']) {
                    $gather($result, self::ROUTE_DEFAULT, $this->service->resolver->url($controller, $action), $controller, $action);
                }
            }
        }

        ksort($result);
        return $result;
    }

    private function getControllers()
    {
        $namespace = $this->service->controllerNamespace;
        $directory = $this->service->controllerDirectory;
        $suffix = $this->service->controllerClass::CONTROLLER_SUFFIX;

        $rdi = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::CURRENT_AS_SELF | \FilesystemIterator::SKIP_DOTS);
        $rii = new \RecursiveIteratorIterator($rdi);
        $ri = new \RegexIterator($rii, "#$suffix\\.php$#");

        $controllers = [];
        /** @var \RecursiveDirectoryIterator $file */
        foreach ($ri as $file) {
            /** @var Controller $class_name */
            $class_name = $namespace . preg_replace(['#\\.php$#', '#/#'], ['', '\\\\'], $file->getSubPathname());
            if (!class_exists($class_name)) {
                continue;
            }
            $metadata = $class_name::metadata($this->service->cacher);
            if ($metadata['abstract']) {
                continue;
            }

            $controllers[] = $class_name;
        }
        return $controllers;
    }

    private function actionMethodToAction($action)
    {
        if ($action === 'default') {
            return '';
        }
        return strtolower(preg_replace('#([^/])([A-Z])#', '$1-$2', $action));
    }
}
