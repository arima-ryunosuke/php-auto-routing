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

    /** @var array */
    private $metadata;

    /** @var \Closure */
    private $setcookie;

    public function __construct($options = [])
    {
        assert(array_key_exists('privateKey', $options));

        $this->privateKey = (string) $options['privateKey'];
        $this->storeName = (string) ($options['storeName'] ?? '');
        $this->chunkSize = (int) ($options['chunkSize'] ?? 4095); // ブラウザの最小サイズは 4095 byte
        $this->maxLength = (int) ($options['maxLength'] ?? 19);   // RFC 的には 20 個。ただし個数クッキーで1つ使うので -1

        $this->cookieBag = new ParameterBag($options['cookie'] ?? $_COOKIE);
        $this->setcookie = \Closure::fromCallable($options['setcookie'] ?? '\setcookie');
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        $this->storeName = strlen($this->storeName) ? $this->storeName : $sessionName;
        // クッキーサイズ制限にはクッキー名、さらに path, domain まで含まれる（メタ部分は http_build_query で概算）
        $this->chunkSize -= strlen($this->storeName) + strlen(http_build_query(session_get_cookie_params()));

        // for compatible
        $metadata = $this->cookieBag->get($this->storeName, '{}');
        if (ctype_digit((string) $metadata)) {
            $this->metadata = [
                'length'  => $metadata,
                'version' => 0,
            ];
        }
        else {
            $this->metadata = json_decode($metadata, true);
        }

        $this->metadata['length'] = (int) min($this->metadata['length'] ?? 0, $this->maxLength);
        $this->metadata['version'] = (int) ($this->metadata['version'] ?? 1);

        return parent::open($savePath, $this->storeName);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead($sessionId): string
    {
        return @$this->getCookies();
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

        $encrypted_data = openssl_encrypt(gzdeflate($decrypted_data, 9), 'AES-256-CBC', $key, 0, $iv);
        return strtr(base64_encode($salt . $encrypted_data), [
            '/' => '_',
            '+' => '-',
        ]);
    }

    private function decode($encrypted_data)
    {
        $data = base64_decode(strtr($encrypted_data, [
            '_' => '/',
            '-' => '+',
        ]));

        // for compatible
        if ($this->metadata['version'] === 0) {
            $data = gzuncompress($data);
        }

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

        $decrypted_data = openssl_decrypt($ct, 'AES-256-CBC', $key, 0, $iv) ?: '';
        if ($this->metadata['version'] === 0) {
            return $decrypted_data;
        }
        return (string) gzinflate($decrypted_data);
    }

    private function getCookies()
    {
        if ($this->metadata['length'] <= 0) {
            return '';
        }

        $data = '';
        for ($i = 0; $i < $this->metadata['length']; $i++) {
            $value = $this->cookieBag->get($this->storeName . $i, '');
            $data .= $value;
        }
        return @$this->decode($data);
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

        $chunks = array_filter(str_split($data, $this->chunkSize), fn($v) => strlen($v));
        $length = count($chunks);
        if ($length > $this->maxLength) {
            return false;
        }
        $chunks[''] = json_encode([
            'length'  => $length,
            'version' => 1,
        ]);

        foreach ($chunks as $i => $v) {
            ($this->setcookie)($this->storeName . $i, $v, $session_params);
        }

        // 無駄なので余剰を削除する
        for ($i = $length; $i <= $this->metadata['length']; $i++) {
            ($this->setcookie)($this->storeName . $i, '', $session_params);
        }

        return true;
    }
}
