<?php
namespace ryunosuke\microute\example\controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * @alias /alias
 * @scope (?<pref_id>\d+)/
 */
class DefaultController extends AbstractController
{
    protected function error(\Exception $ex)
    {
        if ($ex instanceof \DomainException) {
            throw $ex;
        }
        return new Response('これはコントローラ単位のエラーハンドリングでハンドリングされた例外メッセージです：' . $ex->getMessage());
    }

    public function errorAction(\Exception $ex)
    {
        $this->view->exception = $ex;
    }

    /**
     * @action GET
     * @route default-index
     */
    public function defaultAction()
    {
        $this->view->url = $this->request->getRequestUri();
    }

    public function relativeAction($pref_id)
    {
        return '相対リンクです。'
            . '<p>ダイレクトにここに来た場合はパラメータが一致しないエラーになります</p>'
            . '<p>scope 経由でここに来た場合は URL がスコープ付きになっていてかつスコープパラメータが渡ってきています</p>'
            . '<pre>' . var_export($this->request->getRequestUri(), true) . '</pre>'
            . '<pre>pref_id: ' . var_export($pref_id, true) . '</pre>';
    }

    public function urlsAction()
    {
        return '存在する URL の一覧です<pre>' . var_export($this->service->router->urls(), true);
    }

    /**
     * @origin http://localhost
     * @origin http://localhost:8000, http://localhost:3000
     */
    public function originAction()
    {
    }

    public function sessionAction()
    {
        $times = $this->session->get('times', []);
        $times[] = time();
        $this->session->set('times', $times);
        $cookie = array_filter($_COOKIE, function ($k) { return strpos($k, session_name()) === 0; }, ARRAY_FILTER_USE_KEY);
        uksort($cookie, function ($a, $b) { return strnatcmp($a, $b); });
        return 'セッションデータです。セッションは cookie ストレージで、1分間継続、256バイト毎に分割されるように設定されています<pre>'
            . "<strong>cookie data</strong>\n"
            . var_export($cookie, true)
            . "\n"
            . "<strong>session data</strong>\n"
            . var_export($this->session->all(), true)
            . "\n";
    }

    public function jsonAction()
    {
        if ($this->request->getContentType() === 'json') {
            $json = $this->request->request->all();
            $json['microtime'] = microtime(true);
            return $this->json($json, JSON_PRETTY_PRINT);
        }
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $default
     * @return string
     */
    public function argumentAction($id, $name, $default = 'default')
    {
        return 'クエリストリングがアクションメソッドの引数に渡ってきます<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => compact('id', 'name', 'default'),
            ], true);
    }

    /**
     * @queryable false
     * @context ,json
     * @param int $id
     * @return string
     */
    public function pathfulAction($id)
    {
        return '/ 区切りでアクションメソッドの引数に渡ってきます<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => ['id' => $id, 'context' => $this->request->attributes->get('context')],
            ], true);
    }

    /**
     * @argument file
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @return string
     */
    public function uploadAction($file = null)
    {
        if ($this->request->isMethod('POST')) {
            return '/ 区切りでアクションメソッドの引数に渡ってきます<pre>' . var_export([
                    'url'  => $this->request->getRequestUri(),
                    'file' => $file,
                ], true);
        }
    }

    /**
     * @redirect /hoge 302
     * @redirect /fuga 303
     * @rewrite /piyo
     */
    public function originalAction()
    {
        return 'このページは /hoge でも /fuga でも /piyo でもアクセスできます。/hoge は 302 リダイレクト、 /fuga は 303 リダイレクト、 /piyo は URL そのままです<pre>' . var_export([
                'url' => $this->request->getRequestUri(),
            ], true);
    }

    /**
     * @regex /regex/(?<id>\d+)-([a-z]+)
     * @param int $id
     * @param string $name
     * @return string
     */
    public function articleAction($id, $name)
    {
        return '$id は名前付きキャプチャ、$name は2番目マッチで2番目の引数に渡ってきます<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => compact('id', 'name'),
            ], true);
    }

    /**
     * @regex /[_a-zA-Z0-9]+
     * @return string
     */
    public function anyregexAction()
    {
        return '本来なら [_\-a-zA-Z0-9]+ にマッチするあらゆるリクエストがここに到達しますが、正規表現ルーティングの優先順位を下げているので、他のルーティングで到達しなかった場合のみ到達します';
    }

    /**
     * @context json,xml
     * @queryable false
     * @param int $id
     * @return string
     */
    public function contextAction($id)
    {
        return '@context するとパスの最後に拡張子をつけてもこのアクションに到達します<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => ['id' => $id, 'context' => $this->request->attributes->get('context')],
            ], true);
    }

    public function contentAction()
    {
        return $this->content(__FILE__);
    }

    public function downloadAction()
    {
        return $this->download(new \SplFileObject(__FILE__));
    }

    /**
     * @action get
     * @event:cache 10
     */
    public function cacheAction()
    {
        sleep(3);
        return date('Y/m/d H:i:s') . '：ものすごく重い処理のキャッシュレスポンスです。大体3秒かかりますが10秒間キャッシュされています';
    }

    public function resolverAction()
    {
    }

    public function throwRuntimeAction()
    {
        throw new \RuntimeException(__FUNCTION__);
    }

    public function throwDomainAction()
    {
        throw new \DomainException(__FUNCTION__);
    }
}
