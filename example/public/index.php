<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$service = new \ryunosuke\microute\Service([
    'debug'                => ($_SERVER['HTTP_CACHE_CONTROL'] ?? '') === 'no-cache',
    'cacher'               => new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\PhpFilesAdapter()),
    'logger'               => function () {
        return function (\Throwable $exception, \Symfony\Component\HttpFoundation\Request $request = null) {
            printf('これは "logger" でハンドリングされた例外メッセージです（%s）：%s<br>', $request === null ? 'NULL' : $request->getRequestUri(), $exception->getMessage());
        };
    },
    'priority'             => ['rewrite', 'redirect', 'alias', 'default', 'scope', 'regex'],
    'trustedProxies'       => [
        'mynetwork',        // 自セグメントを登録します
        'private',          // プライベートネットワークを登録します
        '100.100.101.0/24', // CIDR を登録します
        // URL は3つのオプションを指定できます
        'cloudfront' => [
            'url'    => 'http://d7uri8nf7uskq.cloudfront.net/tools/list-cloudfront-ips', // この URL に取りに行きます
            'ttl'    => 60 * 60 * 24 * 365,                                              // 再取得の有効期限です
            'filter' => fn($contents) => array_merge(...array_values($contents)),        // フィルターコールバックです
        ],
    ],
    'sessionStorage'       => function () {
        return new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
            'cache_limiter' => 'nocache',
        ], new \ryunosuke\microute\http\CookieSessionHandler([
            'privateKey' => 'secretkey',
            'storeName'  => 'SID',
            'chunkSize'  => 256,
            'lifetime'   => 60,
        ]), new \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag('_sf2_meta', PHP_INT_MAX));
    },
    'parameterDelimiter'   => '/',
    'parameterSeparator'   => '&',
    'controllerLocation'   => [
        'ryunosuke\\microute\\example\\controller\\' => __DIR__ . '/../app/controller/',
    ],
    'controllerAnnotation' => false,
]);

// /external アクセスで外部サイトにリダイレクトするようにします
$service->router->redirect('/external', 'https://example.com/');

return $service->run();
