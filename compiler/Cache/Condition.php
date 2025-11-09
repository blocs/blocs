<?php

namespace Blocs\Compiler\Cache;

class Condition
{
    private static $partInclude = [];

    public static function partInclude($partInclude)
    {
        self::$partInclude = $partInclude;
    }

    // data-existなどの条件スクリプトを生成する
    public static function condition($compiledTag, $attrList, $quotesList, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $conditionScript = self::generateConditionScript($attrList, $quotesList);

        if ($conditionScript !== '') {
            $compiledTag = $conditionScript.$compiledTag;
        }

        if ($tagName) {
            // タグ記法を使用する場合
            if (substr($compiledTag, -2) === '/>') {
                // 閉じタグを持たないタグの場合
                array_unshift($htmlArray, BLOCS_ENDIF_SCRIPT);
            } else {
                $tagCounter = [
                    'tag' => $tagName,
                    'after' => BLOCS_ENDIF_SCRIPT,
                ];
            }
        }

        return $compiledTag;
    }

    private static function generateConditionScript($attrList, $quotesList)
    {
        if (isset($attrList[BLOCS_DATA_EXIST])) {
            return self::generateDataExistScript($attrList, $quotesList);
        }

        if (isset($attrList[BLOCS_DATA_NONE])) {
            return self::generateDataNoneScript($attrList);
        }

        if (isset($attrList[BLOCS_DATA_IF])) {
            return self::generateDataIfScript($attrList);
        }

        if (isset($attrList[BLOCS_DATA_UNLESS])) {
            return self::generateDataUnlessScript($attrList);
        }

        return '';
    }

    private static function generateDataExistScript($attrList, $quotesList)
    {
        if (isset($quotesList[BLOCS_DATA_EXIST])) {
            // data-includeのチェックを行う
            return empty(self::$partInclude[$attrList[BLOCS_DATA_EXIST]])
                ? "<?php if(false): ?>\n"
                : "<?php if(true): ?>\n";
        }

        return "<?php if(!empty({$attrList[BLOCS_DATA_EXIST]})): ?>\n";
    }

    private static function generateDataNoneScript($attrList)
    {
        return "<?php if(empty({$attrList[BLOCS_DATA_NONE]})): ?>\n";
    }

    private static function generateDataIfScript($attrList)
    {
        return "<?php if({$attrList[BLOCS_DATA_IF]}): ?>\n";
    }

    private static function generateDataUnlessScript($attrList)
    {
        return "<?php if(!({$attrList[BLOCS_DATA_UNLESS]})): ?>\n";
    }
}
