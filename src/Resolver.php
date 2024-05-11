<?php
namespace ryunosuke\microute;

use ryunosuke\microute\mixin\Utility;

/**
 * リゾルバクラス
 *
 * URL を生成したりする。主にビュー内で使うことを想定。
 */
class Resolver
{
    use Utility;

    const CACHE_KEY = 'Resolver' . Service::CACHE_VERSION;

    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * URL ビルダー（ホスト名）
     *
     * 単純にホスト名を返す。
     */
    public function host(): string
    {
        return $this->service->request->getHttpHost();
    }

    /**
     * URL ビルダー（ルート名）
     *
     * ルート名、パラメータ配列から URL を生成。
     */
    public function route(string $route, array $params = []): string
    {
        return $this->service->router->reverseRoute($route, $params);
    }

    /**
     * URL ビルダー（コントローラ・アクション）
     */
    public function url(string $controller, string $action = '', array $params = [], ?string $base = null): string
    {
        // コンテキストの切り離し
        $parts = pathinfo($action);
        $context = $parts['extension'] ?? '';
        $action = $parts['filename'] ?? '';

        $maction = lcfirst(strtr(ucwords($action, " \t\r\n\f\v-"), ['-' => '']));
        $action = $this->actionMethodToAction($action);

        /** @var Controller::class $controller */
        $controller = $this->service->dispatcher->resolveController($controller);
        $class_name = $this->service->dispatcher->shortenController($controller);
        $class_name = preg_replace('#\\\\?Default$#', '', $class_name);
        $class_name = ltrim(strtolower(preg_replace('#(?<!/)[A-Z]([A-Z](?![a-z]))*#', '-$0', str_replace('\\', '/', $class_name))), '-');

        if ($base === '') {
            $base = '/' . $class_name;
        }
        elseif ($base === null) {
            $base = $this->service->request->getBasePath() . '/' . $class_name;
        }
        else {
            $base = $this->service->request->getBasePath() . $base;
        }

        $metadata = $controller::metadata($this->service->cacher);

        if ($metadata['@scope']) {
            $parameter = $this->service->request->attributes->get('parameter', []);
            foreach ($metadata['@scope'] as $scope => $comment) {
                $paramnames = $this->regexParameter($scope);
                if (array_intersect_key($paramnames, $parameter) === $paramnames) {
                    $action = $this->reverseRegex($scope, $parameter) . $action;
                    break;
                }
            }
        }

        if (strlen($maction)) {
            $action .= (strlen($context) ? ".$context" : '') . ($params ? '?' . http_build_query($params) : '');
        }

        $url = rtrim($base, '/') . "/$action";
        return strlen($url) ? $url : '/';
    }

    /**
     * URL ビルダー（アクション）
     *
     * url メソッドの引数簡易版として機能する。
     */
    public function action(string|array|null $eitherControllerOrAction = null, string|array|null $eitherActionOrParams = null, array $params = []): string
    {
        // 引数が3つの時は「controller, action, params」確定
        if (func_num_args() === 3) {
            $controller = $eitherControllerOrAction;
            $action = $eitherActionOrParams;
        }
        // 引数が2つの時は「controller, action」or「action, params」
        elseif (func_num_args() === 2) {
            if (is_array($eitherActionOrParams)) {
                $controller = $this->service->dispatcher->dispatchedController;
                $action = $eitherControllerOrAction;
                $params = $eitherActionOrParams;
            }
            else {
                $controller = $eitherControllerOrAction;
                $action = $eitherActionOrParams;
                $params = [];
            }
        }
        // 引数が1つの時は「action」or「params」
        elseif (func_num_args() === 1) {
            $controller = $this->service->dispatcher->dispatchedController;
            if (is_array($eitherControllerOrAction)) {
                $action = $controller->action;
                $params = $eitherControllerOrAction;
            }
            else {
                $action = $eitherControllerOrAction;
                $params = [];
            }
        }
        // 引数がない時は現在の Controller/Action
        else {
            $controller = $this->service->dispatcher->dispatchedController;
            $action = $controller->action;
            $params = [];
        }

        return $this->url($controller, $action, $params);
    }

    /**
     * URL ビルダー（現在 URL）
     *
     * 現在 URL を返す。
     * ただし、クエリパラメータを与えればクエリ部分は書き換えることができる。
     * $current に null を与えると現在の物がそのまま適用される。空配列を渡すと完全にリセットされる。
     */
    public function current(array $params = [], ?array $current = null): string
    {
        $basepath = rtrim($this->service->request->getBasePath(), '/');
        $currentpath = $this->service->request->getPathInfo();

        return $basepath . $currentpath . $this->query($params, $current);
    }

    /**
     * URL ビルダー（静的ファイル）
     *
     * ファイル名に公開パスと更新日時を付加したものを生成。
     * ファイル名省略時はベースパスを返す。
     *
     * - スキーム付き完全URL :指定された $filename
     * - "//" から始まる     :hostname/{$filename}
     * - "/"  から始まる     :hostname/base/path/{$filename}
     * - "/"  から始まらない :hostname/base/path/controller/{$filename}
     *
     * $query に文字列を渡すと更新日時クエリのキーとして付与される。
     */
    public function path(string $filename = '', string|array $query = 'v'): string
    {
        $docroot = rtrim($this->service->request->server->get('DOCUMENT_ROOT'), '/');
        $basepath = trim($this->service->request->getBasePath(), '/');
        $pathinfo = trim(preg_replace('#(^.+)(/.*)$#', '$1', $this->service->request->getPathInfo()), '/');
        $urlparts = parse_url($filename);

        // スキーム付きはそのまま
        if (isset($urlparts['scheme'])) {
            $fullpath = "$docroot/{$urlparts['path']}";
        }
        // 空っぽはベースパスを表す
        elseif (strlen($filename) === 0) {
            $filename = "/$basepath/";
        }
        // "//" から始まる場合はフルパスとみなす（本来別の意味だが、同一アプリ内で // から始めることなんてほとんど無い）
        elseif (substr($filename, 0, 2) === '//') {
            $filename = substr($filename, 1);
        }
        // "/" ならベースパスからの相対
        elseif ($filename[0] === '/') {
            $filename = "/$basepath/$filename";
        }
        // それ以外はカレントパスからの相対
        else {
            $filename = "/$basepath/$pathinfo/$filename";
        }

        if (!isset($fullpath)) {
            $fullpath = "$docroot/" . parse_url($filename, PHP_URL_PATH);
            $filename = preg_replace('#/+#', '/', $filename);
        }

        if (is_string($query)) {
            $query = [$query => fn($fullpath) => is_file($fullpath) ? filemtime($fullpath) : null];
        }

        foreach ($query as $k => $v) {
            if ($v instanceof \Closure) {
                $v = $v($fullpath);
            }
            if ($v === null) {
                unset($query[$k]);
                continue;
            }
            $query[$k] = $v;
        }

        return $filename . ($query ? (isset($urlparts['query']) ? '&' : '?') . http_build_query($query) : '');
    }

    /**
     * クエリビルダー
     *
     * 現在のクエリに付与・除去した新しいクエリストリングを返す。
     * 連想配列は再帰する。
     * クロージャを与えると元の値を引数としてコールバックされる（ない場合は null）。
     * null を与えるとパラメータから除去される。
     */
    public function query(array $params, ?array $current = null): string
    {
        $is_hasharray = function ($array) {
            if (!is_array($array)) {
                return false;
            }
            foreach ($array as $k => $dummy) {
                if (!is_int($k)) {
                    return true;
                }
            }
            return false;
        };
        $main = function ($array, $newarray) use (&$main, $is_hasharray) {
            foreach ($newarray as $k => $v) {
                // 連想配列は再帰する
                if (array_key_exists($k, $array) && $is_hasharray($array[$k]) && $is_hasharray($v)) {
                    $array[$k] = $main($array[$k], $v);
                }
                // クロージャはコールバック
                elseif ($v instanceof \Closure) {
                    $array[$k] = $v($array[$k] ?? null);
                }
                // それ以外は単純上書き
                else {
                    $array[$k] = $v;
                }
            }
            return $array;
        };

        $current = $main($current ?? $this->service->request->query->all(), $params);
        $query = preg_replace('#%5B\d+%5D=#', '%5B%5D=', http_build_query($current));
        if (!strlen($query)) {
            return '';
        }
        return '?' . $query;
    }

    /**
     * data URI 化する
     *
     * いろいろ引数は用意しているが原則として指定しなくても特に問題はない。
     */
    public function data(string $file, ?string $mimetype = null, ?string $charset = null, ?string $encode = null): string
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("$file is not exists.");
        }

        if ($mimetype === null) {
            $mimetype = mime_content_type($file);
        }

        $texttype = explode('/', $mimetype)[0] === 'text';

        if ($charset === null && $texttype) {
            $charset = mb_internal_encoding();
        }
        if (strlen($charset)) {
            $charset = "charset=$charset";
        }

        if ($encode === null && !$texttype) {
            $encode = 'base64';
        }

        switch ($encode) {
            default:
                throw new \InvalidArgumentException("$encode is not supported.");
            case "":
                $data = rawurlencode(file_get_contents($file));
                break;
            case "base64":
                $data = base64_encode(file_get_contents($file));
                break;
        }

        // RFC: data:[<MIME-type>][;charset=<encoding>][;base64],<data>
        return 'data:' . implode(';', array_filter([$mimetype, $charset, $encode], 'strlen')) . ",$data";
    }
}
