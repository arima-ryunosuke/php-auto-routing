<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$service = new \ryunosuke\microute\Service([
    'debug'              => ($_SERVER['HTTP_CACHE_CONTROL'] ?? '') === 'no-cache',
    'cacher'             => new \ryunosuke\microute\Cacher(sys_get_temp_dir() . '/microute/example'),
    'logger'             => function () {
        return function (\Exception $exception, \Symfony\Component\HttpFoundation\Request $request) {
            printf('これは "logger" でハンドリングされた例外メッセージです（%s）：%s<br>', $request->getRequestUri(), $exception->getMessage());
        };
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
