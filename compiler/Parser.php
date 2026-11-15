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

    // HTMLをタグ単位で分割して解析用の配列へ整形
    public static function parse($htmlString, $commentParse = false)
    {
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

        // 中身をHTMLとして解析しないタグ（script・style）の中にいる間、そのタグ名
        $rawTextTag = '';

        foreach ($htmlList as $htmlNum => $htmlBuff) {
            $htmlBuff = self::unmaskOperators($htmlBuff);

            if ($htmlBuff === '<' && isset($htmlList[$htmlNum + 1])) {
                $nextHtmlBuff = $htmlList[$htmlNum + 1];

                if (! strncmp($nextHtmlBuff, '?', 1)) {
                    $isPhp = true;
                }

                if (! strlen($tagName) && empty($isQuote) && ! $isPhp) {
                    $isTagStart = preg_match('/^('.BLOCS_TAG_NAME_REGEX.')/s', $nextHtmlBuff, $matchList) || preg_match('/^(\/\s*'.BLOCS_TAG_NAME_REGEX.')/s', $nextHtmlBuff, $matchList);

                    // script・styleの中身はHTMLではないので、コメント記法と閉じタグだけをタグとして扱う
                    // （a<b のような比較演算子をタグの開始と誤認しない）
                    if ($isTagStart && $rawTextTag !== '') {
                        $candidateName = preg_replace('/\s/', '', strtolower($matchList[0]));
                        $isTagStart = $candidateName === '!--' || $candidateName === '/'.$rawTextTag;
                    }

                    if ($isTagStart) {
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
                    $attrValueList = self::splitAttributeString($attrString, $tagName);

                    self::appendAttributeEntry($attrList, $quotesList, $rawString, $parsedHtml, $attrName, $attrValueList, $commentParse);

                    array_push($parsedHtml, [
                        'raw' => self::applyAttributeAliases($rawString),
                        'tag' => $tagName,
                        'attribute' => $attrList,
                        'quotes' => $quotesList,
                    ]);
                }

                // script・styleの開始と終了を追跡する
                $normalizedTagName = preg_replace('/\s/', '', $tagName);
                if (self::isRawTextTag($normalizedTagName, $attrList) && substr($rawString, -2) !== '/>') {
                    $rawTextTag = $normalizedTagName;
                } elseif ($normalizedTagName === '/'.$rawTextTag) {
                    $rawTextTag = '';
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
                $attrValueList = self::splitAttributeString($attrString, $tagName);

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

    /**
     * 中身をHTMLとして解析しないタグか。
     * styleと、JavaScript・JSONを書くscriptが対象。type="text/template" のようにHTMLの雛形を書くscriptは、
     * 中のタグでもデータ属性を使えるよう従来どおり解析する。
     */
    private static function isRawTextTag($tagName, $attrList): bool
    {
        if ($tagName === 'style') {
            return true;
        }

        if ($tagName !== 'script') {
            return false;
        }

        $type = strtolower(trim((string) ($attrList['type'] ?? '')));

        return $type === '' || $type === 'module' || $type === 'importmap'
            || (bool) preg_match('/^(text|application)\/(javascript|ecmascript|x-javascript|json|ld\+json)$/', $type);
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

    /**
     * タグ名を取り除いた属性部分を、空白区切りのトークン（空白も含む）に分割する
     *
     * @return list<string>
     */
    private static function splitAttributeString($attrString, $tagName)
    {
        str_starts_with($attrString, '<'.$tagName) && $attrString = substr($attrString, strlen('<'.$tagName));
        $attrString = self::applyAttributeAliases($attrString);

        return preg_split("/(\s)/", trim($attrString), -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    private static function applyAttributeAliases($rawString)
    {
        // エイリアス名を本来の属性名へ変換
        // data-language のように、エイリアス名で始まるだけの属性は書き換えない
        foreach (self::$attributeAliasMap as $aliasName => $attrName) {
            if (strpos($rawString, $aliasName) === false) {
                continue;
            }

            $rawString = preg_replace('/(?<![\w\-])'.preg_quote($aliasName, '/').'(?![\w\-])/', $attrName, $rawString);
        }

        return $rawString;
    }
}
