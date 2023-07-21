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

    /** @var InputBag alias by Request Method */
    public $input;

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->get = $this->query;
        $this->post = $this->request;
        $this->input = $this->isMethod('GET') ? $this->query : $this->request;
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

    /**
     * 現在のリクエストメソッドパラメータから最初に見つかったものを返す
     *
     * Symfony の型の制約を受けない（配列型でもそのまま返す）。
     * 無かった場合は null 固定。
     *
     * @param string ...$keys キー名
     * @return string|int|float|bool|null リクエストの値
     */
    public function any(string ...$keys)
    {
        $all = $this->input->all();
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                return $all[$key];
            }
        }
        return null;
    }

    /**
     * 現在のリクエストメソッドパラメータから指定したもののみを返す
     *
     * Symfony の型の制約を受けない（配列型でもそのまま返す）。
     * keys の指定順は維持される。
     *
     * @param string ...$keys キー名
     * @return array リクエストの値
     */
    public function only(string ...$keys)
    {
        //return array_intersect_key($this->input->all(), array_keys($keys));
        $all = $this->input->all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    /**
     * 現在のリクエストメソッドパラメータから指定したものを除外してを返す
     *
     * Symfony の型の制約を受けない（配列型でもそのまま返す）。
     *
     * @param string ...$keys キー名
     * @return array リクエストの値
     */
    public function except(string ...$keys)
    {
        return array_diff_key($this->input->all(), array_flip($keys));
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
