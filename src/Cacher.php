<?php
namespace ryunosuke\microute;

use Psr\SimpleCache\CacheInterface;

/**
 * psr-16 の素朴な実装クラス
 *
 * リファレンス実装…というか、サンプルのために psr16 実装ライブラリに依存したくないので「単に最低限動かすだけ」のために近い。
 */
class Cacher implements CacheInterface
{
    /** @var array キャッシュエントリ */
    private $entries = [];

    /** @var string 保存ファイル名 */
    private $filename;

    /** @var bool 変更フラグ */
    private $changed = false;

    public function __construct(string $dirname = null)
    {
        $dirname = $dirname ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'microute');
        is_dir($dirname) or mkdir($dirname, 0777, true);

        $this->filename = $dirname . DIRECTORY_SEPARATOR . 'all.cache';
        if (is_file($this->filename)) {
            $this->entries = require $this->filename;
        }
    }

    public function __destruct()
    {
        if ($this->changed) {
            $contents = '<?php return ' . var_export($this->entries, true) . ';';
            $tempnam = tempnam(sys_get_temp_dir(), 'tmp');
            if (file_put_contents($tempnam, $contents) !== false) {
                if (rename($tempnam, $this->filename)) {
                    @chmod($this->filename, 0666 & ~umask());
                }
            }
        }
    }

    public function has($key)
    {
        $dummy = new \stdClass();
        return $dummy !== $this->get($key, $dummy);
    }

    public function get($key, $default = null)
    {
        if (!isset($this->entries[$key])) {
            return $default;
        }

        list($expire, $value) = $this->entries[$key];
        if ($expire !== null && $expire <= time()) {
            $this->changed = true;
            unset($this->entries[$key]);
            return $default;
        }
        return $value;
    }

    public function getMultiple($keys, $default = null)
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function set($key, $value, $ttl = null)
    {
        assert(strlen($key) > 0);
        assert(strpbrk($key, '{}()/\@:') === false);

        if ($ttl instanceof \DateInterval) {
            $expire = (new \DateTime())->add($ttl)->getTimestamp();
        }
        elseif (is_int($ttl)) {
            $expire = time() + $ttl;
        }
        else {
            $expire = null;
        }

        $entry = [$expire, $value];

        $this->changed = $this->changed || ($this->entries[$key] ?? null) !== $entry;
        $this->entries[$key] = $entry;
        return true;
    }

    public function setMultiple($values, $ttl = null)
    {
        $result = true;
        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl) && $result;
        }
        return $result;
    }

    public function delete($key)
    {
        if (isset($this->entries[$key])) {
            $this->changed = true;
            unset($this->entries[$key]);
            return true;
        }
        return false;
    }

    public function deleteMultiple($keys)
    {
        $result = true;
        foreach ($keys as $key) {
            $result = $this->delete($key) && $result;
        }
        return $result;
    }

    public function clear()
    {
        $this->changed = true;
        $this->entries = [];

        if (is_file($this->filename)) {
            return unlink($this->filename);
        }
        return true;
    }
}
