<?php

namespace Blocs\Compiler\Cache;

class Condition
{
    // 条件を指定するデータ属性があるか
    public static function has($attrList): bool
    {
        return isset($attrList[BLOCS_DATA_EXIST]) || isset($attrList[BLOCS_DATA_NONE]) || isset($attrList[BLOCS_DATA_IF]) || isset($attrList[BLOCS_DATA_UNLESS]);
    }

    /**
     * data-existなどの条件スクリプトを生成する
     *
     * @param  array  $partInclude  読み込み済みのブロック（data-exist="ブロック名" の判定に使う）
     * @param  string  $tagName  タグ記法の場合のタグ名。閉じタグで条件を閉じるカウンターを $tagCounter に返す
     */
    public static function condition($compiledTag, $attrList, $quotesList, array $partInclude = [], $tagName = '', &$tagCounter = null)
    {
        $compiledTag = self::generateConditionScript($attrList, $quotesList, $partInclude).$compiledTag;

        if ($tagName) {
            $tagCounter = [
                'tag' => $tagName,
                'after' => BLOCS_ENDIF_SCRIPT,
            ];
        }

        return $compiledTag;
    }

    private static function generateConditionScript($attrList, $quotesList, array $partInclude)
    {
        if (isset($attrList[BLOCS_DATA_EXIST])) {
            return self::generateDataExistScript($attrList, $quotesList, $partInclude);
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

    private static function generateDataExistScript($attrList, $quotesList, array $partInclude)
    {
        if (isset($quotesList[BLOCS_DATA_EXIST])) {
            // data-includeのチェックを行う
            return empty($partInclude[$attrList[BLOCS_DATA_EXIST]])
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
