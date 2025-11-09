<?php

namespace Blocs;

use Illuminate\Support\Facades\Lang as LaravelLang;

class Lang
{
    // data-langで指定されたコードからメッセージ文字列を取得
    public static function get($messageCode)
    {
        $messageParts = self::parseMessageCode($messageCode);
        if (is_null($messageParts)) {
            return $messageCode;
        }

        [$categoryCode, $args] = $messageParts;
        $messageTemplate = self::resolveMessageTemplate($categoryCode);

        return is_null($messageTemplate)
            ? $messageCode
            : self::replaceMessageArguments($messageTemplate, $args);
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
        if (defined('BLOCS_ROOT_DIR') && is_file(BLOCS_ROOT_DIR.'/lang.json')) {
            $messageCatalog = json_decode(file_get_contents(BLOCS_ROOT_DIR.'/lang.json'), true);

            return is_array($messageCatalog) ? ($messageCatalog[$categoryCode] ?? null) : null;
        }

        $message = LaravelLang::get($categoryCode);

        return $message === $categoryCode ? null : $message;
    }

    private static function replaceMessageArguments($message, array $msgArgList)
    {
        foreach ($msgArgList as $index => $argument) {
            // メッセージ内のプレースホルダーを差し替え
            $message = str_replace('{'.($index + 1).'}', $argument, $message);
        }

        return $message;
    }
}
