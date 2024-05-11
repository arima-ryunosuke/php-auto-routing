<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class CookieSessionHandler extends AbstractSessionHandler
{
    private const VERSION = 2;

    private string $privateKey;

    private string $storeName;
    private string $initialStoreName;

    private int $chunkSize;
    private int $initialChunkSize;

    private int $maxLength;

    private int $lifetime;

    private array $cookieInput;
    private array $cookieOutput;

    private array $metadata;

    private \Closure $setcookie;

    public function __construct(array $options = [])
    {
        assert(array_key_exists('privateKey', $options));

        $this->privateKey = (string) $options['privateKey'];
        $this->initialStoreName = (string) ($options['storeName'] ?? '');
        $this->initialChunkSize = (int) ($options['chunkSize'] ?? 4095); // ブラウザの最小サイズは 4095 byte
        $this->maxLength = (int) ($options['maxLength'] ?? 19);          // RFC 的には 20 個。ただし個数クッキーで1つ使うので -1
        $this->lifetime = (int) ($options['lifetime'] ?? 0);             // セッションの lifetime とは別（セッションクッキー＋有効期限）

        $this->cookieInput = $options['cookie'] ?? $_COOKIE;
        $this->setcookie = \Closure::fromCallable($options['setcookie'] ?? '\setcookie');
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName): bool
    {
        $this->storeName = strlen($this->initialStoreName) ? $this->initialStoreName : $sessionName;
        // クッキーサイズ制限にはクッキー名、さらに path, domain まで含まれる（メタ部分は http_build_query で概算）
        $this->chunkSize = $this->initialChunkSize - (strlen($this->storeName) + strlen(http_build_query(session_get_cookie_params())));

        $this->cookieOutput = [];

        return parent::open($savePath, $this->storeName);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead(string $sessionId): string
    {
        $this->metadata = (array) json_decode($this->cookieInput[$this->storeName] ?? '{}', true);
        $this->metadata['version'] = (int) ($this->metadata['version'] ?? self::VERSION);
        $this->metadata['length'] = (int) min($this->metadata['length'] ?? 0, $this->maxLength);
        $this->metadata['ctime'] = (int) ($this->metadata['ctime'] ?? time());
        $this->metadata['atime'] = (int) ($this->metadata['atime'] ?? time());
        $this->metadata['mtime'] = (int) ($this->metadata['mtime'] ?? time());

        if ($this->metadata['length'] <= 0) {
            return '';
        }

        if ($this->lifetime !== 0) {
            if ($this->lifetime <= (time() - $this->metadata['atime'])) {
                return '';
            }
            $this->cookieOutput[$this->storeName] = json_encode(array_replace($this->metadata, ['atime' => time()]));
        }

        $data = '';
        for ($i = 0; $i < $this->metadata['length']; $i++) {
            $data .= $this->cookieInput[$this->storeName . $i] ?? '';
        }
        return @$this->decode($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(string $sessionId, string $data): bool
    {
        $chunks = array_values(array_filter(str_split($this->encode($data), $this->chunkSize), fn($v) => strlen($v)));
        $length = count($chunks);
        if ($length > $this->maxLength) {
            return false;
        }

        $this->cookieOutput[$this->storeName] = json_encode(array_replace($this->metadata, [
            'version' => self::VERSION,
            'length'  => $length,
            'atime'   => time(),
            'mtime'   => time(),
        ]));

        foreach (array_pad($chunks, $this->metadata['length'], '') as $i => $v) {
            $this->cookieOutput[$this->storeName . $i] = $v;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy(string $sessionId): bool
    {
        $this->cookieOutput = array_fill_keys(array_keys($this->cookieOutput), '');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
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

        $headers = preg_grep("#^Set-Cookie:\s*{$this->storeName}\d*=#ui", headers_list(), PREG_GREP_INVERT);
        @header_remove();
        foreach ($headers as $header) {
            header($header, false); // @codeCoverageIgnore
        }

        $this->cookieInput = $this->cookieOutput;
        foreach ($this->cookieOutput as $name => $value) {
            ($this->setcookie)($name, $value, $session_params);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxlifetime): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp(string $sessionId, string $data): bool
    {
        return true;
    }

    private function encode(string $decrypted_data): string
    {
        $algo = 'aes-256-gcm';
        $taglen = 16;

        $keylen = 256 / 8;// openssl_cipher_key_length($algo);
        $key = hash_hkdf('sha256', $this->privateKey, $keylen);

        $ivlen = openssl_cipher_iv_length($algo);
        $iv = random_bytes($ivlen);

        $ciphertext = openssl_encrypt(gzdeflate($decrypted_data, 9), $algo, $key, OPENSSL_RAW_DATA, $iv, $tag, '', $taglen);

        return strtr(base64_encode($tag . $iv . $ciphertext), [
            '/' => '_',
            '+' => '-',
        ]);
    }

    private function decode(string $encrypted_data): string
    {
        $data = base64_decode(strtr($encrypted_data, [
            '_' => '/',
            '-' => '+',
        ]));

        $algo = 'aes-256-gcm';
        $taglen = 16;

        $tag = substr($data, 0, $taglen);

        $keylen = 256 / 8;// openssl_cipher_key_length($algo);
        $key = hash_hkdf('sha256', $this->privateKey, $keylen);

        $ivlen = openssl_cipher_iv_length($algo);
        $iv = substr($data, $taglen, $ivlen);

        $decrypted_data = openssl_decrypt(substr($data, $taglen + $ivlen), $algo, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return (string) gzinflate($decrypted_data);
    }
}
