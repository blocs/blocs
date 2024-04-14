<?php

namespace Blocs\Compiler;

trait ParserTrait
{
    private static $deleteAttribute = [];

    private static function addAttrList(&$attrList, &$quotesList, &$rawString, &$parsedHtml, $attrName, $attrString, $commentParse)
    {
        $attrString = self::replaceAliasAttrName($attrString);
        $attrValueList = preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);

        // 引数を取得
        $attrValue = '';

        // 一つ目は必ず引数とする
        !empty($attrName) && count($attrValueList) && $attrValue = array_shift($attrValueList);

        foreach (array_reverse($attrValueList) as $attrBuff) {
            if (preg_match('/^'.BLOCS_ATTR_NAME_REGREX.'$/s', $attrBuff)) {
                // 値のない属性
                $attrList[$attrBuff] = '';

                array_pop($attrValueList);
            } elseif (!strlen(trim($attrBuff))) {
                array_pop($attrValueList);
            } else {
                break;
            }
        }

        $attrValue .= implode('', $attrValueList);

        if (empty($attrName)) {
            // 値のない属性
            strlen($attrValue) && $attrList[$attrValue] = '';

            return;
        }

        if (!strncmp($attrName, ':', 1) && $commentParse) {
            // data-attributeの省略表記（コメント記法）
            $attrList[BLOCS_DATA_ATTRIBUTE] = '"'.substr($attrName, 1).'"';
            $attrList[BLOCS_DATA_VAL] = $attrValue;
            unset($attrList[$attrName]);

            $quotesList[BLOCS_DATA_ATTRIBUTE] = '"';
            if (isset($quotesList[$attrName])) {
                $quotesList[BLOCS_DATA_VAL] = $quotesList[$attrName];
                unset($quotesList[$attrName]);
            }

            // データ属性を削除
            self::$deleteAttribute[$attrName] = $attrValue;

            return;
        }

        if (!strncmp($attrName, ':', 1) && !$commentParse) {
            // data-attributeの省略表記（タグ記法）
            $commentAttribute = BLOCS_DATA_ATTRIBUTE.'="'.substr($attrName, 1).'"';
            $commentVal = BLOCS_DATA_VAL.'='.$attrValue;

            // コメントとして代入
            array_push($parsedHtml, '<!-- '.$commentAttribute.' '.$commentVal.' -->');

            // 属性値をクリア
            unset($attrList[$attrName]);
            unset($quotesList[$attrName]);

            // データ属性を削除
            self::$deleteAttribute[$attrName] = $attrValue;

            return;
        }

        if (!strncmp($attrName, '!', 1)) {
            // data-validateの省略表記（コメント記法のみ）
            $attrList[BLOCS_DATA_FORM] = '"'.substr($attrName, 1).'"';
            $attrList[BLOCS_DATA_VALIDATE] = $attrValue;
            unset($attrList[$attrName]);

            $quotesList[BLOCS_DATA_FORM] = '"';
            if (isset($quotesList[$attrName])) {
                $quotesList[BLOCS_DATA_VALIDATE] = $quotesList[$attrName];
                unset($quotesList[$attrName]);
            }

            // データ属性を削除
            self::$deleteAttribute[$attrName] = $attrValue;

            return;
        }

        $attrList[$attrName] = $attrValue;
    }

    private static function replaceAliasAttrName($rawString)
    {
        // エイリアス名を変換
        foreach (self::$aliasAttrName as $aliasName => $attrName) {
            $rawString = str_replace($aliasName, $attrName, $rawString);
        }

        return $rawString;
    }

    private static function escepeOperator($htmlString)
    {
        $htmlString = str_replace('-->', 'REPLACE_TO_COMMENT_OPERATOR', $htmlString);

        foreach (self::$escapeOperatorList as $num => $escapeOperator) {
            $htmlString = str_replace($escapeOperator, "REPLACE_TO_OPERATOR_{$num}", $htmlString);
        }

        $htmlString = str_replace('REPLACE_TO_COMMENT_OPERATOR', '-->', $htmlString);

        return $htmlString;
    }

    private static function backOperator($htmlString)
    {
        foreach (self::$escapeOperatorList as $num => $escapeOperator) {
            $htmlString = str_replace("REPLACE_TO_OPERATOR_{$num}", $escapeOperator, $htmlString);
        }

        return $htmlString;
    }

    private static function deleteTagName($attrString, $tagName)
    {
        strncmp($attrString, '<'.$tagName, strlen('<'.$tagName)) || $attrString = substr($attrString, strlen('<'.$tagName));

        return $attrString;
    }
}
