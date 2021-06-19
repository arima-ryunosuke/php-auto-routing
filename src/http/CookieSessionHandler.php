<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class CookieSessionHandler extends AbstractSessionHandler
{
    /** @var string */
    private $privateKey;

    /** @var string */
    private $storeName;

    /** @var int */
    private $chunkSize;

    /** @var int */
    private $maxLength;

    /** @var ParameterBag */
    private $cookieBag;

    /** @var callable */
    private $setcookie;

    public function __construct($options = [])
    {
        assert(array_key_exists('privateKey', $options));

        $this->privateKey = $options['privateKey'];
        $this->chunkSize = $options['chunkSize'] ?? 4095; // ブラウザの最小サイズは 4095 byte
        $this->maxLength = $options['maxLength'] ?? 19; // RFC 的には 20 個。ただし個数クッキーで1つ使うので -1

        $this->cookieBag = new ParameterBag($options['cookie'] ?? $_COOKIE);
        $this->setcookie = $options['setcookie'] ?? '\setcookie';
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        $this->storeName = $sessionName;
        // クッキーサイズ制限にはクッキー名、さらに path, domain まで含まれる（メタ部分は http_build_query で概算）
        $this->chunkSize -= strlen($sessionName) + strlen(http_build_query(session_get_cookie_params()));

        return parent::open($savePath, $sessionName);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead($sessionId): string
    {
        $data = '';
        $count = (int) $this->cookieBag->get($this->storeName, 0);
        if ($count === 0) {
            return '';
        }
        for ($i = 0; $i < $count; $i++) {
            $value = $this->cookieBag->get($this->storeName . $i);
            if ($value === null) {
                break;
            }
            $data .= $value;
        }
        return @$this->decode($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($sessionId, $data): bool
    {
        return $this->setCookies($this->encode($data));
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy($sessionId): bool
    {
        return $this->setCookies('');
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        return true;
    }

    private function encode($decrypted_data)
    {
        // Set a random salt
        $salt = random_bytes(16);

        $salted = '';
        $dx = '';
        // Salt the key(32) and iv(16) = 48
        while (strlen($salted) < 48) {
            $dx = hash('sha256', $dx . $this->privateKey . $salt, true);
            $salted .= $dx;
        }

        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);

        $encrypted_data = openssl_encrypt($decrypted_data, 'AES-256-CBC', $key, 0, $iv);
        return strtr(base64_encode(gzcompress($salt . $encrypted_data, 9)), [
            '/' => '_',
            '+' => '-',
        ]);
    }

    private function decode($encrypted_data)
    {
        $data = gzuncompress(base64_decode(strtr($encrypted_data, [
            '_' => '/',
            '-' => '+',
        ])));

        $salt = substr($data, 0, 16);
        $ct = substr($data, 16);

        $rounds = 3; // depends on key length
        $data00 = $this->privateKey . $salt;
        $hash = [];
        $hash[0] = hash('sha256', $data00, true);
        $result = $hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $hash[$i] = hash('sha256', $hash[$i - 1] . $data00, true);
            $result .= $hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv = substr($result, 32, 16);

        return openssl_decrypt($ct, 'AES-256-CBC', $key, 0, $iv) ?: '';
    }

    private function setCookies($data)
    {
        $cookie_params = session_get_cookie_params();
        $session_params = [
            'expires'  => $cookie_params['lifetime'] ? time() + $cookie_params['lifetime'] : 0,
            'path'     => $cookie_params['path'],
            'domain'   => $cookie_params['domain'],
            'secure'   => $cookie_params['secure'],
            'httponly' => $cookie_params['httponly'],
            'samesite' => $cookie_params['samesite'],
        ];

        $count = (int) $this->cookieBag->get($this->storeName, 0);
        $chunks = array_filter(str_split($data, $this->chunkSize), function ($v) { return strlen($v); });
        $length = count($chunks);
        if ($length > $this->maxLength) {
            return false;
        }
        $chunks[''] = $length;

        foreach ($chunks as $i => $v) {
            ($this->setcookie)($this->storeName . $i, $v, $session_params);
        }

        // 無駄なので余剰を削除する
        for ($i = $length; $i <= $count; $i++) {
            ($this->setcookie)($this->storeName . $i, '', $session_params);
        }

        return true;
    }
}
