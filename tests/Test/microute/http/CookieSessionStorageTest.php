<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\CookieSessionStorage;

class CookieSessionStorageTest extends \ryunosuke\Test\AbstractTestCase
{
    function provideStorage(&$cookies, $options = [])
    {
        $options += [
            'handler' => [],
        ];
        $options['handler'] += [
            'privateKey' => 'test',
            'cookie'     => $cookies,
            'chunkSize'  => 256,
            'maxLength'  => 10,
            'setcookie'  => function ($name, $value, $options) use (&$cookies) {
                if ($value === '') {
                    unset($cookies[$name]);
                }
                else {
                    $cookies[$name] = $value;
                }
            },
        ];
        $storage = new CookieSessionStorage($options);
        return $storage;
    }

    function test_clear()
    {
        $storage = $this->provideStorage($cookies);

        $_SESSION = ['dummy' => 1];
        $this->assertNotEmpty($_SESSION);
        $storage->clear();
        $this->assertEmpty($_SESSION);
    }
}
