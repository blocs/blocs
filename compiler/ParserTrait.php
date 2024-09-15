<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait ParserTrait
{
    private static $deleteAttribute = [];

    private static function addAttrList(&$attrList, &$quotesList, &$rawString, &$parsedHtml, $attrName, $attrValueList, $commentParse)
    {
        // 属性値を取得
        $attrValue = '';

        // 一つ目は必ず属性値とする
        !empty($attrName) && count($attrValueList) && $attrValue = array_shift($attrValueList);

        foreach (array_reverse($attrValueList) as $attrBuff) {
            if (!strlen(trim($attrBuff))) {
                // 空白
                array_pop($attrValueList);
            } elseif (self::checkAttrName($attrBuff)) {
                // 値のない属性
                $attrList[$attrBuff] = '';

                array_pop($attrValueList);
            } elseif (self::checkAttrValue($attrBuff)) {
                // data-valの省略表記
                $attrList[BLOCS_DATA_VAL] = $attrBuff;

                array_pop($attrValueList);
            } else {
                break;
            }
        }

        // 属性値をセット
        $attrValue .= implode('', $attrValueList);

        if (isset($quotesList[$attrName]) && substr($attrValue, 0, 1) === $quotesList[$attrName] && substr($attrValue, -1) === $quotesList[$attrName]) {
            // "", ''で囲われている属性
            $attrValue = substr($attrValue, 1, -1);
        } else {
            // "", ''で囲われていない属性
            unset($quotesList[$attrName]);
        }

        if (empty($attrName)) {
            if (!strlen($attrValue)) {
                return;
            }

            if (self::checkAttrValue($attrValue)) {
                // data-valの省略表記
                $attrList[BLOCS_DATA_VAL] = $attrValue;
            } else {
                // 値のない属性
                $attrList[$attrValue] = '';
            }

            return;
        }

        if (!strncmp($attrName, ':', 1) && $commentParse) {
            // data-attributeの省略表記（コメント記法）
            $attrList[BLOCS_DATA_ATTRIBUTE] = substr($attrName, 1);
            $attrList[BLOCS_DATA_VAL] = $attrValue;
            unset($attrList[$attrName]);

            $quotesList[BLOCS_DATA_ATTRIBUTE] = '"';
            if (isset($quotesList[$attrName])) {
                $quotesList[BLOCS_DATA_VAL] = $quotesList[$attrName];
                unset($quotesList[$attrName]);
            }

            // データ属性を削除
            $rawString = '';

            return;
        }

        if (!strncmp($attrName, ':', 1) && !$commentParse) {
            // data-attributeの省略表記（タグ記法）
            $commentAttribute = BLOCS_DATA_ATTRIBUTE.'="'.substr($attrName, 1).'"';
            if (isset($quotesList[$attrName])) {
                $commentVal = BLOCS_DATA_VAL.'='.$quotesList[$attrName].$attrValue.$quotesList[$attrName];
            } else {
                $commentVal = BLOCS_DATA_VAL.'='.$attrValue;
            }

            // コメントとして代入
            array_push($parsedHtml, '<!-- '.$commentAttribute.' '.$commentVal.' -->');

            // データ属性を削除
            if (isset($quotesList[$attrName])) {
                $rawString = self::deleteDataAttribute($attrName, $quotesList[$attrName].$attrValue.$quotesList[$attrName], $rawString);
            } else {
                $rawString = self::deleteDataAttribute($attrName, $attrValue, $rawString);
            }

            // 属性値をクリア
            unset($attrList[$attrName]);
            unset($quotesList[$attrName]);

            return;
        }

        if (!strncmp($attrName, '!', 1) && $commentParse) {
            // data-validateの省略表記（コメント記法のみ）
            $attrList[BLOCS_DATA_FORM] = substr($attrName, 1);
            $attrList[BLOCS_DATA_VALIDATE] = $attrValue;
            unset($attrList[$attrName]);

            $quotesList[BLOCS_DATA_FORM] = '"';
            if (isset($quotesList[$attrName])) {
                $quotesList[BLOCS_DATA_VALIDATE] = $quotesList[$attrName];
                unset($quotesList[$attrName]);
            }

            // データ属性を削除
            $rawString = '';

            return;
        }

        $attrList[$attrName] = $attrValue;
    }

    // 属性値かをチェック
    private static function checkAttrName($attrName)
    {
        if (preg_match('/^'.BLOCS_ATTR_NAME_REGREX.'$/s', $attrName)) {
            return true;
        }

        return false;
    }

    // 変数かをチェック
    private static function checkAttrValue($attrName)
    {
        // $object->method()
        '()' === substr($attrName, -2) && $attrName = substr($attrName, 0, -2);
        $attrName = str_replace('()->', '', $attrName);

        return Common::checkValueName($attrName);
    }

    private static function deleteDataAttribute($attrName, $attrValue, $rawString)
    {
        return preg_replace('/\s+'.$attrName.'\s*=\s*'.preg_quote($attrValue).'([\s>\/]+)/si', '${1}', $rawString);
    }
}
