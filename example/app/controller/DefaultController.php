<?php
namespace ryunosuke\microute\example\controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[\ryunosuke\microute\attribute\Alias('/alias')]
#[\ryunosuke\microute\attribute\Scope('(?<pref_id>\d+)/')]
class DefaultController extends AbstractController
{
    protected function catch(\Throwable $t)
    {
        if ($t instanceof \DomainException) {
            throw $t;
        }
        $statusCode = 200;
        $headers = [];
        if ($t instanceof HttpException) {
            $statusCode = $t->getStatusCode();
            $headers = $t->getHeaders();
        }
        return new Response('これはコントローラ単位のエラーハンドリングでハンドリングされた例外メッセージです：' . $t->getMessage(), $statusCode, $headers);
    }

    public function errorAction(\Exception $ex)
    {
        $this->view->exception = $ex;
    }

    #[\ryunosuke\microute\attribute\Method('GET')]
    #[\ryunosuke\microute\attribute\Route('default-index')]
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

    #[\ryunosuke\microute\attribute\Origin('http://localhost', 'http://localhost:8000', 'http://localhost:3000')]
    public function originAction()
    {
    }

    public function requestAction()
    {
        return '拡張リクエストです<pre>' . var_export([
                'user-agent' => $this->request->getUserAgent(),
                'referer'    => $this->request->getReferer(),
            ], true);
    }

    public function sessionAction()
    {
        $times = $this->session->get('times', []);
        $times[] = intval(time() / 5) * 5;
        $this->session->set('times', array_unique($times));
        $cookie = array_filter($_COOKIE, function ($k) { return strpos($k, 'SID') === 0; }, ARRAY_FILTER_USE_KEY);
        uksort($cookie, function ($a, $b) { return strnatcmp($a, $b); });
        return 'セッションデータです。'
            . '<p>セッションは cookie ストレージで、1分間継続、256バイト毎に分割されるように設定されています</p>'
            . '<p>1分間継続は最後のアクセスから計測されます。かつセッションクッキーであり、ブラウザを閉じると削除されます</p>'
            . '<p>session.lazy_write が有効だと同じセッションデータの場合は set-cookie を発行しません（このサンプルだと5秒間クッキーを吐きません）</p>'
            . '<pre>'
            . "<strong>cookie data</strong>\n"
            . var_export($cookie, true)
            . "\n"
            . "<strong>session data</strong>\n"
            . var_export($this->session->all(), true)
            . "\n";
    }

    public function backgroundAction()
    {
        $now = date('Y-m-d H:i:s');
        $this->background(function () use ($now) {
            sleep(5);
            file_put_contents(__DIR__ . '/../../public/background.txt', "$now\n", FILE_APPEND);
        });
        return "fpm の場合、この処理は即座に帰りますが、5秒後に<a href='background.txt'>background.txt</a>に「{$now}」が追記されます";
    }

    public function jsonAction()
    {
        if ($this->request->getContentType() === 'json') {
            $json = $this->request->request->all();
            $json['microtime'] = microtime(true);
            return $this->json($json, JSON_PRETTY_PRINT);
        }
    }

    public function argumentAction(int $id, string $name, string $default = 'default')
    {
        return 'クエリストリングがアクションメソッドの引数に渡ってきます<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => compact('id', 'name', 'default'),
            ], true);
    }

    #[\ryunosuke\microute\attribute\Argument('file')]
    public function uploadAction($file = null)
    {
        if ($this->request->isMethod('POST')) {
            return '/ 区切りでアクションメソッドの引数に渡ってきます<pre>' . var_export([
                    'url'  => $this->request->getRequestUri(),
                    'file' => $file,
                ], true);
        }
    }

    #[\ryunosuke\microute\attribute\Redirect('/hoge', 302)]
    #[\ryunosuke\microute\attribute\Redirect('/fuga', 303)]
    #[\ryunosuke\microute\attribute\Rewrite('/piyo')]
    public function originalAction()
    {
        return 'このページは /hoge でも /fuga でも /piyo でもアクセスできます。/hoge は 302 リダイレクト、 /fuga は 303 リダイレクト、 /piyo は URL そのままです<pre>' . var_export([
                'url' => $this->request->getRequestUri(),
            ], true);
    }

    #[\ryunosuke\microute\attribute\Regex('/regex/(?<id>\d+)-([a-z]+)')]
    public function articleAction(int $id, string $name)
    {
        return '$id は名前付きキャプチャ、$name は2番目マッチで2番目の引数に渡ってきます<pre>' . var_export([
                'url'       => $this->request->getRequestUri(),
                'parameter' => compact('id', 'name'),
            ], true);
    }

    #[\ryunosuke\microute\attribute\Regex('/[_a-zA-Z0-9]+')]
    public function anyregexAction()
    {
        return '本来なら [_\-a-zA-Z0-9]+ にマッチするあらゆるリクエストがここに到達しますが、正規表現ルーティングの優先順位を下げているので、他のルーティングで到達しなかった場合のみ到達します';
    }

    #[\ryunosuke\microute\attribute\Context('json', 'xml')]
    public function contextAction(int $id)
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

    public function pushAction()
    {
        if ($this->request->headers->get('accept') !== 'text/event-stream') {
            return;
        }
        return $this->push((function () {
            while (true) {
                yield date('Y-m-dTH:i:s');
                yield [
                    'event' => 'userevent',
                    'data'  => date('Y-m-dTH:i:s'),
                    'retry' => 100,
                ];
                sleep(3);
            }
        })());
    }

    #[\ryunosuke\microute\attribute\Method('GET')]
    #[\ryunosuke\microute\attribute\Event('cache', 10)]
    public function cacheAction()
    {
        $this->session->set('hoge', 'hoge');
        sleep(3);
        if ($this->request->query->has('img')) {
            $this->response->headers->set('content-type', 'image/svg+xml');
            return '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="40"><text y="12" font-size="12">自分自身へのトップレベルではないリクエスト</text></svg>';
        }
        $img = '<img src="./cache?img=true">';
        return date('Y/m/d H:i:s') . '：ものすごく重い処理のキャッシュレスポンスです。大体3秒かかりますが10秒間キャッシュされています<br>' . $img;
    }

    #[\ryunosuke\microute\attribute\Method('GET')]
    #[\ryunosuke\microute\attribute\Context('html')]
    #[\ryunosuke\microute\attribute\Event('public', 10)]
    public function publicAction()
    {
        return 'このレスポンスは初回以降 php ではなく web サーバーが返しています';
    }

    #[\ryunosuke\microute\attribute\RateLimit(10, 20, 'get:id')]
    #[\ryunosuke\microute\attribute\RateLimit(5, 10, 'ip')]
    public function ratelimitAction()
    {
        return 'まだレート制限に達してません';
    }

    public function resolverAction($pref_id)
    {
    }

    public function proxiesAction()
    {
        return '<pre>' . var_export($this->request->getTrustedProxies(), true) . '</pre>';
    }

    public function alternativeClientHintsAction()
    {
        return $this->response->setAlternativeCookieHints();
    }

    public function serverAction()
    {
        $this->view->server = $this->request->server->all();
        $this->view->clientHints = $this->request->getClientHints();
        $this->response->setAcceptClientHints('*');
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
