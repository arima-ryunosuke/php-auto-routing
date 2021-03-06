# RELEASE

バージョニングはセマンティックバージョニングでは**ありません**。

| バージョン   | 説明
|:--           |:--
| メジャー     | 大規模な仕様変更の際にアップします（クラス構造・メソッド体系などの根本的な変更）。<br>メジャーバージョンアップ対応は多大なコストを伴います。
| マイナー     | 小規模な仕様変更の際にアップします（中機能追加・メソッドの追加など）。<br>マイナーバージョンアップ対応は1日程度の修正で終わるようにします。
| パッチ       | バグフィックス・小機能追加の際にアップします（基本的には互換性を維持するバグフィックス）。<br>パッチバージョンアップは特殊なことをしてない限り何も行う必要はありません。

なお、下記の一覧のプレフィックスは下記のような意味合いです。

- change: 仕様変更
- feature: 新機能
- fixbug: バグ修正
- refactor: 内部動作の変更
- `*` 付きは互換性破壊

## x.y.z

- psr15(middleware) に対応する？
- DefaultController::errorAction の廃止

## 1.1.7

- 対象バージョンを php7.4, symfony5.* に格上げ
- [change] CookieSessionHandler の実装を 7.3 以降に追従

## 1.1.6

- [change][Service] request の発行処理を requestFactory に委譲
- [refactor][Router] debug 時は reverseRoute がキャッシュされないように変更
- [fixbug][Dispatcher] 引数名の一致よりも連番の方が優先されていた不具合を修正

## 1.1.5

- [change][Resolver] path メソッドでクエリパラメータが活きるように修正

## 1.1.4

- [feature][Service] イベント機構の実装

## 1.1.3

- [feature][Resolver] クエリパラメータの生成を行う query メソッドを追加
- [feature][Router] ルーティングの優先順位を指定可能にした
- [feature][Dispatcher] origin ヘッダの検証をコントローラレベルではなくグローバルレベルに拡張
- [feature][Service] requestClass 名を注入できるように修正

## 1.1.2

- [feature][Service] requestTypes オプションを実装
- [fixbug][Router] reverseRoute で正規表現が残ってしまう不具合を修正
- [fixbug][Router] 仮想ルートに Action が紛れていた不具合を修正
- [feature][http] CookieSessionHandler を実装

## 1.1.1

- [feature][Dispatcher] origin ヘッダによる拒否機能
- [fixbug][Dispatcher] リクエスト制限が error アクションにまで及んでいた不具合を修正

## 1.1.0

- [*change][Service] 不要なエントリを削除
- [fixbug][Controller] http-foundation 4.3.4 にしたらテストがコケたので修正
- [refactor][Cacher] キャッシュファイルをシンプルに1ファイルに変更

## 1.0.1

- [fixbug][Router] キャッシュがない状態だと notice が出ていた不具合を修正
- [refactor][Service] debug 時のキャッシュ無効は clear で十分

## 1.0.0

- 公開
