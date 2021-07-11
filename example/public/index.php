<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$service = new \ryunosuke\microute\Service([
    'debug'              => ($_SERVER['HTTP_CACHE_CONTROL'] ?? '') === 'no-cache',
    'cacher'             => new \ryunosuke\microute\Cacher(sys_get_temp_dir() . '/microute/example'),
    'logger'             => function () {
        return function (\Throwable $exception, \Symfony\Component\HttpFoundation\Request $request = null) {
            printf('これは "logger" でハンドリングされた例外メッセージです（%s）：%s<br>', $request === null ? 'NULL' : $request->getRequestUri(), $exception->getMessage());
        };
    },
    'priority'           => ['rewrite', 'redirect', 'alias', 'default', 'scope', 'regex'],
    'sessionStorage'     => function () {
        return new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
            'cookie_lifetime' => 60,
        ], new \ryunosuke\microute\http\CookieSessionHandler([
            'privateKey' => 'secretkey',
            'chunkSize'  => 256,
        ]));
    },
    'parameterDelimiter' => '/',
    'parameterSeparator' => '&',
    'controllerLocation' => [
        'ryunosuke\\microute\\example\\controller\\' => __DIR__ . '/../app/controller/'
    ],
]);

// /external アクセスで外部サイトにリダイレクトするようにします
$service->router->redirect('/external', 'https://example.com/');

return $service->run();
