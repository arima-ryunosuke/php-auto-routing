<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class CookieSessionStorage extends NativeSessionStorage
{
    public function __construct(array $options = [])
    {
        // cookie 故のいくつかの決め打ちオプションがある
        $options += [
            // デフォの session cookie は不要
            'use_cookies'    => 0,
            // GC されることはないので不要
            'gc_probability' => 0,
            'gc_divisor'     => 0,
            'gc_maxlifetime' => 0,
        ];

        // cookie 前提なので固定
        $handler = new CookieSessionHandler($options['handler']);

        parent::__construct($options, $handler);
    }

    protected function loadSession(array &$session = null)
    {
        parent::loadSession($session);

        $metadataKey = $this->metadataBag->getStorageKey();
        unset($session[$metadataKey]);
        unset($_SESSION[$metadataKey]);
    }
}
