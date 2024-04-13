<?php

namespace Blocs\Compiler;

class Parser
{
    // データ属性名のエイリアス
    private static array $aliasAttrName = [
        BLOCS_DATA_LANG => BLOCS_DATA_NOTICE,
        BLOCS_DATA_ASSIGN => BLOCS_DATA_QUERY,
        BLOCS_DATA_REPEAT => BLOCS_DATA_LOOP,
        BLOCS_DATA_ENDREPEAT => BLOCS_DATA_ENDLOOP,
    ];

    private static array $escapeOperatorList = [
        '->',
        '=>',
    ];

    private static $htmlString;

    // HTMLをタグ単位で配列に分解
    public static function parse($htmlString, $commentParse = false)
    {
        self::$htmlString = $htmlString;
        $htmlString = self::escepeOperator($htmlString);
        $htmlList = preg_split('/([<>="\'])/s', $htmlString, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parsedHtml = [];
        $rawString = '';
        $attrString = '';

        $tagName = '';
        $attrName = '';
        $attrList = [];
        $quotesList = [];

        $isPhp = false;
        $isQuote = '';

        foreach ($htmlList as $htmlNum => $htmlBuff) {
            $htmlBuff = self::backOperator($htmlBuff);

            if ('<' === $htmlBuff && isset($htmlList[$htmlNum + 1])) {
                $nextHtmlBuff = $htmlList[$htmlNum + 1];

                if (!strncmp($nextHtmlBuff, '?', 1)) {
                    $isPhp = true;
                }

                if (!strlen($tagName) && empty($isQuote) && !$isPhp) {
                    if (preg_match('/^('.BLOCS_TAG_NAME_REGREX.')/s', $nextHtmlBuff, $matchList) || preg_match('/^(\/\s*'.BLOCS_TAG_NAME_REGREX.')/s', $nextHtmlBuff, $matchList)) {
                        // タグ処理に切替
                        $tagName = strtolower($matchList[0]);

                        // テキストを格納
                        strlen($rawString) && $parsedHtml[] = $rawString;
                        $rawString = '';
                    }
                }
            }

            if ('>' === $htmlBuff && isset($htmlList[$htmlNum - 1]) && '?' === substr($htmlList[$htmlNum - 1], -1)) {
                $isPhp = false;
            }

            strlen($htmlBuff) && $rawString .= $htmlBuff;

            if (!strlen($tagName) || $isPhp) {
                // タグ処理でない
                continue;
            }

            if ('>' === $htmlBuff && empty($isQuote)) {
                if ('!--' === $tagName && !$commentParse) {
                    // コメントをパースしない
                    strlen($rawString) && $parsedHtml[] = $rawString;
                } else {
                    $attrString = self::deleteTagName($attrString, $tagName);
                    self::addAttrList($attrList, $quotesList, $rawString, $attrName, $attrString);

                    // "", ''で囲われていない属性
                    foreach ($attrList as $attrName => $attrValue) {
                        if (empty($quotesList[$attrName])) {
                            continue;
                        }
                        if (substr($attrValue, 0, 1) !== $quotesList[$attrName] || substr($attrValue, -1) !== $quotesList[$attrName]) {
                            unset($quotesList[$attrName]);
                            continue;
                        }

                        $attrList[$attrName] = substr($attrValue, 1, -1);
                    }

                    array_push($parsedHtml, [
                        'raw' => self::replaceAliasAttrName($rawString),
                        'tag' => $tagName,
                        'attribute' => $attrList,
                        'quotes' => $quotesList,
                    ]);
                }

                $rawString = '';
                $attrString = '';

                // タグ処理を解除
                $tagName = '';
                $attrName = '';
                $attrList = [];
                $quotesList = [];

                $isQuote = '';

                continue;
            }

            if ('=' === $htmlBuff && empty($isQuote) && !('!--' === $tagName && !$commentParse)) {
                $attrString = self::deleteTagName($attrString, $tagName);
                self::addAttrList($attrList, $quotesList, $rawString, $attrName, $attrString);

                // =の前は次の属性名とする
                $attrValueList = array_filter(preg_split("/\s/", $attrString), 'strlen');
                $nextAttrName = end($attrValueList);
                if (count($attrValueList) && preg_match('/^'.BLOCS_ATTR_NAME_REGREX.'$/s', $nextAttrName)) {
                    $attrName = self::replaceAliasAttrName($nextAttrName);
                } else {
                    $attrName = '';
                }

                $attrString = '';

                continue;
            }

            if (('"' === $htmlBuff || "'" === $htmlBuff) && strlen($attrName)) {
                if (!empty($isQuote)) {
                    if ($isQuote === $htmlBuff) {
                        // quoteを無効にする
                        $quotesList[$attrName] = $isQuote;
                        $isQuote = '';
                    }
                } else {
                    if (isset($htmlList[$htmlNum - 1]) && !trim($htmlList[$htmlNum - 1]) && isset($htmlList[$htmlNum - 2]) && '=' === $htmlList[$htmlNum - 2]) {
                        // quoteを有効にする
                        $isQuote = $htmlBuff;
                    }
                }
            }

            $attrString .= $htmlBuff;
        }

        strlen($rawString) && $parsedHtml[] = $rawString;

        return $parsedHtml;
    }

    private static function addAttrList(&$attrList, &$quotesList, &$rawString, $attrName, $attrString)
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

        if (!strncmp($attrName, ':', 1)) {
            // data-attributeの省略表記
            $attrList[BLOCS_DATA_ATTRIBUTE] = '"'.substr($attrName, 1).'"';
            $attrList[BLOCS_DATA_VAL] = $attrValue;
            unset($attrList[$attrName]);

            $quotesList[BLOCS_DATA_ATTRIBUTE] = '"';
            if (isset($quotesList[$attrName])) {
                $quotesList[BLOCS_DATA_VAL] = $quotesList[$attrName];
                unset($quotesList[$attrName]);
            }

            // 属性名を置換して削除
            $rawString = str_replace($attrName, BLOCS_DATA_VAL, $rawString);

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
