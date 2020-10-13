<?php
namespace ryunosuke\microute\mixin;

/**
 * アノテーションを収集するトレイト
 */
trait Annotatable
{
    /**
     * メンバー名指定でリフレクションオブジェクトを取得
     *
     * - menber 省略で自身のクラス
     * - $member でプロパティ
     * - member でメソッド
     *
     * @param string|null $member メンバー名
     * @return \ReflectionClass|\ReflectionProperty|\ReflectionMethod
     */
    public static function reflect($member = null)
    {
        if (strlen($member) === 0) {
            return new \ReflectionClass(static::class);
        }
        elseif ($member[0] === '$') {
            return new \ReflectionProperty(static::class, substr($member, 1));
        }
        else {
            return new \ReflectionMethod(static::class, $member);
        }
    }

    /**
     * アノテーションを取得（複数）
     *
     * @hoge value1 value2
     * @hoge value3
     *
     * のような場合に `[[value1, value2], [value3]]` を返す。
     *
     * @param string $name アノテーション名
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAll($name, $member = null, $default = null)
    {
        $parse = function ($doccomment, $name) {
            if (preg_match_all('#@' . preg_quote($name, '#') . '(.*)#u', $doccomment, $matches)) {
                return array_map('trim', $matches[1]);
            }
            return null;
        };

        // 継承ツリーをたどる仕様なのでループ
        $refclass = new \ReflectionClass(static::class);
        do {
            $annotation = null;
            // まず自身から
            if (strlen($member)) {
                if ($member[0] === '$' && $refclass->hasProperty(substr($member, 1))) {
                    $annotation = $parse($refclass->getProperty(substr($member, 1))->getDocComment(), $name);
                }
                elseif ($refclass->hasMethod($member)) {
                    $annotation = $parse($refclass->getMethod($member)->getDocComment(), $name);
                }
            }
            // 無いならクラスから
            if ($annotation === null) {
                $annotation = $parse($refclass->getDocComment(), $name);
            }
            // 見つかったのならそれで
            if ($annotation !== null) {
                break;
            }
        } while ($refclass = $refclass->getParentClass());

        return $annotation ?? $default;
    }

    /**
     * アノテーションを取得（真偽値）
     *
     * @hoge true false
     * @hoge false
     *
     * のような場合に `true` を返す。
     *
     * @param string $name アノテーション名
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAsBool($name, $member = null, $default = null)
    {
        $annotation = self::getAnnotationAsString($name, $member, null);
        if ($annotation === null) {
            return $default;
        }

        if (in_array(strtolower($annotation), ['false', 'off', 'no'], true)) {
            return false;
        }
        return boolval($annotation);
    }

    /**
     * アノテーションを取得（整数値）
     *
     * @hoge 12 34
     * @hoge 56
     *
     * のような場合に `12` を返す。
     *
     * @param string $name アノテーション名
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAsInt($name, $member = null, $default = null)
    {
        $annotation = self::getAnnotationAsString($name, $member, null);
        if ($annotation === null) {
            return $default;
        }

        return intval($annotation);
    }

    /**
     * アノテーションを取得（文字列）
     *
     * @hoge value1 value2
     * @hoge value3
     *
     * のような場合に `"value1 value2"` を返す。
     *
     * @param string $name アノテーション名
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAsString($name, $member = null, $default = null)
    {
        $annotations = self::getAnnotationAll($name, $member, null);
        if ($annotations === null) {
            return $default;
        }

        return reset($annotations);
    }

    /**
     * アノテーションを取得（通常配列）
     *
     * @hoge value1 value2
     * @hoge value3
     *
     * のような場合に `[value1, value2, value3]` を返す。
     *
     * @param string $name アノテーション名
     * @param string $delimiter デリミタ
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAsList($name, $delimiter, $member = null, $default = null)
    {
        $annotations = self::getAnnotationAll($name, $member, null);
        if ($annotations === null) {
            return $default;
        }

        $result = [];
        foreach ($annotations as $annotation) {
            $result = array_merge($result, array_map('trim', explode($delimiter, $annotation)));
        }
        return $result;
    }

    /**
     * アノテーションを取得（連想配列）
     *
     * @hoge value1 value2
     * @hoge value3
     *
     * のような場合に `[[value1 => value2], [value3 => null]]` を返す。
     *
     * @param string $name アノテーション名
     * @param array $keymap 読み替えマップ。 null はキーを表す
     * @param string|null $member メンバー名
     * @param mixed $default デフォルト値
     * @return mixed アノテーションがないなら $default
     */
    public static function getAnnotationAsHash($name, $keymap, $member = null, $default = null)
    {
        $annotations = self::getAnnotationAll($name, $member, null);
        if ($annotations === null) {
            return $default;
        }

        $count = count($keymap);
        $keyIndex = array_search(null, $keymap, true);
        if ($keyIndex !== false) {
            unset($keymap[$keyIndex]);
        }

        $result = [];
        foreach ($annotations as $annotation) {
            $parts = preg_split('#\\s+#', $annotation, $count, PREG_SPLIT_NO_EMPTY);

            $key = null;
            if ($keyIndex !== false) {
                $key = $parts[$keyIndex] ?? '';
                unset($parts[$keyIndex]);
            }

            if ($keymap) {
                $parts = array_combine($keymap, array_pad(array_values($parts), count($keymap), null));
            }
            $key === null ? $result[] = $parts : $result[$key] = $parts;
        }
        return $result;
    }
}
