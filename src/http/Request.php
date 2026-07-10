<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @property-read SessionInterface $session
 * @property-read PayloadBag $payload
 */
class Request extends \Symfony\Component\HttpFoundation\Request
{
    public InputBag $get;

    public InputBag $post;
    public InputBag $body;

    public InputBag $input;

    private PayloadBag $payload;

    private function _customize(): static
    {
        $this->get = $this->query;
        $this->post = $this->request;
        $this->body = $this->request;
        $this->input = $this->isMethod('GET') ? $this->query : $this->request;

        $files = $this->files->all();
        array_walk_recursive($files, function (&$value) {
            if ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $value = UploadedFile::fromSymfonyFile($value);
            }
        });
        $this->files->replace($files);

        return $this;
    }

    public function duplicate(?array $query = null, ?array $request = null, ?array $attributes = null, ?array $cookies = null, ?array $files = null, ?array $server = null): static
    {
        return parent::duplicate($query, $request, $attributes, $cookies, $files, $server)->_customize();
    }

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->_customize();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'session') {
            return $this->getSession();
        }
        // 使わない場合は完全に無駄となるので遅延取得する（symfony が getPayload としているのもそのためだと思う）
        // @todo php8.4 ならプロパティフックで対応できるはず…
        if ($name === 'payload') {
            return $this->payload ??= new PayloadBag($this);
        }
        throw new \InvalidArgumentException("$name is not supported property");
    }

    /**
     * GET, POST, COOKIE の優先順位でリクエスト値を返す
     *
     * いわゆる $_REQUEST 変数に値するが、request_order などの影響は受けない。
     */
    public function input(string $key, mixed $default = null): mixed
    {
        foreach ([$this->query, $this->request, $this->cookies] as $bag) {
            if ($bag->has($key)) {
                return $bag->get($key);
            }
        }

        return $default;
    }

    /**
     * パラメータから指定したものを返し、無かったら例外を投げる
     *
     * 存在しない or filter_var による検証が失敗した場合は例外を投げる。
     * filter_var の引数体系はかなり特殊なので、options と flags は引数を分けてかつ名前付き引数で指定する。
     *
     * $bags 引数でどのパラメータから取得するかを指定できる（省略時は GET/POST/COOKIE 全て）。
     * 本来は InputBag 自体に生やしたいメソッドだが、symfony が認めていないので苦肉の引数。
     * https://github.com/symfony/symfony/issues/62443
     */
    public function require(
        string              $key,
        int                 $filter = FILTER_DEFAULT,
        null|InputBag|array $bags = null,
        null|int|float      $min_range = null, // for FILTER_VALIDATE_INT, FILTER_VALIDATE_FLOAT
        null|int|float      $max_range = null, // for FILTER_VALIDATE_INT, FILTER_VALIDATE_FLOAT
        null|string         $decimal = null,   // for FILTER_VALIDATE_FLOAT
        null|string         $regexp = null,    // for FILTER_VALIDATE_REGEXP
        null|int            $flags = null,
        mixed               ...$otherOptions,
    ): mixed {
        $bags ??= [$this->get, $this->post, $this->cookies];
        $bags = is_array($bags) ? $bags : [$bags];

        $options = array_filter(array_replace($otherOptions, [
            'min_range' => $min_range,
            'max_range' => $max_range,
            'decimal'   => $decimal,
            'regexp'    => $regexp,
        ]), fn($v) => $v !== null);

        $flags ??= FILTER_FLAG_NONE;
        $flags |= FILTER_NULL_ON_FAILURE;

        foreach ($bags as $bag) {
            $value = $bag->has($key) ? $bag->all()[$key] : $this;
            if ($value === $this) {
                continue;
            }

            $value = filter_var($value, $filter, ['options' => $options, 'flags' => $flags]);
            if ($value === null) {
                continue;
            }

            return $value;
        }
        throw new BadRequestException(sprintf('Input value "%s" is missing or mismatch.', $key));
    }

    /**
     * 現在のリクエストメソッドパラメータから最初に見つかったものを返す
     *
     * Symfony の型の制約を受けない（配列型でもそのまま返す）。
     * 無かった場合は null 固定。
     */
    public function any(string ...$keys): mixed
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
     */
    public function only(string ...$keys): array
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
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->input->all(), array_flip($keys));
    }

    /**
     * 指定パス以下をパスパラメータとして扱って連想配列で返す
     *
     * basepath が一致しない場合は null を返す。
     *
     * キーのみで値が無い場合は null を返す。
     * ただし、/ で終わる場合は空文字が指定されているとみなして空文字を返す。
     *
     * - e.g. /base/hoge/1/fuga/2/piyo/3 => ["hoge" => "1", "fuga" => "2", "piyo" => "3]
     * - e.g. /base/hoge/1/fuga/2/piyo/ => ["hoge" => "1", "fuga" => "2", "piyo" => ""]
     * - e.g. /base/hoge/1/fuga/2/piyo => ["hoge" => "1", "fuga" => "2", "piyo" => null]
     * - e.g. /not-base/hoge/1/fuga/2/piyo => null
     */
    public function getPathParameters(string $basePath, bool $stripExtension): ?array
    {
        $basepath = rtrim($this->getBasePath(), '/');
        $currentpath = $this->getPathInfo();
        $pathInfo = $basepath . $currentpath;
        if (!str_starts_with($pathInfo, $basePath)) {
            return null;
        }

        if ($stripExtension) {
            $extension = pathinfo($pathInfo, PATHINFO_EXTENSION);
            $pathInfo = preg_replace('#\\.' . preg_quote($extension) . '$#', '', $pathInfo);
        }

        $basePath = rtrim($basePath, '/') . '/';
        $remainder = substr($pathInfo, strlen($basePath));
        $segments = explode('/', $remainder);
        if ($segments === ['']) {
            return [];
        }

        $params = [];
        for ($i = 0; $i < count($segments); $i += 2) {
            if (strlen($segments[$i]) || isset($segments[$i + 1])) {
                $params[$segments[$i]] = $segments[$i + 1] ?? null;
            }
        }

        return $params;
    }

    public function getUserAgent(): ?string
    {
        return $this->headers->get('USER-AGENT');
    }

    public function getReferer(): ?string
    {
        return $this->headers->get('REFERER');
    }

    public function isAsynchronous(): bool
    {
        return $this->headers->get('sec-fetch-dest') === 'empty' || $this->isXmlHttpRequest();
    }

    public function getClientHints(bool $raw = false, string $alternativeCookie = 'client_hints'): array
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

    private function _parseStructuredFieldValue(string $type, string $sfv): mixed
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
