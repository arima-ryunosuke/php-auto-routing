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
    public $post, $body;

    /** @var InputBag alias by Request Method */
    public $input;

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->get = $this->query;
        $this->post = $this->request;
        $this->body = $this->request;
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

    public function getClientHints($raw = false, string $alternativeCookie = 'client_hints')
    {
        // @see https://developer.mozilla.org/ja/docs/Web/HTTP/Headers/Accept-CH
        $hints = [
            'Content-DPR'                => 'decimal',
            'DPR'                        => 'decimal',
            'Device-Memory'              => 'decimal',
            'Viewport-Width'             => 'integer',
            'Width'                      => 'integer',
            'Sec-CH-UA'                  => 'string@string[]',
            'Sec-CH-UA-Arch'             => 'string',
            'Sec-CH-UA-Full-Version'     => 'string',
            'Sec-CH-UA-Mobile'           => 'boolean',
            'Sec-CH-UA-Model'            => 'string',
            'Sec-CH-UA-Platform'         => 'string',
            'Sec-CH-UA-Platform-Version' => 'string',
        ];

        if (strlen($alternativeCookie)) {
            $cookieHints = json_decode($this->cookies->get($alternativeCookie, '{}'), true);
        }

        $result = [];
        foreach ($hints as $hint => $type) {
            $value = $this->headers->get($hint) ?? $cookieHints[$hint] ?? null;

            if ($raw) {
                $result[$hint] = $value;
                continue;
            }

            if (isset($value)) {
                if (str_ends_with($type, '[]')) {
                    [$vtype, $ptype] = explode('@', substr($type, 0, -2)) + [1 => null];
                    foreach ($this->_parseStructuredFieldValue('list', $value) as $item) {
                        $key = $this->_parseStructuredFieldValue($vtype, $item['value']);
                        $params = array_map(fn($param) => $this->_parseStructuredFieldValue($ptype, $param), $item['params']);
                        $result[$hint][$key] = $params;
                    }
                }
                else {
                    $result[$hint] = $this->_parseStructuredFieldValue($type, $value);
                }
            }
        }
        return $result;
    }

    private function _parseStructuredFieldValue(string $type, string $sfv)
    {
        // @todo 真面目にはやってられないので CH に必要なもののみ（まぁ自前実装より専用のライブラリを使った方がいい）

        if ($type === 'boolean') {
            return boolval(substr($sfv, 1));
        }
        if ($type === 'integer') {
            return intval($sfv);
        }
        if ($type === 'decimal') {
            return floatval($sfv);
        }
        if ($type === 'string') {
            return trim($sfv, '"');
        }
        if ($type === 'bytes') {
            assert($type); // @codeCoverageIgnore
        }
        if ($type === 'item') {
            $parts = explode(';', $sfv);
            $value = trim(array_shift($parts));
            $params = [];
            foreach ($parts as $param) {
                $param = trim($param);
                if (strlen($param)) {
                    [$k, $v] = explode('=', $param, 2) + [1 => '?1'];
                    $params[trim($k)] = trim($v);
                }
            }

            return ['value' => $value, 'params' => $params];
        }
        if ($type === 'list') {
            $items = [];
            foreach (explode(',', $sfv) as $item) {
                $item = trim($item);
                if (strlen($item)) {
                    $items[] = $this->_parseStructuredFieldValue('item', $item);
                }
            }
            return $items;
        }
    } // @codeCoverageIgnore
}
