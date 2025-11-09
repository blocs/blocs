<?php

namespace Blocs\Compiler;

class Parser
{
    use ParserTrait;

    // データ属性名のエイリアス定義
    private static array $attributeAliasMap = [
        BLOCS_DATA_LANG => BLOCS_DATA_NOTICE,
        BLOCS_DATA_REPEAT => BLOCS_DATA_LOOP,
        BLOCS_DATA_ENDREPEAT => BLOCS_DATA_ENDLOOP,
    ];

    private static array $operatorMaskList = [
        '->',
        '=>',
    ];

    private static $htmlString;

    // HTMLをタグ単位で分割して解析用の配列へ整形
    public static function parse($htmlString, $commentParse = false)
    {
        self::$htmlString = $htmlString;
        $htmlString = self::maskOperators($htmlString);
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
            $htmlBuff = self::unmaskOperators($htmlBuff);

            if ($htmlBuff === '<' && isset($htmlList[$htmlNum + 1])) {
                $nextHtmlBuff = $htmlList[$htmlNum + 1];

                if (! strncmp($nextHtmlBuff, '?', 1)) {
                    $isPhp = true;
                }

                if (! strlen($tagName) && empty($isQuote) && ! $isPhp) {
                    if (preg_match('/^('.BLOCS_TAG_NAME_REGREX.')/s', $nextHtmlBuff, $matchList) || preg_match('/^(\/\s*'.BLOCS_TAG_NAME_REGREX.')/s', $nextHtmlBuff, $matchList)) {
                        // タグ解析を開始
                        $tagName = strtolower($matchList[0]);

                        // テキストを解析結果へ格納
                        strlen($rawString) && $parsedHtml[] = $rawString;
                        $rawString = '';
                    }
                }
            }

            if ($htmlBuff === '>' && isset($htmlList[$htmlNum - 1]) && substr($htmlList[$htmlNum - 1], -1) === '?') {
                $isPhp = false;
            }

            strlen($htmlBuff) && $rawString .= $htmlBuff;

            if (! strlen($tagName) || $isPhp) {
                // タグ解析の対象外
                continue;
            }

            if ($htmlBuff === '>' && empty($isQuote)) {
                if ($tagName === '!--' && ! $commentParse) {
                    // コメントは解析対象外
                    strlen($rawString) && $parsedHtml[] = $rawString;
                } else {
                    $attrString = self::removeTagNamePrefix($attrString, $tagName);
                    $attrString = self::applyAttributeAliases($attrString);
                    $attrValueList = preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);

                    self::appendAttributeEntry($attrList, $quotesList, $rawString, $parsedHtml, $attrName, $attrValueList, $commentParse);

                    array_push($parsedHtml, [
                        'raw' => self::applyAttributeAliases($rawString),
                        'tag' => $tagName,
                        'attribute' => $attrList,
                        'quotes' => $quotesList,
                    ]);
                }

                // タグ解析を終了
                $rawString = '';
                $tagName = '';
                $attrList = [];
                $quotesList = [];

                $attrName = '';
                $attrString = '';

                $isQuote = '';

                continue;
            }

            if ($htmlBuff === '=' && empty($isQuote) && ! ($tagName === '!--' && ! $commentParse)) {
                $attrString = self::removeTagNamePrefix($attrString, $tagName);
                $attrString = self::applyAttributeAliases($attrString);
                $attrValueList = preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);

                $nextAttrName = array_pop($attrValueList);

                self::appendAttributeEntry($attrList, $quotesList, $rawString, $parsedHtml, $attrName, $attrValueList, $commentParse);

                // '=' の直前を次の属性名として扱う
                $attrName = self::applyAttributeAliases($nextAttrName);
                $attrString = '';

                continue;
            }

            if ($htmlBuff === '"' || $htmlBuff === "'") {
                if (! empty($isQuote)) {
                    if ($isQuote === $htmlBuff) {
                        // クォートを無効化
                        strlen($attrName) && $quotesList[$attrName] = $isQuote;
                        $isQuote = '';
                    }
                } else {
                    if (isset($htmlList[$htmlNum - 1]) && ! trim($htmlList[$htmlNum - 1]) && isset($htmlList[$htmlNum - 2]) && $htmlList[$htmlNum - 2] === '=') {
                        // クォートを有効化
                        $isQuote = $htmlBuff;
                    }
                }
            }

            $attrString .= $htmlBuff;
        }

        strlen($rawString) && $parsedHtml[] = $rawString;

        return $parsedHtml;
    }

    private static function maskOperators($htmlString)
    {
        $htmlString = str_replace('-->', 'REPLACE_TO_COMMENT_OPERATOR', $htmlString);

        foreach (self::$operatorMaskList as $num => $escapeOperator) {
            $htmlString = str_replace($escapeOperator, "REPLACE_TO_OPERATOR_{$num}", $htmlString);
        }

        $htmlString = str_replace('REPLACE_TO_COMMENT_OPERATOR', '-->', $htmlString);

        return $htmlString;
    }

    private static function unmaskOperators($htmlString)
    {
        foreach (self::$operatorMaskList as $num => $escapeOperator) {
            $htmlString = str_replace("REPLACE_TO_OPERATOR_{$num}", $escapeOperator, $htmlString);
        }

        return $htmlString;
    }

    private static function removeTagNamePrefix($attrString, $tagName)
    {
        strncmp($attrString, '<'.$tagName, strlen('<'.$tagName)) || $attrString = substr($attrString, strlen('<'.$tagName));

        return $attrString;
    }

    private static function applyAttributeAliases($rawString)
    {
        // エイリアス名を本来の属性名へ変換
        foreach (self::$attributeAliasMap as $aliasName => $attrName) {
            $rawString = str_replace($aliasName, $attrName, $rawString);
        }

        return $rawString;
    }
}
