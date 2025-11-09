<?php

namespace Blocs\Data;

// data-filterを記述したフォーム部品から入力された値を、指定した形式で変換
// |（パイプ）でつなぐことで複数のフィルターを指定できる
class Filter
{
    // 入力された全角英字・数字・スペースを半角へ統一
    public static function single($str)
    {
        return self::convertKanaByOption($str, 'ras');
    }

    // 半角の英数字・スペース・カタカナ・濁点付き文字を全角へ統一
    public static function multi($str)
    {
        return self::convertKanaByOption($str, 'ASKV');
    }

    // 半角・全角カタカナを全角ひらがなに変換
    public static function hiragana($str)
    {
        return self::convertKanaByOption($str, 'HVc');
    }

    // 半角カタカナと全角ひらがなを全角カタカナに変換
    public static function katakana($str)
    {
        return self::convertKanaByOption($str, 'KVC');
    }

    // 全角カタカナ・全角ひらがなを半角カタカナに変換
    public static function halfKatakana($str)
    {
        return self::convertKanaByOption($str, 'kh');
    }

    // 半角カタカナを全角カタカナに変換
    public static function antiHalfKatakana($str)
    {
        return self::convertKanaByOption($str, 'KV');
    }

    // 電話番号を半角へ統一し、ハイフン区切りへ変換
    public static function phone($str)
    {
        $normalized = self::convertKanaByOption($str, 'ras');
        $normalized = self::normalizeHyphenVariants($normalized);

        return $normalized;
    }

    // 郵便番号を半角へ統一し、ハイフン区切りへ変換
    public static function postal($str)
    {
        $normalized = self::convertKanaByOption($str, 'ras');
        $normalized = self::normalizeHyphenVariants($normalized);
        if (strlen($normalized) == 7 || preg_match('/^[0-9]+$/', $normalized)) {
            $normalized = substr($normalized, 0, 3).'-'.substr($normalized, 3);
        }

        return $normalized;
    }

    // 日付を半角に統一し、datepicker形式へ整形
    public static function datepicker($str)
    {
        $normalized = self::convertKanaByOption($str, 'ras');
        $normalized = str_replace('/', '-', $normalized);
        if (preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $normalized) && strlen($normalized) != 10) {
            $dateParts = explode('-', $normalized);

            // 月日の箇所をゼロ詰めに整形
            return vsprintf('%4d-%02d-%02d', $dateParts);
        }

        return $normalized;
    }

    private static function convertKanaByOption($str, $option)
    {
        return mb_convert_kana($str, $option);
    }

    private static function normalizeHyphenVariants($str)
    {
        return str_replace(['ー', '―', '‐'], '-', $str);
    }
}
