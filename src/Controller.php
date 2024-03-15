<?php
namespace ryunosuke\microute;

use Psr\SimpleCache\CacheInterface;
use ryunosuke\microute\http\ThrowableResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
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
    use mixin\Annotatable;

    const CACHE_KEY = 'Controller' . Service::CACHE_VERSION;

    /** @var string コントローラクラスのサフィックス */
    const CONTROLLER_SUFFIX = 'Controller';

    /** @var string アクションメソッドのサフィックス */
    const ACTION_SUFFIX = 'Action';

    /** @internal delete in future scope */
    public static $enabledAttribute;

    /** @var array メタデータ */
    private static $metadata = [];

    /** @var array[] オートロード名前空間 */
    private static $namespaces = [];

    /** @var object[] オートロードインスタンス */
    private static $instances = [];

    /** @var Service */
    private $service;

    /** @var string 実行中のアクション */
    private $action;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    public static function metadata(CacheInterface $cacher = null)
    {
        // 頻繁に呼ばれるので $cacher だけでなくローカルキャッシュもする
        if (isset(self::$metadata[static::class])) {
            return self::$metadata[static::class];
        }

        $cachekey = self::CACHE_KEY . '.metadata.' . strtr(static::class, ['\\' => '%']);
        if ($cacher && $metadata = $cacher->get($cachekey, [])) {
            return self::$metadata[static::class] = $metadata;
        }

        if (static::$enabledAttribute) {
            $refclass = new \ReflectionClass(static::class);
            $actions = [];
            foreach (get_class_methods(static::class) as $method) {
                if (preg_match("#(.+)" . static::ACTION_SUFFIX . "$#", $method, $m)) {
                    $actions[$m[1]] = new \ReflectionMethod(static::class, $method);
                }
            }
            $metadata = [
                '@alias'   => attribute\Alias::by($refclass),
                '@scope'   => attribute\Scope::by($refclass),
                'abstract' => $refclass->isAbstract(),
                'actions'  => array_map(function (\ReflectionMethod $action) {
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
                        '@default-route' => attribute\DefaultRoute::by($action)[0] ?? true,
                        '@route'         => attribute\Route::by($action),
                        '@redirect'      => attribute\Redirect::by($action),
                        '@rewrite'       => attribute\Rewrite::by($action),
                        '@regex'         => attribute\Regex::by($action),
                        // アクション系
                        '@events'        => $events,
                        '@action'        => attribute\Method::by($action),
                        '@argument'      => attribute\Argument::by($action),
                        // メタデータ系
                        '@basic-auth'    => attribute\BasicAuth::by($action)[0] ?? null,
                        '@digest-auth'   => attribute\DigestAuth::by($action)[0] ?? null,
                        '@origin'        => attribute\Origin::by($action),
                        '@ajaxable'      => attribute\Ajaxable::by($action)[0] ?? null,
                        '@queryable'     => attribute\Queryable::by($action)[0] ?? true,
                        // パラメータ系
                        '@context'       => attribute\Context::by($action) ?: [''],
                        'parameters'     => array_map(function (\ReflectionParameter $parameter) {
                            $typemap = [
                                'bool'    => 'boolean',
                                'boolean' => 'boolean',
                                'int'     => 'integer',
                                'integer' => 'integer',
                                'float'   => 'double',
                                'double'  => 'double',
                                'null'    => 'null',
                            ];
                            $pname = $parameter->name;
                            $types = [];
                            $defaultable = $parameter->isDefaultValueAvailable();
                            $default = $defaultable ? $parameter->getDefaultValue() : null;

                            // タイプヒントから
                            if ($parameter->hasType()) {
                                $type = $parameter->getType();
                                $tname = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
                                $types[$typemap[strtolower($tname)] ?? $tname] = 'typehint';
                                if ($parameter->allowsNull()) {
                                    $types['null'] = 'typehint';
                                }
                            }
                            // デフォルト値があるならその型を加える
                            if ($defaultable) {
                                $tname = gettype($default);
                                $types[$typemap[strtolower($tname)] ?? $tname] = 'typehint';
                            }
                            // 後始末（NULL は特殊すぎるので後ろに持っていく）
                            if (array_key_exists('null', $types)) {
                                $tmp = $types['null'];
                                unset($types['null']);
                                $types['null'] = $tmp;
                            }
                            return [
                                'name'        => $pname,
                                'type'        => $types,
                                'defaultable' => $defaultable,
                                'default'     => $default,
                            ];
                        }, $action->getParameters()),
                    ];
                }, $actions),
            ];
        }
        else {
            // @codeCoverageIgnoreStart
            $metadata = [
                '@alias'   => static::getAnnotationAsHash('alias', [null, 'comment'], null, []),
                '@scope'   => static::getAnnotationAsHash('scope', [null, 'comment'], null, []),
                'abstract' => static::reflect()->isAbstract(),
                'actions'  => array_map(function (\ReflectionMethod $action) {
                    $aname = $action->name;

                    $events = static::getAnnotationAsHash('event:', [null, 'args'], $aname, []);
                    $cache = static::getAnnotationAsString('cache', $aname, null);
                    if ($cache !== null) {
                        // アノテーション由来だしキャッシュされるしなので eval でも問題はない
                        $events['cache'] = ['args' => $cache ? eval("return $cache;") : '60'];
                    }
                    $public = static::getAnnotationAsString('public', $aname, null);
                    if ($public !== null) {
                        // アノテーション由来だしキャッシュされるしなので eval でも問題はない
                        $events['public'] = ['args' => $public ? eval("return $public;") : '60'];
                    }
                    $paramannotations = static::getAnnotationAsHash('param', ['type', null, 'comment'], $aname, []);
                    return [
                        // ルーティング系
                        '@default-route'  => static::getAnnotationAsBool('default-route', $aname, true),
                        '@route'          => static::getAnnotationAsHash('route', [null, 'comment'], $aname, []),
                        '@redirect'       => static::getAnnotationAsHash('redirect', [null, 'status', 'comment'], $aname, []),
                        '@rewrite'        => static::getAnnotationAsHash('rewrite', [null, 'comment'], $aname, []),
                        '@regex'          => static::getAnnotationAsHash('regex', [null, 'comment'], $aname, []),
                        // アクション系
                        '@events'         => array_map(fn($v) => preg_split('#\s*,\s*#', $v['args']), $events),
                        '@action'         => array_map('strtoupper', static::getAnnotationAsList('action', ',', $aname, [])),
                        '@argument'       => array_map('strtoupper', static::getAnnotationAsList('argument', ',', $aname, [])),
                        // メタデータ系
                        '@authentication' => static::getAnnotationAsString('authentication', $aname, null),
                        '@origin'         => static::getAnnotationAsList('origin', ',', $aname, []),
                        '@ajaxable'       => static::getAnnotationAsInt('ajaxable', $aname, null),
                        '@queryable'      => static::getAnnotationAsBool('queryable', $aname, true),
                        // パラメータ系
                        '@context'        => static::getAnnotationAsList('context', ',', $aname, ['']),
                        'parameters'      => array_map(function (\ReflectionParameter $parameter) use ($paramannotations) {
                            $typemap = [
                                'bool'    => 'boolean',
                                'boolean' => 'boolean',
                                'int'     => 'integer',
                                'integer' => 'integer',
                                'float'   => 'double',
                                'double'  => 'double',
                                'null'    => 'null',
                            ];
                            $pname = $parameter->name;
                            $types = [];
                            $defaultable = $parameter->isDefaultValueAvailable();
                            $default = $defaultable ? $parameter->getDefaultValue() : null;

                            // アノテーションから
                            if (isset($paramannotations["\$$pname"])) {
                                foreach (explode('|', strtolower($paramannotations["\$$pname"]['type'])) as $tname) {
                                    $types[$typemap[strtolower($tname)] ?? $tname] = 'annotation';
                                }
                            }
                            // タイプヒントから
                            if ($parameter->hasType()) {
                                $type = $parameter->getType();
                                $tname = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
                                $types[$typemap[strtolower($tname)] ?? $tname] = 'typehint';
                                if ($parameter->allowsNull()) {
                                    $types['null'] = 'typehint';
                                }
                            }
                            // デフォルト値があるならその型を加える
                            if ($defaultable) {
                                $tname = gettype($default);
                                $types[$typemap[strtolower($tname)] ?? $tname] = 'typehint';
                            }
                            // 後始末（NULL は特殊すぎるので後ろに持っていく）
                            if (array_key_exists('null', $types)) {
                                $tmp = $types['null'];
                                unset($types['null']);
                                $types['null'] = $tmp;
                            }
                            return [
                                'name'        => $pname,
                                'type'        => $types,
                                'defaultable' => $defaultable,
                                'default'     => $default,
                            ];
                        }, $action->getParameters()),
                    ];
                }, (function () {
                    $actions = [];
                    foreach (get_class_methods(static::class) as $method) {
                        if (preg_match("#(.+)" . static::ACTION_SUFFIX . "$#", $method, $m)) {
                            $actions[$m[1]] = static::reflect($method);
                        }
                    }
                    return $actions;
                })()),
            ];
            // @codeCoverageIgnoreEnd
        }

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
     *
     * @param string $namespace オートロードする名前空間
     * @param array $ctor_args コンストラクタ引数
     * @return array 現在の名前空間配列
     */
    public static function autoload($namespace, $ctor_args = [])
    {
        $namespace = trim($namespace, '\\');
        self::$namespaces[$namespace] = $ctor_args;
        self::$instances[$namespace] = [];
        return array_keys(self::$namespaces);
    }

    final public function __construct(Service $service, $action, Request $request = null)
    {
        $this->service = $service;
        $this->action = $action;
        $this->request = $request ?? $this->service->request;
        $this->response = new \ryunosuke\microute\http\Response();

        $this->construct();
    }

    public function __get($name)
    {
        if ($name === 'session') {
            return $this->request->getSession();
        }

        // オートロード空間から一致するクラスを返す
        foreach (self::$namespaces as $namespace => $ctor_args) {
            if (class_exists($class = "$namespace\\$name") && (new \ReflectionClass($class))->getName() === $class) {
                return self::$instances[$class] ??= new $class(...$ctor_args);
            }
        }

        return $this->Accessable__get($name);
    }

    public function __toString()
    {
        if ($this) {
            throw new \DomainException(__METHOD__ . ' is not supported');
        }
        return ''; // @codeCoverageIgnore
    }

    /**
     * 名前空間からの相対パスを返す
     *
     * 実質的にビューファイルへのパス取得として機能する。
     *
     * @return string 相対パス
     */
    public function location()
    {
        $classname = $this->service->dispatcher->shortenController(static::class);
        return str_replace('\\', '/', $classname) . '/' . $this->action;
    }

    /**
     * Symfony\Component\HttpFoundation\Cookie を生成して返す
     *
     * 第2引数以降で配列ではなく個別指定できるが特に意味はない（配列のキーを明示するために定義している）。
     * 引数で作成したい場合は普通に new Cookie すればいい。
     *
     * @param array $default 引数配列
     * @param string|null $name The name of the cookie
     * @param string|null $value The value of the cookie
     * @param int|string|\DateTimeInterface $expire The time the cookie expires
     * @param string|null $path The path on the server in which the cookie will be available on
     * @param string|null $domain The domain that the cookie is available to
     * @param bool|null $secure Whether the client should send back the cookie only over HTTPS or null to auto-enable this when the request is already using HTTPS
     * @param bool $httpOnly Whether the cookie will be made accessible only through the HTTP protocol
     * @param bool $raw Whether the cookie value should be sent with no url encoding
     * @param string|null $sameSite Whether the cookie will be available for cross-site requests
     * @return Cookie
     */
    public function cookie(array $default, string $name = null, string $value = null, $expire = 0, ?string $path = '/', string $domain = null, ?bool $secure = false, bool $httpOnly = true, bool $raw = false, string $sameSite = null)
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
     *
     * @param \Closure $tack 実行する処理
     * @param int $timeout 後処理のタイムアウト（0は無制限。request_terminate_timeout_track_finished:no だと決してタイムアウトしないので注意）
     */
    public function background($tack, $timeout = 0)
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
     * 指定 URL へリダイレクト
     *
     * @param string $url リダイレクト URL
     * @param int $status ステータスコード
     * @return RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
        return $this->response(new RedirectResponse($url, $status));
    }

    /**
     * 指定 URL へリダイレクト（自身のホストのみ）
     *
     * @param string $url リダイレクト URL
     * @param string $default ダメだった場合のデフォルト
     * @param int $status ステータスコード
     * @return RedirectResponse
     */
    public function redirectInternal($url, $default = '/', $status = 302)
    {
        $hostname = parse_url($url, PHP_URL_HOST);
        if ($hostname !== null && $hostname !== $this->request->getHost()) {
            $url = $default;
        }
        return $this->redirect($url, $status);
    }

    /**
     * 指定ルートへリダイレクト
     *
     * @param string $route ルート名
     * @param array $params パラメータ
     * @param int $status ステータスコード
     * @return RedirectResponse
     */
    public function redirectRoute($route, $params = [], $status = 302)
    {
        $url = $this->service->router->reverseRoute($route, $params);
        return $this->redirect($url, $status);
    }

    /**
     * 指定 Controller/Action へリダイレクト
     *
     * @param array|string|null $eitherParamsOrAction パラメータあるいはアクション名
     * @param string|null $action アクション名
     * @param string|null $controller コントローラ名
     * @param int $status ステータスコード
     * @return RedirectResponse
     */
    public function redirectThis($eitherParamsOrAction = null, $action = null, $controller = null, $status = 302)
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
     *
     * @param array $params パラメータ
     * @param int $status ステータスコード
     * @return RedirectResponse
     */
    public function redirectCurrent($params = [], $status = 302)
    {
        $url = $this->service->resolver->current($params);
        return $this->redirect($url, $status);
    }

    /**
     * JSON レスポンスを返す
     *
     * @param array $data JSON データ
     * @param int $jsonOptions JSON オプション
     * @return JsonResponse
     */
    public function json($data = [], $jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
    {
        // JsonResponse は後から setEncodingOptions を呼ぶと decode/encode が走るので注意すること
        $jsonData = json_encode($data, $jsonOptions);
        return $this->response(new JsonResponse($jsonData, 200, [], true));
    }

    /**
     * ファイルの内容をそのままレスポンスする
     *
     * @param string $filename レスポンスファイル
     * @param bool $httpcache 304 キャッシュを使用するか
     * @param bool $public cache-control:public フラグ
     * @return Response
     */
    public function content($filename, $httpcache = true, $public = false)
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
     *
     * @param \Closure|\SplFileInfo|string $eitherContentOrFileinfo クロージャかファイルオブジェクトかダウンロードの中身
     * @param string|null $filename ダウンロードファイル名
     * @return Response
     */
    public function download($eitherContentOrFileinfo, $filename = null)
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
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', $attachment($filename ?? $eitherContentOrFileinfo->getFilename()));
        }
        elseif ($eitherContentOrFileinfo instanceof \Closure) {
            $response = new StreamedResponse($eitherContentOrFileinfo, $this->response->getStatusCode());
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', $attachment($filename));
        }
        else {
            $response = new Response($eitherContentOrFileinfo, $this->response->getStatusCode());
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', $attachment($filename));
            $response->headers->set('Content-Length', strlen($eitherContentOrFileinfo));
        }

        return $this->response($response);
    }

    /**
     * プッシュ（SSE）レスポンスを返す
     *
     * $provider は前回のデータを引数として受けるので、流したいデータを返せばそれが SSE のレスポンスとして返される。
     * いわゆるポーリングとして動作するので、適宜 sleep 処理などを必ず入れなければならない。
     * 返り値はレコード or レコード配列でなければならない。
     * 「レコード」とは返すべきデータを表す。
     *
     * レコードがプリミティブの場合は単純に data として送出される。
     * レコードが配列の場合は json_encode して data として送出される。
     * レコードがオブジェクトの場合はイテレータ結果が送出される。
     * その際、フィールドが配列の場合は json_encode, オブジェクトの場合は __toString/json_encode される（__toString 優先）。
     *
     * @param \Closure $provider データを返すクロージャ
     * @param int|float $timeout タイムアウト秒
     * @param bool $testing テストフラグ（出力を扱うため引数で与える）
     * @return StreamedResponse
     */
    public function push($provider, $timeout = 10, $testing = false)
    {
        $response = new StreamedResponse(function () use ($provider, $timeout, $testing) {
            session_write_close();
            $mtime = microtime(true);
            while (true) {
                if (connection_aborted() || $timeout < (microtime(true) - $mtime)) {
                    break;
                }

                $value = $provider($prev ?? []);
                if ($value === []) {
                    usleep(1 * 1000); // 念の為
                    continue;
                }
                $prev = $value;

                $values = (is_array($value) || $value instanceof \Generator) ? $value : [$value];
                foreach ($values as $record) {
                    if (is_array($record)) {
                        echo 'data: ' . json_encode($record) . "\n";
                    }
                    elseif (is_object($record)) {
                        foreach ($record as $k => $v) {
                            if (is_array($v) || (is_object($v) && !method_exists($v, '__toString'))) {
                                $v = json_encode($v);
                            }
                            foreach (preg_split('#\\R#u', strval($v)) as $line) {
                                echo "$k: $line\n";
                            }
                        }
                    }
                    else {
                        foreach (preg_split('#\\R#u', strval($record)) as $line) {
                            echo "data: $line\n";
                        }
                    }
                    echo "\n";
                }

                if (!$testing) {
                    Response::closeOutputBuffers(0, true); // @codeCoverageIgnore
                }
                flush();

                if ($testing && $value instanceof \Generator) {
                    break;
                }
            }
        }, $this->response->getStatusCode());
        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cach-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $this->response($response);
    }

    public function dispatch($args = [], $error_handling = true)
    {
        $metadata = static::metadata($this->service->cacher);

        // 認証
        [$authmethod, $realm] = explode(' ', $metadata['actions'][$this->action]['@authentication'] ?? '', 2) + [1 => 'Enter username and password'];
        $authentications = [
            'basic'                       => $metadata['actions'][$this->action]['@basic-auth'] ?? null,
            'digest'                      => $metadata['actions'][$this->action]['@digest-auth'] ?? null,
            strtolower(trim($authmethod)) => ['realm' => trim($realm)], // for compatible
        ];
        foreach ($authentications as $authmethod => $option) {
            if (strlen($authmethod) && $option) {
                $this->request->attributes->remove('authname');
                $authname = $this->authenticate($authmethod, $option['realm']);
                if (!strlen($authname)) {
                    return $this->response;
                }
                $this->request->attributes->set('authname', $authname);
            }
        }

        // 5大イベントディスパッチ
        try {
            // init は初期化処理（Response の返却を許す）
            $this->service->logger->info(get_class($this) . " init");
            $response = $this->init();
            if ($response instanceof Response) {
                return $this->response($response);
            }

            // before は共通事前処理
            $this->service->logger->info(get_class($this) . " before");
            $this->before();

            // action はメイン処理
            $this->service->logger->info(get_class($this) . " action");
            $this->response($this->action($args));

            // after は共通事後処理
            $this->after();

            // finish は後始末処理（Response の返却を許す）
            $this->service->logger->info(get_class($this) . " finish");
            $response = $this->finish();
            if ($response instanceof Response) {
                return $this->response($response);
            }

            return $this->response;
        }
        catch (ThrowableResponse $response) {
            $this->service->logger->info(get_class($this) . " throw");
            return $this->response($response->response());
        }
        catch (\Throwable $t) {
            // コントローラレベルの例外ハンドリング
            if ($error_handling) {
                $this->service->logger->info(get_class($this) . " error");
                $response = $this->catch($t);
                if ($response instanceof Response) {
                    if ($t instanceof HttpException) {
                        $response->headers->add($t->getHeaders());
                    }
                    return $this->response($response);
                }
                throw new \RuntimeException('Controller#error is must be return Response.');
            }
            throw $t;
        }
        finally {
            $this->finally($this->response);
        }
    }

    public function action($args)
    {
        // pre-action
        if (($result = $this->dispatchEvent('pre')) instanceof Response) {
            return $this->response($result);
        }

        // アクション実行
        $result = ([$this, $this->action . static::ACTION_SUFFIX])(...$args);

        // 返り値が string ならレスポンンスボディ
        if (is_string($result)) {
            $this->response->setContent($result);
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
            return $this->response($result);
        }

        return $this->response;
    }

    private function dispatchEvent($phase)
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
    }

    /**
     * 認証ヘッダを返す
     *
     * @param string $method 認証メソッド（basic or digest）
     * @param string $realm realm 文字列
     * @return string|null ユーザ名
     */
    protected function authenticate(string $method, string $realm)
    {
        // クオートは RFC 的に規定はされているようだが、詳細な情報を見つけられなかった
        // そもそも Edge では正しく解釈してくれない挙動を示したのでいっその事不可とする
        if (strpos($realm, '"') !== false) {
            throw new \DomainException('realm should not be contains \'"\'');
        }

        $provider = $this->service->authenticationProvider;
        $passworder = function ($username) use ($provider) {
            if ($provider instanceof \Closure) {
                return $provider($username);
            }
            else {
                return isset($provider[$username]) ? $provider[$username] : null;
            }
        };

        $methods = [
            'basic'  => [
                'verify' => function () use ($passworder) {
                    $username = $this->request->server->get('PHP_AUTH_USER');
                    $password = $this->request->server->get('PHP_AUTH_PW');
                    $comparator = $this->service->authenticationComparator;
                    return $comparator($passworder($username), $password) ? $username : null;
                },
                'header' => fn() => sprintf('Basic realm="%s"', $realm),
            ],
            'digest' => [
                'verify' => function () use ($passworder, $realm) {
                    $md5implode = static fn($_) => md5(implode(':', func_get_args()));
                    $keys = ['response', 'nonce', 'nc', 'cnonce', 'qop', 'uri', 'username'];
                    $digest = $this->request->server->get('PHP_AUTH_DIGEST');

                    preg_match_all('@(' . implode('|', $keys) . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);
                    $data = array_reduce($matches, static function ($data, $m) {
                        $data[$m[1]] = $m[3] ?: $m[4];
                        return $data;
                    }, array_fill_keys($keys, ''));

                    $counter = $this->service->authenticationNoncer;
                    $ncount = $counter($data['nonce']);
                    $ncount = $ncount === null ? $data['nc'] : sprintf('%08x', $ncount);

                    $username = $data['username'];
                    $password = $passworder($username);
                    $response = $md5implode(
                        $md5implode($username, $realm, $password),
                        $data['nonce'],
                        $ncount,
                        $data['cnonce'],
                        $data['qop'],
                        $md5implode($this->request->getMethod(), $this->request->getRequestUri())
                    );
                    return hash_equals($response, $data['response']) ? $data['username'] : null;
                },
                'header' => function () use ($realm) {
                    $counter = $this->service->authenticationNoncer;
                    return sprintf('Digest realm="%s", nonce="%s", algorithm=MD5, qop="auth"', $realm, $counter(null));
                },
            ],
        ];

        if (!isset($methods[$method])) {
            throw new \DomainException("$method is not supported.");
        }

        $username = $methods[$method]['verify']();
        if (!strlen($username)) {
            $this->response->headers->set('WWW-Authenticate', $methods[$method]['header']());
            $this->response->setStatusCode(401);
            return null;
        }
        return $username;
    }

    /**
     * 明確に LastModified が分かる場合にキャッシュを使うようにするメソッド
     *
     * 例えば DB レコードごとに更新日時が保持されていて
     * - 更新されていたら通常レスポンス
     * - されていないなら 304 レスポンス
     * のような場合に使用できる。
     *
     * @param Response $response レスポンスオブジェクト
     * @param \DateTime|int|null $lastModified 最終更新時刻
     * @return bool キャッシュの設定に成功したら(304を返すはずなら) true
     */
    protected function cache(Response $response, $lastModified = null)
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
            header_remove('Expires');
            header_remove('Cache-Control');
            header_remove('Pragma');
            // @codeCoverageIgnoreEnd
        }

        return $response->isNotModified($this->request);
    }

    protected function cacheEvent($phase, $expire)
    {
        // debug 中は無効
        if ($this->service->debug) {
            return;
        }

        // http 的キャッシュが許容されているのは GET, HEAD のみ
        if (!$this->request->isMethodCacheable()) {
            return;
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
    }

    protected function publicEvent($phase, $expire)
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
     * @param mixed $action_value アクションメソッドの返り値
     * @return string|Response レスポンス
     */
    protected function render($action_value)
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
            return $this->response->setContent((static function (...$dummy) {
                unset($dummy);
                ob_start();
                extract(func_get_arg(1));
                include func_get_arg(0);
                return ob_get_clean();
            })('/path/to/view/' . $this->location() . '.phtml', $this->request->attributes->get('parameter', [])));
        }
    }

    /**
     * 現在設定されている汎用的なヘッダ等を再設定して返す
     *
     * @template T of Response
     * @param T $response
     * @return T
     */
    protected function response(Response $response)
    {
        if ($this->response === $response) {
            return $this->response;
        }

        if (!$response instanceof RedirectResponse && $response->getStatusCode() === 200) {
            $response->setStatusCode($this->response->getStatusCode());
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

    protected function init() { }

    protected function before() { }

    protected function after() { }

    protected function finish() { }

    protected function error(\Throwable $t) { } // delete in future scope

    protected function catch(\Throwable $t)
    {
        // for compatible
        return $this->error($t); // @codeCoverageIgnore
    }

    protected function finally(Response $response) { }
}
