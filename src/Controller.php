<?php
namespace ryunosuke\microute;

use Psr\SimpleCache\CacheInterface;
use ryunosuke\microute\http\ThrowableResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * コントローラ基底クラス
 *
 * このクラスを継承してルーティングを行う。
 *
 * @property-read Service $service
 * @property-read SessionInterface $session
 * @property-read \ryunosuke\microute\http\Request $request
 * @property-read \ryunosuke\microute\http\Response $response
 * @property-read string $action
 */
class Controller
{
    use mixin\Accessable {
        mixin\Accessable::__get as Accessable__get;
    }

    const CACHE_KEY = 'Controller' . Service::CACHE_VERSION;

    /** @var string コントローラクラスのサフィックス */
    const CONTROLLER_SUFFIX = 'Controller';

    /** @var string アクションメソッドのサフィックス */
    const ACTION_SUFFIX = 'Action';

    private static array $metadata = [];

    private static array $namespaces = [];

    /** @var object[] オートロードインスタンス */
    private static array $instances = [];

    private Service $service;

    private string $action;

    private Request $request;

    private Response $response;

    public static function metadata(CacheInterface $cacher): array
    {
        // 頻繁に呼ばれるので $cacher だけでなくローカルキャッシュもする
        if (isset(self::$metadata[static::class])) {
            return self::$metadata[static::class];
        }

        $cachekey = self::CACHE_KEY . '.metadata.' . strtr(static::class, ['\\' => '%']);
        if ($cacher && $metadata = $cacher->get($cachekey, [])) {
            return self::$metadata[static::class] = $metadata;
        }

        $refclass = new \ReflectionClass(static::class);
        $actions = [];
        foreach (get_class_methods(static::class) as $method) {
            if (preg_match("#(.+)" . static::ACTION_SUFFIX . "$#", $method, $m)) {
                $actions[$m[1]] = new \ReflectionMethod(static::class, $method);
            }
        }
        $metadata = [
            '@alias'    => attribute\Alias::by($refclass),
            '@scope'    => attribute\Scope::by($refclass),
            '@callback' => attribute\Callback::by($refclass),
            'abstract'  => $refclass->isAbstract(),
            'actions'   => array_map(function (\ReflectionMethod $action) {
                $events = attribute\Event::by($action);
                $cache = attribute\Cache::by($action);
                if ($cache) {
                    $events['cache'] = $cache;
                }
                $public = attribute\WebCache::by($action);
                if ($public) {
                    $events['public'] = $public;
                }
                return [
                    // ルーティング系
                    '@default-route'  => attribute\DefaultRoute::by($action)[0] ?? true,
                    '@default-slash'  => attribute\DefaultSlash::by($action)[0] ?? false, // to true in future scope
                    '@trailing-slash' => attribute\TrailingSlash::by($action)[0] ?? null,
                    '@route'          => attribute\Route::by($action),
                    '@redirect'       => attribute\Redirect::by($action),
                    '@rewrite'        => attribute\Rewrite::by($action),
                    '@regex'          => attribute\Regex::by($action),
                    // アクション系
                    '@events'         => $events,
                    '@aspects'        => attribute\Aspect::by($action),
                    '@method'         => attribute\Method::by($action),
                    '@argument'       => attribute\Argument::by($action),
                    // メタデータ系
                    '@basic-auth'     => attribute\BasicAuth::by($action)[0] ?? null,
                    '@bearer-auth'    => attribute\BearerAuth::by($action)[0] ?? null,
                    '@digest-auth'    => attribute\DigestAuth::by($action)[0] ?? null,
                    '@origin'         => attribute\Origin::by($action),
                    '@ip-address'     => attribute\IpAddress::by($action),
                    '@ajaxable'       => attribute\Ajaxable::by($action)[0] ?? null,
                    '@json'           => attribute\Json::by($action)[0] ?? null,
                    '@ratelimit'      => attribute\RateLimit::by($action) ?? [],
                    // パラメータ系
                    '@context'        => attribute\Context::by($action) ?: [''],
                    'parameters'      => array_map(fn(\ReflectionParameter $parameter) => [
                        'name'        => $parameter->name,
                        'type'        => $parameter->hasType() ? (string) $parameter->getType() : 'mixed',
                        'defaultable' => $parameter->isDefaultValueAvailable(),
                        'default'     => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                    ], $action->getParameters()),
                ];
            }, $actions),
        ];

        if ($cacher) {
            $cacher->set($cachekey, $metadata);
        }
        return self::$metadata[static::class] = $metadata;
    }

    /**
     * getter のオートロード名前空間を指定する
     *
     * オートロード自体は composer などの外部頼りで自前でロードは行わない。
     * オブジェクトはキャッシュされるので生成は一度きりとなる。
     * （ほぼ内部仕様だが、このメソッドはキャッシュの削除も兼ねている）。
     */
    public static function autoload(string $namespace, array $ctor_args = []): array
    {
        $namespace = trim($namespace, '\\');
        self::$namespaces[$namespace] = $ctor_args;
        self::$instances[$namespace] = [];
        return array_keys(self::$namespaces);
    }

    final public function __construct(Service $service, string $action, ?Request $request = null)
    {
        $this->service = $service;
        $this->action = $action;
        $this->request = $request ?? $this->service->request;
        $this->response = new \ryunosuke\microute\http\Response();

        $this->construct();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'session') {
            return $this->request->getSession();
        }

        $model = $this->modelObject($name);
        if ($model !== null) {
            return $model;
        }

        // オートロード空間から一致するクラスを返す
        foreach (self::$namespaces as $namespace => $ctor_args) {
            if (class_exists($class = "$namespace\\$name") && (new \ReflectionClass($class))->getName() === $class) {
                return self::$instances[$class] ??= new $class(...$ctor_args);
            }
        }

        return $this->Accessable__get($name);
    }

    public function __toString(): string
    {
        return static::class;
    }

    public function modelObject(string $name): ?object
    {
        foreach ($this->service->mvcLocation as $namespace => $location) {
            $class = trim($namespace, '\\') . "\\$name\\{$this->service->mvcModelName}";
            if (class_exists($class) && (new \ReflectionClass($class))->getName() === $class) {
                return self::$instances[$class] ??= new $class();
            }
        }

        if ($name === $this->service->mvcModelName) {
            $class = (new \ReflectionClass($this))->getNamespaceName() . "\\$name";
            if (class_exists($class)) {
                return self::$instances[$class] ??= new $class();
            }
        }

        return null;
    }

    public function viewFile(string $suffix): string
    {
        $dirname = dirname((new \ReflectionClass($this))->getFileName());
        return "{$dirname}/{$this->service->mvcViewName}/{$this->action}{$suffix}";
    }

    /**
     * 名前空間からの相対パスを返す
     *
     * 実質的にビューファイルへのパス取得として機能する。
     */
    public function location(): string
    {
        $classname = $this->service->dispatcher->shortenController(static::class);
        return str_replace('\\', '/', $classname) . '/' . $this->action;
    }

    /**
     * Symfony\Component\HttpFoundation\Cookie を生成して返す
     *
     * 第2引数以降で配列ではなく個別指定できるが特に意味はない（配列のキーを明示するために定義している）。
     * 引数で作成したい場合は普通に new Cookie すればいい。
     */
    public function cookie(array $default, string $name = null, string $value = null, $expire = 0, ?string $path = '/', string $domain = null, ?bool $secure = false, bool $httpOnly = true, bool $raw = false, string $sameSite = null): Cookie
    {
        extract($default);
        return new Cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * リクエスト終了後の処理を登録する
     *
     * 実態は register_shutdown_function であり、 fpm 環境でなければほとんど意味はない。
     * （fpm 環境なら fastcgi_finish_request でコールされるので後処理として活用できるのを利用している）。
     * それに付随するいくつかのボイラープレートをまとめ上げただけなので、ちょろっと呼ぶだけなら普通に register_shutdown_function を呼ぶだけとあまり変わらない。
     */
    public function background(\Closure $tack, int $timeout = 0): \Closure
    {
        $callback = function () use ($tack, $timeout) {
            // ファイルセッションだと次のリクエストをブロックしてしまうのでセッションを書き込んで終了する
            session_write_close();

            // 後処理したいということはそれなりに長い時間が予想されるのでタイムアウトを設定する
            set_time_limit($timeout);

            return $tack();
        };
        register_shutdown_function($callback);
        return $callback; // for testing
    }

    /**
     * 指定 URL へフォワード（内部リダイレクト）
     */
    public function forward(string $url): Response
    {
        $parsed = parse_url($url);

        if (($parsed['path'][0] ?? '/') !== '/') {
            $url = $this->service->resolver->url($this, $parsed['path']);
        }
        parse_str($parsed['query'] ?? '', $query);

        $subrequest = $this->request->duplicate();
        $subrequest->query->replace($query);
        $subrequest->server->set('REQUEST_URI', $url);

        $matched = $this->service->router->match($subrequest);
        if ($matched instanceof Response) {
            return $matched;
        }

        return $this->_forward($subrequest, $matched['controller'], $matched['action'], $matched['parameters']);
    }

    /**
     * 指定 Controller/Action へフォワード（内部リダイレクト）
     */
    public function forwardThis(string|array $eitherParamsOrAction = [], ?string $action = null, ?string $controller = null): Response
    {
        // 文字列なら action 指定とみなす
        if (is_string($eitherParamsOrAction)) {
            $action = $eitherParamsOrAction;
        }
        $params = is_array($eitherParamsOrAction) ? $eitherParamsOrAction : [];

        // action 指定がないなら現在の action
        if ($action === null) {
            $action = $this->action;
        }
        $action = lcfirst(strtr(ucwords($action, " \t\r\n\f\v-"), ['-' => '']));

        // controller 指定がないなら現在の $controller
        if ($controller === null) {
            $controller = get_class($this);
        }
        $controller = $this->service->dispatcher->shortenController($controller);

        return $this->_forward($this->request->duplicate(), $controller, $action, $params);
    }

    protected function _forward(Request $subrequest, string $controller, string $action, array $parameters): Response
    {
        $subrequest->attributes->set('request-type', Service::SUB_REQUEST);

        // forward はアプリケーションエラーなので HttpException ではない（ヘタに forward して 404 が出たら混乱してしまう）
        try {
            [$controller_name, $action_name] = $this->service->dispatcher->findController($controller, $action);
            if (is_int($controller_name)) {
                throw new HttpException($controller_name, $action_name);
            }

            $controller = $this->service->dispatcher->loadController($controller_name, $action_name, $subrequest);
            $controller->subrequest($this);

            return $controller->dispatch($parameters);
        }
        catch (HttpException $e) {
            throw new \InvalidArgumentException('failed to forward caused by HttpException', 0, $e);
        }
    }

    /**
     * 指定 URL へリダイレクト
     */
    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return $this->response(new RedirectResponse($url, $status));
    }

    /**
     * 指定 URL へリダイレクト（自身のホストのみ）
     */
    public function redirectInternal(string $url, string $default = '/', int $status = 302): RedirectResponse
    {
        $hostname = parse_url($url, PHP_URL_HOST);
        if ($hostname !== null && $hostname !== $this->request->getHost()) {
            $url = $default;
        }
        return $this->redirect($url, $status);
    }

    /**
     * 指定ルートへリダイレクト
     */
    public function redirectRoute(string $route, array $params = [], int $status = 302): RedirectResponse
    {
        $url = $this->service->router->reverseRoute($route, $params);
        return $this->redirect($url, $status);
    }

    /**
     * 指定 Controller/Action へリダイレクト
     */
    public function redirectThis(string|array $eitherParamsOrAction = [], ?string $action = null, ?string $controller = null, int $status = 302): RedirectResponse
    {
        // 文字列なら action 指定とみなす
        if (is_string($eitherParamsOrAction)) {
            $action = $eitherParamsOrAction;
        }
        // action 指定がないなら現在の action
        if ($action === null) {
            $action = $this->action;
        }
        // controller 指定がないなら現在の $controller
        if ($controller === null) {
            $controller = get_class($this);
        }

        $params = is_array($eitherParamsOrAction) ? $eitherParamsOrAction : [];
        $url = $this->service->resolver->url($controller, $action, $params);

        return $this->redirect($url, $status);
    }

    /**
     * 現在の URL へリダイレクト
     */
    public function redirectCurrent(array $params = [], int $status = 302): RedirectResponse
    {
        $url = $this->service->resolver->current($params);
        return $this->redirect($url, $status);
    }

    /**
     * JSON レスポンスを返す
     */
    public function json(mixed $data = [], int $jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE): JsonResponse
    {
        $metadata = static::metadata($this->service->cacher);

        // 引数指定を優先として Json 属性の options を見る
        if (func_num_args() === 1 && isset($metadata['actions'][$this->action]['@json'])) {
            $jsonOptions = $metadata['actions'][$this->action]['@json'];
        }

        // debug 中は pretty
        if ($this->service->debug) {
            $jsonOptions |= JSON_PRETTY_PRINT;
        }

        // HttpException をハンドリングして json で返すケースが非常に多いので特別扱いする
        if ($data instanceof HttpException) {
            $jsonData = json_encode([
                'code'    => $data->getCode(),
                'message' => $data->getMessage(),
            ], $jsonOptions);
            return $this->response(new JsonResponse($jsonData, $data->getStatusCode(), $data->getHeaders(), true));
        }

        // JsonResponse は後から setEncodingOptions を呼ぶと decode/encode が走るので注意すること
        $jsonData = json_encode($data, $jsonOptions);
        return $this->response(new JsonResponse($jsonData, 200, [], true));
    }

    /**
     * ファイルの内容をそのままレスポンスする
     */
    public function content(string $filename, bool $httpcache = true, bool $public = false): Response
    {
        if (!file_exists($filename)) {
            throw new HttpException(404);
        }
        if (!is_readable($filename) || is_dir($filename)) {
            throw new HttpException(403);
        }

        $response = new BinaryFileResponse($filename, 200, [], $httpcache && $public, null, false, $httpcache);
        if ($httpcache) {
            $this->cache($response);
        }

        return $this->response($response);
    }

    /**
     * ダウンロードレスポンスを返す
     */
    public function download(\Closure|\SplFileInfo|string $eitherContentOrFileinfo, ?string $filename = null): Response
    {
        $attachment = function ($filename) {
            if ($filename !== null) {
                return $this->response->headers->makeDisposition('attachment', $filename);
            }
            $current = $this->response->headers->get('Content-Disposition');
            if ($current == null) {
                throw new \InvalidArgumentException('$filename must be not null.');
            }
            return $current;
        };

        if ($eitherContentOrFileinfo instanceof \SplFileInfo) {
            $response = new BinaryFileResponse($eitherContentOrFileinfo, $this->response->getStatusCode());
            // Content-Type は BinaryFileResponse が付けてくれるので不要
            $response->headers->set('Content-Disposition', $attachment($filename ?? $eitherContentOrFileinfo->getFilename()));
        }
        elseif ($eitherContentOrFileinfo instanceof \Closure) {
            $response = new StreamedResponse($eitherContentOrFileinfo, $this->response->getStatusCode());
            $response->headers->set('Content-Type', $this->response->headers->get('Content-Type', 'application/octet-stream'));
            $response->headers->set('Content-Disposition', $attachment($filename));
        }
        else {
            $response = new Response($eitherContentOrFileinfo, $this->response->getStatusCode());
            $response->headers->set('Content-Type', $this->response->headers->get('Content-Type', 'application/octet-stream'));
            $response->headers->set('Content-Disposition', $attachment($filename));
            $response->headers->set('Content-Length', strlen($eitherContentOrFileinfo));
        }

        return $this->response($response);
    }

    /**
     * プッシュ（SSE）レスポンスを返す
     *
     * Generator を渡すとその結果が SSE のレスポンスとなる。
     * いわゆるポーリングとして動作するので、適宜 sleep 処理などを必ず入れなければならない。
     *
     * 結果がプリミティブの場合は単純に data として送出される。
     * 結果が配列の場合はレコードとして送出される。
     */
    public function push(\Generator $generator, float $timeout = 10, bool $testing = false): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($generator, $timeout, $testing) {
            if (!$testing) {
                Response::closeOutputBuffers(0, true); // @codeCoverageIgnore
            }
            session_write_close();
            $mtime = microtime(true);
            foreach ($generator as $value) {
                if (connection_aborted() || $timeout < (microtime(true) - $mtime)) {
                    break;
                }

                if (!is_iterable($value)) {
                    $value = ['data' => $value];
                }
                foreach ($value as $k => $v) {
                    if (is_array($v) || (is_object($v) && !method_exists($v, '__toString'))) {
                        $v = json_encode($v);
                    }
                    foreach (preg_split('#\\R#u', $v) as $line) {
                        echo "$k: $line\n";
                    }
                }
                echo "\n";

                flush();
                usleep(10 * 1000); // 呼び元 Generator が sleep する保証もないので念の為
            }
        }, $this->response->getStatusCode());
        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $this->response($response);
    }

    public function dispatch(array $args = [], bool $error_handling = true): Response
    {
        $metadata = static::metadata($this->service->cacher);

        // アクションパラメータ（もっと後でも良いが attribute.parameters の設定があるので少し早めの段階で）
        $datasources = [];

        // @argument に基いて見るべきパラメータを導出
        $datasources += attribute\Argument::getArguments($metadata['actions'][$this->action]['@argument'], $this->request);

        // @method に基いて見るべきパラメータを導出
        $datasources += attribute\Method::getArguments($metadata['actions'][$this->action]['@method'], $this->request);

        // ReflectionParameter に基いてパラメータを確定
        $parameters = [];
        foreach ($metadata['actions'][$this->action]['parameters'] as $i => $parameter) {
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

            $parameters[$name] = $value;
        }

        // 利便性が高いので attribute に入れておく
        $this->request->attributes->set('parameter', $parameters);

        // 5大イベントディスパッチ
        try {
            // 認証
            if (!is_null($authname = $this->authenticate())) {
                $this->request->attributes->set('authname', $authname);
            }

            // init は初期化処理（Response の返却を許す）
            $this->service->logger->debug(get_class($this) . " init");
            $response = $this->init();
            if ($response instanceof Response) {
                return $this->response($response);
            }

            // before は共通事前処理
            $this->service->logger->debug(get_class($this) . " before");
            $this->before();

            // action はメイン処理
            $this->service->logger->debug(get_class($this) . " action");
            $this->response($this->action($parameters));

            // after は共通事後処理
            $this->after();

            // finish は後始末処理（Response の返却を許す）
            $this->service->logger->debug(get_class($this) . " finish");
            $response = $this->finish();
            if ($response instanceof Response) {
                return $this->response($response);
            }

            return $this->response;
        }
        catch (ThrowableResponse $response) {
            $this->service->logger->debug(get_class($this) . " throw");
            return $this->response($response->response());
        }
        catch (\Throwable $t) {
            // コントローラレベルの例外ハンドリング
            if ($error_handling) {
                $this->service->logger->debug(get_class($this) . " error");
                $response = $this->catch(match (true) {
                    default                           => $t,
                    $t instanceof BadRequestException => new HttpException(400, "raise BadRequestException({$t->getMessage()})", $t),
                });
                if ($response instanceof Response) {
                    if ($t instanceof HttpException) {
                        $response->headers->add($t->getHeaders());
                    }
                    return $this->response($response);
                }
                throw new \RuntimeException('Controller#error is must be return Response.', 0, $t);
            }
            throw $t;
        }
        finally {
            $this->finally($this->response);
        }
    }

    public function action(array $args): Response
    {
        $metadata = static::metadata($this->service->cacher);

        $this->dispatchAspect('enter', ['arguments' => $args]);

        // RateLimit はログイン前提なことがあるので action 内でやるしかない（IP だけならもっと早い段階で弾けるが…）
        $this->ratelimit();

        // pre-action
        if (($result = $this->dispatchEvent('pre')) instanceof Response) {
            return $this->response($result);
        }

        // アクション実行
        $this->dispatchAspect('try', []);
        $result = null;
        try {
            $action = \Closure::fromCallable([$this, $this->action . self::ACTION_SUFFIX]);
            $result = $this->dispatchAspect('action', ['invoke' => $action]) ?? $action(...$args);
            $this->dispatchAspect('done', ['return' => $result]);
        }
        catch (\TypeError $t) {
            $this->dispatchAspect('catch', ['throw' => $t]);
            throw new HttpException(404, 'parameter is not match type.', $t);
        }
        catch (\Throwable $t) {
            $this->dispatchAspect('catch', ['throw' => $t]);
            throw $t;
        }
        finally {
            $this->dispatchAspect('finally', ['return' => $result, 'throw' => $t ?? null]);
        }

        // json 系
        if ($metadata['actions'][$this->action]['@json'] !== null && (is_null($result) || is_scalar($result) || is_array($result))) {
            $this->response($this->json($result));
        }
        // 返り値が string ならレスポンンスボディ
        elseif (is_string($result)) {
            $this->response->setContent($result);
        }
        // 返り値が Jsonable オブジェクトなら json(stdClass も json みたいなものなので同一視)
        elseif ($result instanceof \JsonSerializable || (is_object($result) && get_class($result) === \stdClass::class)) {
            $this->response($this->json($result));
        }
        // 返り値が Respose オブジェクトなら置換(RedirectResponse とかのため)
        elseif ($result instanceof Response) {
            $this->response($result);
        }
        // それ以外は render に丸投げ
        else {
            $this->response($this->render($result));
        }

        // post-action
        if (($result = $this->dispatchEvent('post')) instanceof Response) {
            $response = $this->response($result);
        }
        else {
            $response = $this->response;
        }

        $this->dispatchAspect('return', ['response' => $response]);

        return $response;
    }

    private function dispatchAspect(string $phase, array $context): mixed
    {
        $metadata = static::metadata($this->service->cacher);

        $default_context = [
            'controller' => $this,
            'action'     => $this->action,
            'service'    => $this->service,
            'request'    => $this->request,
        ];
        foreach ($metadata['actions'][$this->action]['@aspects'] as $advice) {
            $advice->context += $context + $default_context;
            // 属性やシグネチャを見て自動分岐は可能だが、そこまでするモチベーションもないし速度が落ちるので単純に $context で分岐する
            if (isset($context['invoke'])) {
                if (($result = $advice->$phase($context['invoke'])) !== null) {
                    return $result;
                }
            }
            else {
                $advice->$phase();
            }
        }
        return null;
    }

    private function dispatchEvent(string $phase): ?Response
    {
        $metadata = static::metadata($this->service->cacher);

        foreach ($metadata['actions'][$this->action]['@events'] as $ename => $eventargs) {
            $eventMethod = $ename . 'Event';
            if (!method_exists($this, $eventMethod)) {
                throw new \DomainException("unknown event method '$eventMethod'.");
            }
            $result = $this->$eventMethod($phase, ...$eventargs);
            if ($result instanceof Response) {
                return $result;
            }
        }
        return null;
    }

    private function authenticate(): ?string
    {
        $provider = $this->service->authenticationProvider;
        $metadata = static::metadata($this->service->cacher);
        $realmer = function ($realm) {
            // クオートは RFC 的に規定はされているようだが、詳細な情報を見つけられなかった
            // そもそも Edge では正しく解釈してくれない挙動を示したのでいっその事不可とする
            if (strpos($realm, '"') !== false) {
                throw new \DomainException('realm should not be contains \'"\'');
            }
            return $realm;
        };

        $basic = $metadata['actions'][$this->action]['@basic-auth'] ?? null;
        if ($basic !== null) {
            $realm = $realmer($basic['realm']);
            $username = attribute\BasicAuth::authenticate($this->request, $provider, $this->service->authenticationComparator);
            if ($username === null) {
                $this->response->headers->set('WWW-Authenticate', attribute\BasicAuth::getHeader($realm));
                $this->response->setStatusCode(401);
                throw new ThrowableResponse($this->response);
            }
            return $username;
        }

        $bearer = $metadata['actions'][$this->action]['@bearer-auth'] ?? null;
        if ($bearer !== null) {
            $realm = $realmer($bearer['realm']);
            [$username, $exists] = attribute\BearerAuth::authenticate($this->request, $provider);
            if ($username === null) {
                $this->response->headers->set('WWW-Authenticate', attribute\BearerAuth::getHeader($realm, $exists));
                $this->response->setStatusCode(401);
                throw new ThrowableResponse($this->response);
            }
            return $username;
        }

        $digest = $metadata['actions'][$this->action]['@digest-auth'] ?? null;
        if ($digest !== null) {
            $realm = $realmer($digest['realm']);
            $username = attribute\DigestAuth::authenticate($realm, $this->request, $provider, $this->service->authenticationNoncer);
            if ($username === null) {
                $this->response->headers->set('WWW-Authenticate', attribute\DigestAuth::getHeader($realm, $this->service->authenticationNoncer));
                $this->response->setStatusCode(401);
                throw new ThrowableResponse($this->response);
            }
            return $username;
        }

        return null;
    }

    private function ratelimit()
    {
        $ratelimits = static::metadata($this->service->cacher)['actions'][$this->action]['@ratelimit'];
        foreach ($ratelimits as $ratelimit) {
            $keys = attribute\RateLimit::getRate($ratelimit, $this->request);
            if ($keys) {
                $cachekey = self::CACHE_KEY . '.ratelimit.' . strtr(static::class, ['\\' => '%']) . sha1(json_encode($keys));

                $times = $this->service->cacher->get($cachekey, []);
                if (!is_null($after = attribute\RateLimit::checkLimit($ratelimit, $times))) {
                    throw new HttpException(429, '', null, [
                        'Retry-After' => $after,
                    ]);
                }
                $this->service->cacher->set($cachekey, $times);
                break;
            }
        }
    }

    /**
     * 明確に LastModified が分かる場合にキャッシュを使うようにするメソッド
     *
     * 例えば DB レコードごとに更新日時が保持されていて
     * - 更新されていたら通常レスポンス
     * - されていないなら 304 レスポンス
     * のような場合に使用できる。
     */
    protected function cache(Response $response, \DateTime|int|null $lastModified = null): bool
    {
        // http 的キャッシュが許容されているのは GET, HEAD のみ
        if (!$this->request->isMethodCacheable()) {
            return false;
        }

        if ($lastModified !== null) {
            $response->setLastModified($lastModified instanceof \DateTime ? $lastModified : (new \DateTime())->setTimestamp($lastModified));
        }

        // session_cache_limiter を無効化する
        if (PHP_SAPI !== 'cli') {
            // @codeCoverageIgnoreStart
            // session が始まっていない場合はこれで十分
            session_cache_limiter('none');
            // 既に始まっている場合はもう送出されているので明示的に消さなければならない
            header_remove('Expires');
            header_remove('Cache-Control');
            header_remove('Pragma');
            // @codeCoverageIgnoreEnd
        }

        return $response->isNotModified($this->request);
    }

    protected function cacheEvent(string $phase, int $expire): ?Response
    {
        // debug 中は無効
        if ($this->service->debug) {
            return null;
        }

        // http 的キャッシュが許容されているのは GET, HEAD のみ
        if (!$this->request->isMethodCacheable()) {
            return null;
        }

        if ($phase === 'pre') {
            if ($this->cache($this->response, new \DateTime("-$expire seconds"))) {
                return $this->response;
            }
        }
        if ($phase === 'post') {
            $this->response->setExpires(new \DateTime("+$expire seconds"));
            $this->response->setCache([
                'private'       => true,
                'max_age'       => $expire,
                'last_modified' => new \DateTime(),
            ]);
        }
        return null;
    }

    protected function publicEvent(string $phase, int $expire)
    {
        // debug 中は無効
        if ($this->service->debug) {
            return; // @codeCoverageIgnore
        }

        // http 的キャッシュが許容されているのは GET, HEAD のみ
        if (!$this->request->isMethodCacheable()) {
            return;
        }

        if ($phase === 'post') {
            if ($this->response->getStatusCode() === 200) {
                $filename = $this->request->server->get('DOCUMENT_ROOT') . '/' . $this->request->getBasePath() . '/' . $this->request->getPathInfo();
                @mkdir(dirname($filename), 0777, true);
                file_put_contents($filename, $this->response->getContent(), LOCK_EX);

                // レスポンスヘッダはアプリのものなのでキャッシュヘッダを設定しないと次のリクエストでもう一回リクエストが来てしまう
                $this->response->setExpires(new \DateTime("+$expire seconds"));
                $this->response->setCache([
                    'max_age'       => $expire,
                    'last_modified' => new \DateTime(),
                ]);
            }
        }
    }

    /**
     * action で返り値が未定義のときに呼ばれるレンダリングメソッド
     *
     * 典型的には配列と null（返り値なし）のときにコールされる。
     * このメソッドの中身はリファレンス実装としての意味合いが強いので、使用側で必ずオーバーライドして使うこと。
     *
     * @codeCoverageIgnore
     */
    protected function render(mixed $action_value): Response
    {
        // アクションメソッドが数値を返したらステータスコードにするとか
        if (is_int($action_value)) {
            return $this->response->setStatusCode($action_value);
        }
        // アクションメソッドが配列を返したらそれを json で返すとか
        if (is_array($action_value)) {
            return $this->json($action_value);
        }
        // 拡張子が .json ならビュー変数を json で返すとか
        if ($this->request->attributes->get('context') === 'json') {
            return $this->json((array) $this);
        }
        // アクションメソッドが何も返さなかったらレンダリングして返すとか
        if ($action_value === null) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            return $this->response->setContent((static function () {
                ob_start();
                try {
                    extract(func_get_arg(1));
                    include func_get_arg(0);
                    return ob_get_clean();
                }
                catch (\Throwable $t) {
                    ob_end_clean();
                    throw $t;
                }
            })($this->viewFile('.phtml'), $this->request->attributes->get('parameter', [])));
        }
    }

    /**
     * 現在設定されている汎用的なヘッダ等を再設定して返す
     *
     * @template T of Response
     * @param T $response
     * @return T
     */
    protected function response(Response $response): Response
    {
        if ($this->response === $response) {
            return $this->response;
        }

        if (!$response instanceof RedirectResponse && $response->getStatusCode() === 200) {
            $response->setStatusCode($this->response->getStatusCode());
        }

        if (($refresh = $this->response->headers->get('Refresh')) !== null) {
            $response->headers->set('Refresh', $refresh);
        }

        foreach ($this->response->headers->allPreserveCaseWithoutCookies() as $name => $value) {
            if (stripos($name, 'x-') === 0) {
                $response->headers->set($name, $value);
            }
        }

        foreach ($this->response->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        foreach (["Allow-Origin", "Allow-Methods", "Allow-Headers", "Allow-Credentials", "Allow-Max-Age", "Expose-Headers"] as $key) {
            $key = "Access-Control-$key";
            $value = $this->response->headers->get($key);
            if ($value !== null) {
                $response->headers->set($key, $value);
            }
        }

        return $this->response = $response;
    }

    protected function construct() { }

    protected function subrequest(Controller $controller) { }

    protected function init() { }

    protected function before() { }

    protected function after() { }

    protected function finish() { }

    protected function catch(\Throwable $t) { }

    protected function finally(Response $response) { }
}
