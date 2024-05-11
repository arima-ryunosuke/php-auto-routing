<?php
namespace ryunosuke\microute\mixin;

/**
 * ユーティリティ関数トレイト
 */
trait Utility
{
    private function reverseRegex(string $regex, array &$params): string
    {
        $url = $regex;
        foreach ($params as $key => $val) {
            $qkey = preg_quote($key);
            $pattern = "(\( (\?P?<$qkey>)? (?:[^()]+ | (?1) )* \))";
            if (preg_match("#$pattern#x", $url, $m)) {
                // スラッシュはルーティングで使うこともあるので encode しない
                $val = strtr(rawurlencode($val), ['%2F' => '/']);
                $url = str_replace($m[1], $val, $url);
                unset($params[$key]);
            }
        }
        return $url;
    }

    private function regexParameter(string $regex): array
    {
        // 無理やり match させることで名前付きキャプチャーの名前一覧が得られる
        preg_match_all("#$regex#", '', $m);
        return array_flip(preg_grep('#^[0-9]+$#', array_keys($m), PREG_GREP_INVERT));
    }

    private function actionMethodToAction(string $action): string
    {
        // default アクションはアクションなしと等価（設定レベルではなく規約レベル）
        if ($action === 'default') {
            $action = '';
        }
        return ltrim(strtolower(preg_replace('#(?<!/)[A-Z]([A-Z](?![a-z]))*#', '-$0', $action)), '-');
    }
}
