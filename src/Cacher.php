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
    const EXTENSION = 'cache';

    /** @var string 保存ディレクトリ */
    private $dirname;

    public function __construct(string $dirname = null)
    {
        $this->dirname = $dirname ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'microute');
        is_dir($this->dirname) or mkdir($this->dirname, 0777, true);
    }

    protected function filename($key)
    {
        assert(strlen($key) > 0);
        assert(strpbrk($key, '{}()/\@:') === false);

        return $this->dirname . DIRECTORY_SEPARATOR . $key . '.' . self::EXTENSION;
    }

    public function has($key)
    {
        $dummy = new \stdClass();
        return $dummy !== $this->get($key, $dummy);
    }

    public function get($key, $default = null)
    {
        $filename = $this->filename($key);

        if (!is_file($filename)) {
            return $default;
        }

        list($expire, $value) = require $filename;
        if ($expire !== null && $expire <= time()) {
            unlink($filename);
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
        $filename = $this->filename($key);

        if ($ttl instanceof \DateInterval) {
            $expire = (new \DateTime())->add($ttl)->getTimestamp();
        }
        elseif (is_int($ttl)) {
            $expire = time() + $ttl;
        }
        else {
            $expire = null;
        }

        $value = '<?php return ' . var_export([$expire, $value], true) . ';';

        $tempnam = tempnam($this->dirname, 'tmp');
        if (file_put_contents($tempnam, $value) !== false) {
            if (rename($tempnam, $filename)) {
                @chmod($filename, 0666 & ~umask());
                return true;
            }
            unlink($tempnam);
        }
        return false;
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
        $filename = $this->filename($key);

        if (!is_file($filename)) {
            return false;
        }
        return unlink($filename);
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
        $result = true;
        foreach (new \FilesystemIterator($this->dirname, \FilesystemIterator::SKIP_DOTS) as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === self::EXTENSION) {
                $result = unlink($file) && $result;
            }
        }
        return $result;
    }
}
