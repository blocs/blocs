<?php

namespace Blocs\Compiler;

class Parser
{
    use ParserTrait;

    // データ属性名のエイリアス
    private static array $aliasAttrName = [
        BLOCS_DATA_LANG => BLOCS_DATA_NOTICE,
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
        $tagName = '';
        $attrList = [];
        $quotesList = [];

        $attrName = '';
        $attrString = '';

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
                        // タグ処理を開始
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
                    $attrString = self::replaceAliasAttrName($attrString);
                    $attrValueList = preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);

                    self::addAttrList($attrList, $quotesList, $rawString, $parsedHtml, $attrName, $attrValueList, $commentParse);

                    array_push($parsedHtml, [
                        'raw' => self::replaceAliasAttrName($rawString),
                        'tag' => $tagName,
                        'attribute' => $attrList,
                        'quotes' => $quotesList,
                    ]);
                }

                // タグ処理を終了
                $rawString = '';
                $tagName = '';
                $attrList = [];
                $quotesList = [];

                $attrName = '';
                $attrString = '';

                $isQuote = '';

                continue;
            }

            if ('=' === $htmlBuff && empty($isQuote) && !('!--' === $tagName && !$commentParse)) {
                $attrString = self::deleteTagName($attrString, $tagName);
                $attrString = self::replaceAliasAttrName($attrString);
                $attrValueList = preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);

                $nextAttrName = array_pop($attrValueList);

                self::addAttrList($attrList, $quotesList, $rawString, $parsedHtml, $attrName, $attrValueList, $commentParse);

                // =の前は次の属性名とする
                $attrName = self::replaceAliasAttrName($nextAttrName);
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

    private static function replaceAliasAttrName($rawString)
    {
        // エイリアス名を変換
        foreach (self::$aliasAttrName as $aliasName => $attrName) {
            $rawString = str_replace($aliasName, $attrName, $rawString);
        }

        return $rawString;
    }
}
