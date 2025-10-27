<?php

namespace Blocs\Data;

// data-filterを記述したフォーム部品から入力された値を、指定した形式で変換
// |（パイプ）でつなぐことで複数のフィルターを指定できる
class Filter
{
    // 「全角」英字、英数字、スペースを「半角」に変換
    public static function single($str)
    {
        return mb_convert_kana($str, 'ras');
    }

    // 「半角」英数字、スペース、カタカナ、濁点付きの文字を「全角」に変換
    public static function multi($str)
    {
        return mb_convert_kana($str, 'ASKV');
    }

    // 「半角カタカナ」と「全角カタカナ」を「全角ひらがな」に変換
    public static function hiragana($str)
    {
        return mb_convert_kana($str, 'HVc');
    }

    // 「半角カタカナ」と「全角ひらがな」を「全角カタカナ」に変換
    public static function katakana($str)
    {
        return mb_convert_kana($str, 'KVC');
    }

    // 「全角カタカナ」、「全角ひらがな」を「半角カタカナ」に変換
    public static function halfKatakana($str)
    {
        return mb_convert_kana($str, 'kh');
    }

    // 「半角カタカナ」を「全角カタカナ」に変換
    public static function antiHalfKatakana($str)
    {
        return mb_convert_kana($str, 'KV');
    }

    // 電話番号のハイフン区切りに変換
    public static function phone($str)
    {
        $str = mb_convert_kana($str, 'ras');

        return str_replace(['ー', '―', '‐'], '-', $str);
    }

    // 郵便番号のハイフン区切りに変換
    public static function postal($str)
    {
        $str = mb_convert_kana($str, 'ras');
        $str = str_replace(['ー', '―', '‐'], '-', $str);
        if (strlen($str) == 7 || preg_match('/^[0-9]+$/', $str)) {
            $str = substr($str, 0, 3).'-'.substr($str, 3);
        }

        return $str;
    }

    // 日付をdatepicker形式に変換
    public static function datepicker($str)
    {
        $str = mb_convert_kana($str, 'ras');
        $str = str_replace('/', '-', $str);
        if (preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $str) && strlen($str) != 10) {
            $tmp = explode('-', $str);

            return vsprintf('%4d-%02d-%02d', $tmp); // 月日の箇所をゼロ詰めに整形
        }

        return $str;
    }
}
