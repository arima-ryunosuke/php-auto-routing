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

    /** @var Service */
    private $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * URL ビルダー（ホスト名）
     *
     * 単純にホスト名を返す。
     *
     * @return string URL
     */
    public function host()
    {
        return $this->service->request->getHttpHost();
    }

    /**
     * URL ビルダー（ルート名）
     *
     * ルート名、パラメータ配列から URL を生成。
     *
     * @param string $route ルート名
     * @param array $params クエリパラメータ
     * @return string URL
     */
    public function route($route, $params = [])
    {
        return $this->service->router->reverseRoute($route, $params);
    }

    /**
     * URL ビルダー（コントローラ・アクション）
     *
     * @param string $controller コントローラ名
     * @param string $action アクション名
     * @param array $params パラメーター
     * @param string|null $base エイリアス名
     * @return string URL
     */
    public function url($controller, $action = '', $params = [], $base = null)
    {
        $chain_case_pattern = $this->service->routeAbbreviation ? '#(?<!/)[A-Z]([A-Z](?![a-z]))*#' : '#(?<!/)[A-Z]#';

        // コンテキストの切り離し
        $parts = pathinfo($action);
        $context = $parts['extension'] ?? '';
        $action = $parts['filename'] ?? '';

        $maction = lcfirst(strtr(ucwords($action, " \t\r\n\f\v-"), ['-' => '']));

        // default アクションはアクションなしと等価（設定レベルではなく規約レベル）
        if ($action === 'default') {
            $action = '';
        }
        $action = ltrim(strtolower(preg_replace($chain_case_pattern, '-$0', $action)), '-');

        $controller = $this->service->dispatcher->resolveController($controller);
        $class_name = $this->service->dispatcher->shortenController($controller);
        $class_name = preg_replace('#\\\\?Default$#', '', $class_name);
        $class_name = ltrim(strtolower(preg_replace($chain_case_pattern, '-$0', str_replace('\\', '/', $class_name))), '-');

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
            $action_data = $metadata['actions'][$maction];
            $parameters = $action_data['parameters'];
            $pathinfo = '';
            if ($action_data['@queryable']) {
                // 起動パラメータとして渡ってくることがあるので読み替えなければならない
                $newparams = [];
                foreach ($params as $key => $value) {
                    if (is_int($key)) {
                        $key = $parameters[$key]['name'];
                    }
                    $newparams[$key] = $value;
                }
                $params = $newparams;
            }
            else {
                $querymap = [];

                // 起動パラメータとして検索（起動パラメータなのでただの配列のときのみ）
                foreach ($params as $key => $value) {
                    if (is_int($key)) {
                        $querymap[] = rawurlencode($params[$key]);
                        unset($params[$key]);
                    }
                }

                // パラメータ名として検索（名前が一致するもの）
                foreach ($parameters as $n => $parameter) {
                    $name = $parameter['name'];
                    if (array_key_exists($name, $params)) {
                        $querymap[$n] = rawurlencode($params[$name]);
                        unset($params[$name]);
                    }
                }

                // すべてデフォルト引数持ちで引数なしもありうるのでチェック
                if (count($querymap)) {
                    $delimiter = $this->service->parameterDelimiter;
                    $separator = $this->service->parameterSeparator;
                    $pathinfo = $delimiter . implode($separator, $querymap);
                }
            }

            $querysep = ($pathinfo[0] ?? '') === '?' ? '&' : '?';
            $action .= $pathinfo . (strlen($context) ? ".$context" : '') . ($params ? $querysep . http_build_query($params) : '');
        }

        if ($this->service->defaultActionAsDirectory) {
            $url = rtrim($base, '/') . "/$action";
        }
        else {
            $url = rtrim($base, '/') . (strlen($action) ? '/' . $action : '');
        }
        return strlen($url) ? $url : '/';
    }

    /**
     * URL ビルダー（アクション）
     *
     * url メソッドの引数簡易版として機能する。
     *
     * @param string|null $eitherControllerOrAction コントローラ名 or アクション名
     * @param string|null $eitherActionOrParams アクション名 or クエリパラメータ
     * @param array $params クエリパラメータ
     * @return string URL
     */
    public function action($eitherControllerOrAction = null, $eitherActionOrParams = null, array $params = [])
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
     *
     * @param array $params クエリパラメータ
     * @param ?array $current 現在パラメータ。null を与えると現在の物がそのまま適用される。空配列を渡すと完全にリセットされる
     * @return string URL
     */
    public function current($params = [], $current = null)
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
     * - "/"  から始まらない :hostname/base/path/controller/action/{$filename}
     *
     * @param string|null $filename 静的ファイル名
     * @param array $minSuffixes 探す min ファイルサフィックス
     * @param string|array $query クエリパラメータ
     * @return string URL
     */
    public function path($filename = null, $minSuffixes = [], $query = [])
    {
        // for compatible
        [$minSuffixes, $query, $appendmtime] = (function ($minSuffixes, $query) {
            // 第2引数が $appendmtime だった時代
            if (is_bool($minSuffixes)) {
                return [[], '', $minSuffixes];
            }
            // 第2引数が $query だった時代
            if (is_array($minSuffixes) && array_values($minSuffixes) !== $minSuffixes) {
                return [[], $minSuffixes, true];
            }
            // 現行は第2引数が $minSuffixes
            return [$minSuffixes, $query, true];
        })($minSuffixes, $query);

        if (is_array($query) || is_object($query)) {
            $query = preg_replace('#%5B\d+%5D=#', '%5B%5D=', http_build_query($query));
        }

        $docroot = rtrim($this->service->request->server->get('DOCUMENT_ROOT'), '/');
        $basepath = rtrim($this->service->request->getBasePath(), '/');
        $urlparts = parse_url($filename);

        $suffix = function (&$filepath, $fullpather) use ($minSuffixes) {
            $minSuffixes[] = '';
            foreach ($minSuffixes as $suffix) {
                $minfilename = preg_replace("@^(.+?)(\.[^.]+?(\?|#|$))@u", "$1$suffix$2", $filepath);
                $fullpath = $fullpather($minfilename);
                if (is_file($fullpath)) {
                    $filepath = $minfilename;
                    return $fullpath;
                }
            }
            return null;
        };

        // スキーム付き URL ならそのまま返す
        if (isset($urlparts['scheme'])) {
            // 「同じリポジトリだけど静的ファイルは別ホストに分けている」という状況があるので min 検出と更新日時付与も試みる
            $fullpath = $suffix($filename, fn($path) => $docroot . $basepath . parse_url($path, PHP_URL_PATH));
            if ($fullpath !== null && $appendmtime) {
                $query = filemtime($fullpath) . (strlen($query) ? '&' : '') . $query;
            }
            return $filename . (strlen($query) ? (isset($urlparts['query']) ? '&' : '?') . $query : '');
        }

        // 空っぽはベースパスを表す
        if (strlen($filename) === 0) {
            $filepath = $basepath . '/';
        }
        // "//" から始まる場合はフルパスとみなす（本来別の意味だが、同一アプリ内で // から始めることなんてほとんど無い）
        elseif (substr($filename, 0, 2) === '//') {
            $filepath = substr($filename, 1);
        }
        // "/" ならベースパスからの相対
        elseif ($filename[0] === '/') {
            $filepath = $basepath . $filename;
        }
        // それ以外はカレントパスからの相対
        else {
            $currentpath = trim(preg_replace('#(^.+)(/.*)$#', '$1', $this->service->request->getPathInfo()), '/');
            $filepath = $basepath . '/' . $currentpath . '/' . $filename;
        }

        $fullpath = $suffix($filepath, fn($path) => $docroot . '/' . strstr($path . '?', '?', true));
        if ($fullpath !== null && $appendmtime) {
            $query = filemtime($fullpath) . (strlen($query) ? '&' : '') . $query;
        }
        return $filepath . (strlen($query) ? (isset($urlparts['query']) ? '&' : '?') . $query : '');
    }

    /**
     * クエリビルダー
     *
     * 現在のクエリに付与・除去した新しいクエリストリングを返す。
     * 連想配列は再帰する。
     * クロージャを与えると元の値を引数としてコールバックされる（ない場合は null）。
     * null を与えるとパラメータから除去される。
     *
     * @param array $params 付与・除去するパラメータ
     * @param ?array $current 元となるクエリパラメータ（デバッグ・テスト用）
     * @return string クエリ文字列
     */
    public function query($params, $current = null)
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
     *
     * @param string $file ファイル名
     * @param string|null $mimetype MIME タイプ（省略時は自動検出）
     * @param string|null $charset charset（省略時は自動設定）
     * @param string|null $encode エンコード方法（省略時は自動設定）
     * @return string data URI
     */
    public function data($file, $mimetype = null, $charset = null, $encode = null)
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
