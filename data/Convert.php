<?php

namespace Blocs\Data;

use Blocs\Common;

// data-convertを指定して、プログラムから渡されたデータを表示前に指定した形式に変換
class Convert
{
    // 数値を指定した小数点桁でフォーマットして表示
    public static function number($str, $decimals = 0)
    {
        if (! is_numeric($str)) {
            return $str;
        }

        return number_format($str, $decimals);
    }

    // 文字列全体を伏字に変換
    public static function hidden($str)
    {
        return str_repeat('*', mb_strlen((string) $str));
    }

    // 日付をフォーマットし、日本語の曜日名に置き換えて表示
    public static function jdate($str, $format = 'Y/m/d')
    {
        $date = self::formatDateString($str, $format);
        $date = self::translateWeekdayLabels($date);

        return $date;
    }

    // 日付を指定したフォーマットで表示
    public static function date($str, $format = 'Y/m/d')
    {
        return self::formatDateString($str, $format);
    }

    // ファイルサイズを人が読みやすい単位に換算して表示
    public static function uploadsize($str)
    {
        $label = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $str >= 1024 && $i < (count($label) - 1); $str /= 1024, $i++) {
        }

        return round($str, 1).' '.$label[$i];
    }

    // アップロードファイルのダウンロードリンクを生成して表示
    public static function raw_download($str, $class = '', $prefix = null)
    {
        $file = self::decodeFileInfo($str);
        if (empty($file)) {
            return '';
        }

        $prefix = self::resolvePrefix($prefix);
        $classAttribute = self::buildClassAttribute($class);
        $downloadUrl = htmlspecialchars(route($prefix.'.download', ['filename' => $file['filename']]).'?'.time(), ENT_QUOTES, 'UTF-8');
        $displayName = htmlspecialchars((string) ($file['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        if (! empty($file['thumbnail'])) {
            $displayName = "<img src='{$downloadUrl}' {$classAttribute}/>";
        }

        return "<a href='{$downloadUrl}' {$classAttribute}>{$displayName}</a>";
    }

    // アップロードファイルのサムネイル画像を生成して表示
    public static function raw_thumbnail($str, $class = '', $prefix = null)
    {
        $file = self::decodeFileInfo($str);
        if (empty($file)) {
            return '';
        }

        $prefix = self::resolvePrefix($prefix);
        $classAttribute = self::buildClassAttribute($class);
        $thumbnailUrl = htmlspecialchars(route($prefix.'.thumbnail', ['filename' => $file['filename'], 'size' => 'thumbnail']), ENT_QUOTES, 'UTF-8');

        return "<img src='{$thumbnailUrl}' {$classAttribute}/>";
    }

    // 指定文字数で省略し、必要に応じて末尾に省略記号を付与
    public static function ellipsis($str, $length = null, $ellipsis = '...')
    {
        $str = strip_tags($str);
        $str = str_replace(["\r\n", "\r", "\n"], ' ', $str);
        if (! is_numeric($length)) {
            return $str;
        }
        $result = mb_substr($str, 0, $length);

        return $result.($result != $str ? $ellipsis : '');
    }

    // URLを自動リンク化し、改行を維持して表示
    public static function raw_autolink($str, $target = null)
    {
        $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        $str = str_replace(["\r\n", "\r", "\n"], '<br />', $str);

        if ($target) {
            $target = htmlspecialchars((string) $target, ENT_QUOTES, 'UTF-8');
            $replace = "<a href='$1' target='{$target}'>$1</a>";
        } else {
            $replace = "<a href='$1'>$1</a>";
        }

        $str = preg_replace('/(https?:\/\/[^\s<]*)/', $replace, $str);

        return $str;
    }

    private static function formatDateString($str, $format)
    {
        return date($format, strtotime($str));
    }

    private static function translateWeekdayLabels($date)
    {
        $week = [
            'Sunday' => '日曜日',
            'Monday' => '月曜日',
            'Tuesday' => '火曜日',
            'Wednesday' => '水曜日',
            'Thursday' => '木曜日',
            'Friday' => '金曜日',
            'Saturday' => '土曜日',
            'Sun' => '日',
            'Mon' => '月',
            'Tue' => '火',
            'Wed' => '水',
            'Thu' => '木',
            'Fri' => '金',
            'Sat' => '土',
        ];

        foreach ($week as $key => $value) {
            $date = str_replace($key, $value, $date);
        }

        return $date;
    }

    private static function decodeFileInfo($str)
    {
        $json = json_decode($str, true);
        if (empty($json) || empty($json[0])) {
            return null;
        }

        return $json[0];
    }

    private static function resolvePrefix($prefix)
    {
        if ($prefix) {
            return $prefix;
        }

        if (function_exists('prefix')) {
            return prefix();
        }

        return Common::routePrefix();
    }

    private static function buildClassAttribute($class)
    {
        if (! $class) {
            return '';
        }

        return 'class="'.htmlspecialchars((string) $class, ENT_QUOTES, 'UTF-8').'"';
    }
}
