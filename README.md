Class based auto routing
====

## Description

クラス（コントローラ）ベースでのディスパッチのみを行うマイクロルーティングフレームワークです。

MVC の MV 的な機能は一切ありません。
基本機能は下記だけです。

- Controller の php 名前空間をそのまま URL へマッピングします（基底は変更可）
    - `Hoge\Fuga\PiyoController::actionAction` は `hoge/fuga/piyo/action` になります
    - CamelCase(`HogeFugaController::FooBarAction`) は chain-case(`hoge-fuga/foo-bar`) に変換されます
    - 上記がデフォルトルーティングでコントローラを配置すれば自動でルーティングされます。他の能動的なルーティングとしては下記があります
        - rewrite ルーティング
            - 「ある URL をある URL へリライトする」機能です。apache における「mod_rewrite」と完全に同じ意味です
        - redirect ルーティング
            - 「ある URL をある URL へリダイレクトする」機能です。http における「リダイレクト」と完全に同じ意味です
        - regex ルーティング
            - 「正規表現に一致する URL をある Controller/Action として動作させる」機能です。巷のフレームワークでのいわゆる「ルーティング」と似たような意味です
        - alias ルーティング
            - 「ある URL をある Controller として動作させる」機能です。apache における「mod_alias」とほぼ同じ意味です
        - scope ルーティング
            - 「あるプレフィックスをある Controller として動作させる」機能です。apache における「mod_alias」とほぼ同じ意味です（URL パラメータが使える）
    - ルーティングの設定の仕方は3つあります
        - デフォルトルーティング（CamelCase(`HogeFugaController::FooBarAction`) を chain-case(`hoge-fuga/foo-bar`) に変換）
        - `#[Redirect('fromurl')]`, `#[Regex('#pattern#')]` などの属性によるルーティング
        - Router インスタンスの `redirect・regex` などのメソッドを使用して個別にルーティング
- コントローラ単位のエラーハンドリング
    - アクションメソッド内で例外がスローされた時、そのコントローラ内の error メソッドで捕捉できます
    - その上で error メソッドが例外を投げると DefaultController#errorAction へ回されます
- よく使う機能は属性化してあります
    - `#[Ajaxable]` すると ajax リクエストしか受け付けなくなります
    - `#[Context('json')]` すると `.json` という拡張子アクセスを受け付けるようになります
- アクションメソッドの引数はメソッド定義に基いて自動で引数化されます
    - `hogeAction($id)` というアクションでリクエストパラメータから `id` を取得してマップします
    - その値は型引数でキャストされます
    - データソースは `#[Method]` や `#[Argument]` 属性に基づきます
- クエリストリングではない pathful な URL が構築できます
    - e.g. `/controller/action/123`
    - 上記の 123 はアクションメソッドの第一引数として渡ってきます
    - パラメータの区切り文字は指定できます
- Symfony の BrowserKit でテストできます
    - tests を参照

## Install

```json
{
    "require": {
        "ryunosuke/microute": "dev-master"
    }
}
```

## Demo

```sh
cd /path/to/microute
composer example
# access to "http://hostname:8000"
```

## Usage

然るべきコントローラを配置し、 `Service` クラスを生成して `run` すれば OK です。

```php
$service = new \ryunosuke\microute\Service([
    /* オプション配列 */
    'debug'              => false,
    'controllerLocation' => [
        '\\namespace\\to\\controller' => '/directory/to/controller',
    ],
    // ・・・
]);
$service->run();
```

### オプション

`Service` のコンストラクタ引数は下記のようなものを指定します。

- **debug**: `bool`
    - デバッグフラグを指定します
    - true にするとキャッシュが使われなくなったりログが多くなったりします。開発時は true 推奨です。
    - デフォルトは `false` です
- **cacher**: `\Psr\SimpleCache\CacheInterface`
    - 内部で使用するキャッシュインスタンスを指定します
    - デフォルトは var_export/include による素朴なキャッシュです。 `symfony/cache` `doctrine/cache` などのしっかりとした psr16 ライブラリを使用を推奨します
- **logger**: `callable`
    - 未キャッチ例外をログる callable を渡します
    - デフォルトは何もしません
- **events**: `callable[][]`
    - 各イベントごとに実行されるイベントハンドラを指定します
    - デフォルトは何もしません
- origin: `array|Closure`
    - 許容する origin ヘッダを指定します
    - Controller の属性でも指定できますが、ここで設定するとシステムワイドに適用されます
    - クロージャを渡すとその都度状況に応じて配列を返すことができます
    - デフォルトは `[]` です
- priority: `array`
    - ルーティングの優先順位を指定します
    - デフォルトは `['rewrite', 'redirect', 'alias', 'regex', 'scope', 'default']` です
- router: `\ryunosuke\microute\Router`
    - Router インスタンスを指定します
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- dispatcher: `\ryunosuke\microute\Dispatcher`
    - Dispatcher インスタンスを指定します
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- resolver: `\ryunosuke\microute\Resolver`
    - URL ヘルパーインスタンスを指定します
    - デフォルトは `\ryunosuke\microute\Resolver` です
- controllerClass: string
    - Controller の基底クラス名を指定します
    - デフォルトは `\ryunosuke\microute\Controller::class` です
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- controllerAnnotation: bool
    - メタ情報の指定をアノテーションかアトリビュートか指定します
    - デフォルトは true です（アノテーションのみ有効）
    - この項目は互換性のために存在するため将来削除予定です。
- requestFactory: callable
    - リクエストオブジェクトのプロバイダーを指定します
    - ここで指定したクロージャは Request::setFactory に登録されます
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- requestClass: string
    - リクエストオブジェクトのクラス名を指定します
    - デフォルトは `\Symfony\Component\HttpFoundation\Request::class` です
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- request: `\Symfony\Component\HttpFoundation\Request`
    - リクエストオブジェクトを指定します
    - デフォルトは `\Symfony\Component\HttpFoundation\Request` です
    - よほど抜き差しならない状況じゃない限り指定する意味はありません
- requestTypes: `callable[]`
    - コンテントタイプに基づいてリクエストボディをどのようにパースするかを指定します
    - デフォルトは json の時に json_decode です
- sessionStorage: `\Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface`
    - セッションストレージを指定します
    - デフォルトは `\Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage` です
- defaultActionAsDirectory: `bool`
    - デフォルトアクションの url を `/` 付きにします
    - デフォルトは `false` です。このデフォルトは将来的に変更される可能性があります
- parameterDelimiter: `string`
    - パスとパラメータの区切り文字を指定します
    - これを `/` などにすると `/controller/action/123` のような restful な URL でアクセスできます
    - デフォルトは `?` です。 `/controller/action?123` のような URL でアクセスできます
- parameterSeparator: `string`
    - パラメータ間の区切り文字を指定します
    - これを `/` などにすると `/controller/action/123/456` のような pathful な URL でアクセスできます
    - デフォルトは `&` です。 `/controller/action?123&456` のような URL でアクセスできます
- parameterArrayable: `bool`
    - パラメータに配列を許可するかを指定します
    - デフォルトは `false` です。明示されない限り `/controller/action?id[]=123` のような配列パラメータが 404 になります
- parameterContexts: `array|callable`
    - 拡張子ごとの Content-Type を指定します
    - `['json' => 'application/json']` などとすると `/controller/action.json` アクセスされたときに Content-Type が `application/json` になります
    - クロージャの場合は 'json' のような引数が渡ってくるので返り値として Content-Type を返します
    - デフォルトは `[]` です
- authenticationProvider: `array|callable`
    - 認証情報プロバイダを指定します
    - 単純な `['user1' => 'pass1', 'user2' => 'pass2']` のような配列か、$username を受け取ってパスワードを返すクロージャを指定します
    - デフォルトは `[]` です
- authenticationComparator: `callable`
    - 認証情報の比較方法を指定します
    - 期待するパスワードと入力されたパスワードを受け取り、 bool を返す callable を指定します
    - デフォルトは `$valid_password === $password` です
- authenticationNoncer: `callable`
    - digest 認証において nonce のインクリメント機構を指定します
    - nonce を受け取り、 nc を返す callable を指定します
    - デフォルトは nc 検証なしです
- **controllerLocation**: array|string
    - 起動アプリケーションの「名前空間→ディレクトリ」の対応を指定します
    - 配列で `['\\vendor\\app\\controller' => '/app/controller'] ` のように指定します（要するに psr4 と同じ形式です）
        - この場合、オートローダは自動で登録されます
    - あるいは基底コントローラのクラス名を指定します
        - その場合、オートローダは自動登録されません。 composer の `autoload` を活用したい場合に使用します
    - デフォルトはありません。必須です

根本の動作に関わる重要なものや必須・準必須なものは太字にしてあります。

例えば `controllerLocation` は必須です。これがないとコントローラのロードができず、あらゆる処理が失敗します。
`debug` `cacher` は必須ではないですが、指定しないと動作速度に影響が出たり開発が不便になったりします。

すべての要素はクロージャを渡すと初回参照時のみ実行され、以後その結果を示すようになります。
つまり callable を設定したい場合は「callable を返す Closure」を指定します（Pimple などの DI コンテナと同じ）。

## Specification

### コントローラの階層構造

コントローラは下記の階層を持ちます。

- /DefaultController
    - トップレベル名前空間のデフォルトコントローラです
    - `/` アクセスされた場合、`defaultAction` がディスパッチされます
    - `/hoge` アクセスされた場合、`hogeAction` がディスパッチされます
    - トップレベル名前空間で未キャッチ例外が捕捉された場合、`errorAction` がディスパッチされます
    - 最低限 errorAction メソッドを持つ必要があります
- /HogeController
    - トップレベル名前空間のユーザ実装のコントローラです
    - `/hoge/foo` アクセスされた場合、`fooAction` がディスパッチされます
- /Namespace/DefaultController
    - `Namespace` 名前空間のデフォルトコントローラです
    - `/namespace/` アクセスされた場合、`defaultAction` がディスパッチされます
    - `/namespace/hoge` アクセスされた場合、`hogeAction` がディスパッチされます
    - `Namespace` 名前空間で未キャッチ例外が補足された場合、`errorAction` がディスパッチされます
- /Namespace/FugaController
    - `Namespace` 名前空間のユーザ実装のコントローラです
    - `/namespace/fuga/foo` アクセスされた場合、`fooAction` がディスパッチされます

その名前空間内に DefaultController が存在しない場合、ひとつ上の階層の DefaultController を探しに行きます。
上の例で言えば、 `/Namespace/DefaultController` が存在しない場合、未キャッチ例外の捕捉は `/DefaultController` が担います。
トップレベルに DefaultController が存在しない場合はエラーになります。

ただし、探しに行くのは `errorAction` だけです。 `defaultAction` は探しに行きません。
その代わり、例えば `/hoge/fuga` アクセスは `Hoge\\FugaController#defaultAction` に対応します。
「Contoller へのアクションなしアクセスは `defaultAction` と対応する」とも言えます。

上記の

- この名前空間において `/namespace/` アクセスされた場合、`defaultAction` がディスパッチされます
- この名前空間において `/namespace/hoge` アクセスされた場合、`hogeAction` がディスパッチされます
- Contoller への action なしアクセスは `defaultAction` と対応する

は矛盾しています。例えば「hoge/fuga/piyo」という URL は

- Hoge\FugaController#piyoAction (Hoge 名前空間の Fuga コントローラの piyo アクション)
- Hoge\Fuga\DefaultController#piyoAction (Hoge\Fuga 名前空間の Default コントローラの piyo アクション)
- Hoge\Fuga\PiyoController#defaultAction (Hoge\Fuga 名前空間の Piyo コントローラの default アクション)
- Hoge\Fuga\Piyo\DefaultController#defaultAction (Hoge\Fuga\Piyo 名前空間の Default コントローラの default アクション)

の4つに解釈し得ます。この場合は上から順に優先されます。
「なるべく default を使わないように優先される」と言ってもいいでしょう。
さらに「Controller の default なのか Action の default なのか」は Controller が優先です。

### コントローラのライフサイクル

リクエストは下記のライフサイクルを辿ります。

- route（not デフォルトルーティング）の走査
    - rewrite や redirect などで対応する URL かを調べます
    - マッチする場合、 rewrite ならパスの書き換え、 redirect なら直にレスポンスを返します
- dispatch
    - コントローラがあるか、メソッドはアクションメソッドであるかなどを調べて、実行する Controller/Action を決定します
- construct
    - コントローラのコンストラクタの直後にコールされます
    - init メソッドと明確な区別はありません。フィールドの初期化・代入など、コンストラクタで行うべきなことを記述します
    - ただし、「construct は呼ばれるが init は呼ばれない」は状況として有り得ます（ルーティングの失敗など）
    - なお、 __construct は final で継承禁止です
- init
    - コントローラの init メソッドがコールされます
    - init メソッドは Response 型の返却を許可します。Response 型を返却した場合、ライフサイクルはそこで終了して以下の処理は実行されません。
- before
    - コントローラの before メソッドがコールされます
    - 共通ビュー変数や権限チェックなどはここで記述します
- action
    - ディスパッチされたアクションメソッドがコールされます
    - そのアクション固有の処理を記述します
- after
    - コントローラの after メソッドがコールされます
    - この段階で Response は確定しているため、ヘッダ（Content-type 等）を弄りたい場合はここで記述します
- finish
    - コントローラの finish メソッドがコールされます
    - finish メソッドは Response 型の返却を許可しますが、 after と明確な区別はありません。このメソッド以降、Response が変更されることはないため、最終的なロギングやレスポンスチェックに使えます
- error
    - 上記の処理の流れの過程で throw された例外はこのメソッドで catch されます
    - 引数として throw された例外が渡ってきます
    - このメソッドが更に例外を送出するとさらに DefaultController#errorAction へ委譲されます

### サービスとしてのイベントハンドリング

上記のコントローラとしてのライフサイクルとは**別軸**でイベントハンドラが存在します。

- request: route 直後。引数は Request
- dispatch: dispatch 直前。引数は Controller
- error: 例外キャッチ直後。引数は Exception
- response: レスポンス送出直前。引数は Response

これらは必ず1回のみ呼ばれます。複数回は呼ばれません。
また、実行コンテキストは Service となり、`$this` で設定情報にアクセスできます。

コントローラのライフサイクルだと複数回呼ばれてしまったり、コントローラのコンテキストではなくアプリケーションで一律に処理したい処理がある場合はこちらのほうが便利です。

配列で指定するとすべてコールされますが、 `return false` した場合はそこで打ち切られます。
また、どのタイミングでも Response を返した場合はそれが最終レスポンスとなり、すべてのライフサイクルはスルーされます。

### コントローラ・アクションメソッドの属性

属性は下記の順序に従って読み込まれます

- 自身のメソッドの属性
- 自身のクラスの属性
- 親のメソッドの属性
- 親のクラスの属性

つまり「親 < 自身」「クラス < メソッド」という優先順位となり、より定義に近いものが優先されるということです。
究極的には大本の抽象コントローラに属性を記述すると下位コントローラの全メソッドでそれが適用されることになります。

これを止めるには `NoInheritance` 属性を使用します。
上記の継承ツリーの経路に `NoInheritance` 属性があるとそこで読み取りが打ち切られるようになります。
つまりメソッドに `NoInheritance` を付与すればクラスも親も見ない完全に固有の属性として扱われます。
`NoInheritance` 属性は読み取りを止めたい属性名を指定できます。省略時は全属性です。

属性の種類は下記です。

- `#[Method]`
    - リクエストメソッドを指定します
    - `#[Method('get', 'post')]` とすると GET と POST リクエストのみ受け付けます
    - 未指定時は全メソッドです
    - 値省略時は全メソッドです
- `#[Argument]`
    - アクションメソッドの引数として渡ってくるパラメータの種類・順番を指定します
    - `#[Argument('get', 'post')] とすると $_GET, $_COOKIE の順番で見ます
        - 指定できるのは `get` `post` `file` `cookie` `attribute` です
        - 何を指定しようと `#[Method]` で指定したメソッドのパラメータは必ず含まれます
    - 未指定時は `#[Method]` のみに従います
    - 値省略時は `#[Method]` のみに従います
- `#[Origin]`
    - 受け付ける Origin ヘッダを指定します
    - `#[Origin('http://example.com')]` とすると `http://example.com` 以外からのリクエストが 403 になります（GET 以外）。ただし、デバッグ時はアクセス可能です
    - ヘッダには `fnmatch` によるワイルドカードが使えます。複数指定するといずれかにマッチすれば許可されます
    - 未指定時は Origin ヘッダの検証を行いません
    - 値省略時は Origin ヘッダの検証を行いません
    - グローバルの origin 設定を上書きするものではありません
- `#[Ajaxable]`
    - Ajax リクエスト以外受け付けません
    - `#[Ajaxable(403)]` とすると普通にアクセスしても 403 になります。ただし、デバッグ時はアクセス可能です
    - 未指定時はリクエストの制限を行いません
    - 値省略時は 400 です
- `#[Queryable]`
    - URL 生成時やリダイレクト時に queryable な URL で生成するかを指定します
    - `#[Queryable(false)]` とすると `/controller/action?123` のように生成されます
        - 生成時の指定であって、false にしたからといってクエリストリングアクセスが無効になるわけでもその逆でもありません
    - 未指定時は通常のクエリストリングです（e.g. `/controller/action?id=123`）
    - 値省略時は true です
- `#[Context]`
    - 受け付けるコンテキスト（要するに拡張子）を指定します
    - `#[Context('json', 'xml')]` とすると `controller/action.json` や `controller/action.xml` でアクションメソッドがコールされるようになります
        - コンテキストは `$this->request->attribute->get('context')` で得られます
    - `#[Context('', 'json', 'xml')]` とすると `controller/action` を活かしたまま `controller/action.json` や `controller/action.xml` でもコールされるようになります
    - `#[Context('*')]` のように * を含めるとあらゆる拡張子アクセスを受け付けます
    - 未指定時は `.` 付きアクセスを受け付けません
    - 値省略時は `.` 付きアクセスを受け付けません
- `#[BasicAuth]`/`#[DigestAuth]`
    - basic/digest 認証が行われるようになります
    - この属性による認証は簡易的なもので例えば下記の問題があります。用途はあくまで「簡易的にちょっとそのページを守りたい」程度です
        - アクションごとにユーザを使い分けるようなことは出来ない
        - いわゆる認可機構がない
        - digest 認証において `nc` のチェックを行わないのでリプレイ攻撃に弱い（一応フックポイントは用意してある）
- `#[Cache]`
    - 指定秒数の間 http キャッシュが行われるようになります
    - `#[Cache(10)]` とすると 10 秒間の間 304 を返します
    - 未指定時はキャッシュを行いません
    - 値省略時は 60 です
- `#[WebCache]`
    - レスポンスが公開ディレクトリに書き出され、以後のリクエストは web サーバーに委譲されます（htaccess に依存）
    - `#[WebCache(10)]` とすると 10 秒間の間リクエスト自体が飛びません（60 * 60 * 24 のような単純な数式が使える）
    - アプリを通らなくなるため、キャッシュを解除する術はありません（書き出されたファイルを消すしかない）
    - 未指定時はキャッシュを行いません
    - 値省略時は 60 です
- `#[Event('hoge', 1, 2, 3)]`:hoge x, y, z
    - 追加イベントを指定します（後述）
    - アクション前後で hogeEvent(1, 2, 3) が呼ばれるようになります

下記はルーティング用属性です。

- `#[DefaultRoute(true)]`
    - デフォルトルーティングの有効/無効を設定します（後述）
    - `[DefaultRoute(false)]` とするとデフォルトルーティングが無効になります
- `#[Route]`
    - `#[Route('hoge')]` とすると 'hoge' でリバースルーティングしたときにこのアクションの URL を返すようになります
- `#[Rewrite('/url')]`
    - /url アクセス時に rewrite されてこのアクションへ到達します
    - 実態は preg_replace によるリクエストパスの書き換えです。あらゆる処理に先立って行われます
- `#[Redirect('/url', 302)]`
    - /url アクセス時に redirect されてこのアクションへ到達します
    - 第2引数でリダイレクトのステータスコードが指定できます
- `#[Regex('#pattern#')]`
    - pattern にマッチする時に regex されてこのアクションへ到達します
    - `/` から始まると絶対パスでマッチします
    - `/` 以外から始まると「本来そのコントローラが持つ URL（CamelCase -> chain-case のデフォルトルーティング）」からの相対パスでマッチします
- `#[Alias('/url')]`
    - /url アクセス時に alias されてこの**コントローラへ**到達します
- `#[Scope('/pattern')]`
    - /pattern アクセス時にキャプチャーされつつこの**コントローラへ**到達します

大抵の属性は複数記述できます。

```php
    #[Redirect('/url1')] // /url1 アクセスで redirect されてこのアクションに到達します
    #[Redirect('/url2')] // /url2 アクセスで redirect されてこのアクションに到達します
    public function hogeAction() {}
```

上記は `/url1` `/url2` の両方が有効です。

Alias は「URL → Controller」のルーティングなのでメソッドではなくクラス属性として記述します。

```php
#[Alias('/fuga')]
class HogeController extends \ryunosuke\microute\Web\Controller
{
    public function fooAction() {}
}
```

このようなクラス定義をすると `/fuga/foo` という URL はこのコントローラの fooAction へ行き着きます。
つまり php レイヤでクラス名を変更するのと同じ効果があります。

同様に Scope は「URL → Controller」のルーティングなのでメソッドではなくクラス属性として記述します。

```php
#[Scope('(?<pref_id>\d+)/')]
class HogeController extends \ryunosuke\microute\Web\Controller
{
    public function fooAction($pref_id) {}
}
```

このようなクラス定義をすると `/hoge/13/foo` という URL はこのコントローラの fooAction へ行き着きます。
pref_id がキャプチャされる点と相対パスが使える点が alias と異なります。

foo だけではなく、他にアクションが生えていれば到達します。
つまり、「全アクションで `#[Regex]` して共通パラメータを定義」したと同様の振る舞いをしますし、そのような使い方を想定しています。

### アクションメソッドに渡ってくるパラメータ

クエリストリング や `parameterDelimiter` `parameterSeparator` `parameterArrayable` などに応じてアクションメソッドは引数を取ることができます。
データソースは `#[Argument]` `#[Method]` などの属性に応じて変わります。

特殊なことをしなければ `?id=123` というクエリストリングでアクションメソッドの引数 `$id` が設定されます。
その際、下記の特殊な処理が走ります。

- アクションメソッドが引数を持っていてかつマッチするパラメータが無い場合はルーティングに失敗し、 404 になります
    - ただし、引数がデフォルト値を持つ場合は 404 にはならず、デフォルト値でコールされます
- `#[Regex]` でルーティングされた場合はマッチ結果が渡ってきます
    - 名前付きキャプチャの名前が一致するものが優先で、名前が見つからない場合はマッチ順でマップします
- `#[Scope]` でルーティングされた場合はマッチ結果が渡ってきます
    - 名前付きキャプチャの名前が一致するものが優先で、名前が見つからない場合はマッチ順でマップします

php7 の型宣言があれば大抵の場合で不要ですが、キャストが便利な状況もあります（例えば int に not intable な文字列が来ると即死するなど）。

```php
    public function hogeAction($id, $seq)
    {
        // /hoge?id=foo でアクセスしても 404 になる（seq がマップできない）
    }

    public function fugaAction($id, $seq = 123)
    {
        // /fuga?id=foo でアクセスすると $id=foo, $seq=123 となる（seq はデフォルト値が使われる）
    }

    #[Regex('/detail-(?<id>[a-z]+)/(\d+)')]
    public function testAction($id, $seq)
    {
        // /detail-foo/123 でアクセスすると $id=foo, $seq=123 となる（id は名前が一致、seq は名前がないが順番が一致）
    }
```

### アクションメソッドの戻り値による挙動

- string 型
    - 戻り値をそのままレスポンスボディとし、ステータスコードとして 200 を返します
    - あまり用途はないでしょう
- Response 型
    - その Response をそのままレスポンスとします
    - リダイレクト、json 返却、ダウンロードヘッダなど、よく使うものは親メソッドに定義されているのでそれらを使う際に頻出します
- 上記以外
    - Controller の render メソッドがコールされます
    - 大抵の場合はここでテンプレートエンジンによる html レスポンスを返すことになるでしょう
    - render は引数として action メソッドの返り値が渡ってくるのでそれを使用して json レスポンスを返すことなどもできます

### `#[Event('hoge')]` によるイベントディスパッチ

`#[Event('hoge', 1, 2, 3)]` という属性を記述するとアクションメソッドの前後で `hogeEvent` がコールされるようになります。
具体的には下記のコードで

```php
    #[Event('hoge', 10, 15)]
    public function testAction()
    {
        echo 'アクション本体';
    }
    
    public function hogeEvent($phase, $x, $y)
    {
        if ($phase === 'pre') {
            echo 'アクション前';
        }
        if ($phase === 'post') {
            echo 'アクション後';
        }
    }
```

「アクション前アクション本体アクション後」と出力されます。

イベントの仕様は下記です。

- 複数イベントが指定できます
    - 上記のサンプルで言えば `#[Event('hoge')]` `#[Event('fuga')]` と記述すればその記述順でディスパッチされます
- 第1引数は $phase['pre', 'post'] で、それ以降は属性の引数です
    - 上記のサンプルで言えば `$x = 10, $y = 15` です
    - この 'pre', 'post' は将来拡張される可能性もあります
- イベントは Response 型の返却を許します
    - Response 型を返した場合、以降のイベントや（preの場合）実際のアクション処理は実行されません
    - ただし、after/finish はコールされます。イベント処理はあくまでアクションに紐づくイベントだからです
- イベント中の例外送出は通常通り error でハンドリングされます

### その他

#### ルーティング

あらゆるルーティングは、基本である「Controller の名前空間をそのまま URL へマッピング（`Hoge\Fuga\PiyoController::actionAction` → `hoge/fuga/piyo/action`）」という前提を崩しません。
リダイレクトを設定しようと正規表現ルーティングを設定しようと上記の URL も生きていてアクセス可能です。
これを無効にするには `#[DefaultRoute]` 属性を使用する必要があります。

#### デフォルトルート名

すべてのアクションメソッドはデフォルトで `ControllerName::ActionName` という（仮想的な）ルート名を持ちます。
resolver で URL を生成する際に、`$resolver->action($controller, $action)` で生成するのではなく、`$resolver->route("$controller::$action")` で生成しておけば、そのアクションを変更したくなった時、ルート名の変更をせずに済みます（ルート名は明示的に登録されたルート名が優先されるため）。

とは言え存在しない "$controller::$action" を指定するのは気持ち悪いのであらかじめ `#[Route]` で指定しておくのがベストです。

#### parameterDelimiter, parameterSeparator の補足

これらのデフォルトは順に `?` `&` になっています。
これは `/controller/action?123&hoge` でアクションメソッドが `(123, "hoge")` で起動することを意味します。
ちょうどクエリストリングから key を差っ引いて、アクションメソッドの順番に当てはめるような形です。

クエリパラメータのように見えてクエリパラメータじゃないのは少し気持ち悪いので、これを順に `/` `&` にすると `/controller/action/123&hoge` でアクションメソッドが `(123, "hoge")` で起動します。
コンテキストは常に活きるので `/` `-` などにすると `/controller/action/123-hoge.json` で `(123, "hoge")` で起動します。
これは REST っぽい URL 設計に役立ちます。

ただし、注意点として `/` `/` にするとうまく起動しません。これは「どこまでが controller/action なのか、どこからがパラメータなのか」を機械的に判断することが不可能だからです。
厳密に言えば起動はしますが、それ以外の URL での動作は未定義です。
現在のところ、有効な controller/action にマッチするまで探しに行くので、最終的に DefaultController まで行き着いてしまいます。

何が言いたいのかと言うと、 `/` `/` の組み合わせは推奨しません。
きちんと action を設けてパラメータ区切りは `/` `-` などにすることをおすすめします。

#### urls メソッド

router に urls というメソッドが生えています。これは現存するすべての URL とそのメタ情報を返します。
ルーティングの確認や sitemap・URL リストなどを作るときに便利です。

#### アノテーションとアトリビュート

version 1.1.15 まではメタデータはアノテーション指定でした。
version 1.1.16 からはアトリビュートにも対応しています。
どちらを使うかは `controllerAnnotation` オプションで指定できます。

互換性のため、アノテーションも使えますしデフォルトではアノテーションのみを見に行きます。
ただ、アノテーションは前時代的であり、php8.0 からはアトリビュートの言語サポートもあるため、アトリビュートの使用を推奨します。

なお、polyfill が含まれており、php7.4 でもアトリビュートは使用できますが、規模によっては初回アクセスのパフォーマンスが劣悪になる可能性があります。

## License

MIT
