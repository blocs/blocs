<?php

namespace Blocs;

class Lang
{
    // data-langで指定されたコードのメッセージを取得
    public static function get($messageCode)
    {
        $langStringList = explode(':', $messageCode);
        if (count($langStringList) < 2) {
            return $messageCode;
        }

        $category = $langStringList[0];
        $code = $langStringList[1];
        $msgArgList = array_slice($langStringList, 2);

        if (defined('BLOCS_ROOT_DIR') && is_file(BLOCS_ROOT_DIR.'/lang.json')) {
            $message = json_decode(file_get_contents(BLOCS_ROOT_DIR.'/lang.json'), true);

            if (!isset($message[$category.':'.$code])) {
                return $messageCode;
            }
            $message = $message[$category.':'.$code];
        } else {
            $message = \Lang::get($category.':'.$code);

            if ($message === $category.':'.$code) {
                return $messageCode;
            }
        }

        foreach ($msgArgList as $num => $msgArg) {
            // メッセージの置換
            $message = str_replace('{'.($num + 1).'}', $msgArg, $message);
        }

        return $message;
    }
}

/* End of file */
