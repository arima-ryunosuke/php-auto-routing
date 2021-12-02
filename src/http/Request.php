<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @property-read SessionInterface $session
 */
class Request extends \Symfony\Component\HttpFoundation\Request
{
    /** @var InputBag alias for $this->>query */
    public $get;

    /** @var InputBag alias for $this->>request */
    public $post;

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->get = $this->query;
        $this->post = $this->request;
    }

    public function __get($name)
    {
        if ($name === 'session') {
            return $this->getSession();
        }
        throw new \InvalidArgumentException("$name is not supported property");
    }

    /**
     * GET, POST, COOKIE の優先順位でリクエスト値を返す
     *
     * いわゆる $_REQUEST 変数に値するが、request_order などの影響は受けない。
     *
     * @param string $key キー名
     * @param string|int|float|bool|null $default デフォルト値
     * @return string|int|float|bool|null リクエストの値
     */
    public function input(string $key, $default = null)
    {
        foreach ([$this->query, $this->request, $this->cookies] as $bag) {
            if ($bag->has($key)) {
                return $bag->get($key);
            }
        }

        return $default;
    }

    public function getUserAgent()
    {
        return $this->headers->get('USER-AGENT');
    }

    public function getReferer()
    {
        return $this->headers->get('REFERER');
    }
}
