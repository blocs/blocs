<?php

namespace Blocs\Data;

// data-convertを指定して、プログラムから渡されたデータを表示前に指定した形式に変換
class Convert
{
    // 数値表示
    public static function number($str, $decimals = 0)
    {
        if (!is_numeric($str)) {
            return $str;
        }

        return number_format($str, $decimals);
    }

    // 伏字変換
    public static function hidden($str)
    {
        return str_repeat('*', strlen($str));
    }

    // 日付表示
    public static function jdate($str, $format = 'Y/m/d')
    {
        $date = date($format, strtotime($str));

        $week = [
            'Sun' => '日', 'Mon' => '月', 'Tue' => '火', 'Wed' => '水', 'Thu' => '木', 'Fri' => '金', 'Sat' => '土',
            'Sunday' => '日曜日', 'Monday' => '月曜日', 'Tuesday' => '火曜日', 'Wednesday' => '水曜日', 'Thursday' => '木曜日', 'Friday' => '金曜日', 'Saturday' => '土曜日',
        ];

        foreach ($week as $key => $value) {
            $date = str_replace($key, $value, $date);
        }

        return $date;
    }

    // 日付表示
    public static function date($str, $format = 'Y/m/d')
    {
        $date = date($format, strtotime($str));

        return $date;
    }

    // ファイルサイズ表示
    public static function uploadsize($str)
    {
        $label = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $str >= 1024 && $i < (count($label) - 1); $str /= 1024, $i++) {
        }

        return round($str, 1).' '.$label[$i];
    }

    // サムネイル表示
    public static function raw_upload($str)
    {
        $json = json_decode($str, true);
        if (empty($json)) {
            return '';
        }

        $file = $json[0];
        $downloadUrl = route(prefix().'.download', ['filename' => $file['filename']]).'?'.time();
        if (!empty($file['thumbnail'])) {
            $file['name'] = "<img src='{$downloadUrl}' width=100% />";
        }

        return "<a href='{$downloadUrl}'>{$file['name']}</a>";
    }

    // 省略表記
    public static function ellipsis($str, $length = null, $ellipsis = '...')
    {
        $str = strip_tags($str);
        $str = str_replace(["\r\n", "\r", "\n"], ' ', $str);
        if (!is_numeric($length)) {
            return $str;
        }
        $result = mb_substr($str, 0, $length);

        return $result.($result != $str ? $ellipsis : '');
    }

    // リンク追加
    public static function raw_autolink($str, $target = null)
    {
        $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        $str = str_replace(["\r\n", "\r", "\n"], '<br />', $str);

        if ($target) {
            $replace = "<a href='$1' target='target'>$1</a>";
        } else {
            $replace = "<a href='$1'>$1</a>";
        }

        $str = preg_replace('/(https?:\/\/[^\s<]*)/', $replace, $str);

        return $str;
    }
}
