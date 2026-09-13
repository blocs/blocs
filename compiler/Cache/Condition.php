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
            self::assertConditionValue(BLOCS_DATA_NONE, $attrList[BLOCS_DATA_NONE]);

            return "<?php if(empty({$attrList[BLOCS_DATA_NONE]})): ?>\n";
        }

        if (isset($attrList[BLOCS_DATA_IF])) {
            self::assertConditionValue(BLOCS_DATA_IF, $attrList[BLOCS_DATA_IF]);

            return "<?php if({$attrList[BLOCS_DATA_IF]}): ?>\n";
        }

        if (isset($attrList[BLOCS_DATA_UNLESS])) {
            self::assertConditionValue(BLOCS_DATA_UNLESS, $attrList[BLOCS_DATA_UNLESS]);

            return "<?php if(!({$attrList[BLOCS_DATA_UNLESS]})): ?>\n";
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

        self::assertConditionValue(BLOCS_DATA_EXIST, $attrList[BLOCS_DATA_EXIST]);

        return "<?php if(!empty({$attrList[BLOCS_DATA_EXIST]})): ?>\n";
    }

    /**
     * 条件が空のままスクリプトを組み立てると if() のような不正なPHPになるため、
     * コンパイル時に data-loop などと同じ形式のエラーとして知らせる
     */
    private static function assertConditionValue($attrName, $attrValue): void
    {
        if (strlen(trim((string) $attrValue))) {
            return;
        }

        throw new \RuntimeException('B002: Invalid condition "'.$attrName.'" ('.$attrValue.')');
    }
}
