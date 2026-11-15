<?php

namespace Blocs;

use Illuminate\Support\Facades\Lang as LaravelLang;

class Lang
{
    /**
     * lang.json の内容（未確認なら null、ファイルが無ければ false）
     *
     * @var array<string, string>|false|null
     */
    private static $messageCatalog = null;

    /**
     * 常駐ワーカー（Octane）で lang.json の変更を取り込めるよう、リクエスト開始時に呼ぶ
     */
    public static function flush(): void
    {
        self::$messageCatalog = null;
    }

    /**
     * data-langで指定されたコードからメッセージ文字列を取得
     *
     * @param  string  $messageCode  「カテゴリ:コード[:引数...]」
     * @param  list<mixed>  $arguments  追加の引数。「:」を含む値（時刻やURLなど）も分割されずに渡せる
     */
    public static function get($messageCode, array $arguments = [])
    {
        $messageCode = (string) $messageCode;
        $fullMessageCode = implode(':', [$messageCode, ...$arguments]);

        $messageParts = self::parseMessageCode($messageCode);
        if (is_null($messageParts)) {
            return $fullMessageCode;
        }

        [$categoryCode, $args] = $messageParts;
        $messageTemplate = self::resolveMessageTemplate($categoryCode);

        return is_null($messageTemplate)
            ? $fullMessageCode
            : self::replaceMessageArguments($messageTemplate, [...$args, ...$arguments]);
    }

    private static function parseMessageCode($messageCode)
    {
        $messageChunks = explode(':', $messageCode);
        if (count($messageChunks) < 2) {
            return null;
        }

        $categoryCode = $messageChunks[0].':'.$messageChunks[1];
        $argumentList = array_slice($messageChunks, 2);

        return [$categoryCode, $argumentList];
    }

    private static function resolveMessageTemplate($categoryCode)
    {
        if (self::$messageCatalog === null) {
            self::$messageCatalog = false;

            if (defined('BLOCS_ROOT_DIR') && is_file(BLOCS_ROOT_DIR.'/lang.json')) {
                $messageCatalog = json_decode((string) file_get_contents(BLOCS_ROOT_DIR.'/lang.json'), true);
                self::$messageCatalog = is_array($messageCatalog) ? $messageCatalog : [];
            }
        }

        if (self::$messageCatalog !== false) {
            return self::$messageCatalog[$categoryCode] ?? null;
        }

        if (defined('BLOCS_NO_LARAVEL')) {
            // Laravel を使わない環境では lang.json だけを参照する
            return null;
        }

        $message = LaravelLang::get($categoryCode);

        return $message === $categoryCode ? null : $message;
    }

    private static function replaceMessageArguments($message, array $msgArgList)
    {
        // 引数の値に含まれる {2} などを再び置換しないよう、まとめて差し替える
        $replacePairs = [];
        foreach (array_values($msgArgList) as $index => $argument) {
            $replacePairs['{'.($index + 1).'}'] = is_scalar($argument) ? (string) $argument : '';
        }

        return strtr($message, $replacePairs);
    }
}
