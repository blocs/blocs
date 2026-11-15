<?php

namespace Blocs\Compiler\Cache;

class Common
{
    // attributeの置き換え: ビルド済みタグへ属性を統合する
    public static function mergeAttribute($compiledTag, $attrName, $attrBuff, &$attrList, $noValue = false)
    {
        $attributePrefix = $noValue ? '' : '="';
        $attributeSuffix = $noValue ? '' : '"';

        if (isset($attrList[$attrName])) {
            $compiledTag = self::replaceExistingAttributeValue($compiledTag, $attrName, $attrBuff, $attrList[$attrName]);
        } else {
            $compiledTag = self::appendAttribute($compiledTag, $attrName, $attrBuff, $attributePrefix, $attributeSuffix);
        }

        $attrList[$attrName] = $attrBuff;

        return $compiledTag;
    }

    // 特定文字をエスケープ: ダブルクォート用の文字列を生成する
    public static function escapeDoubleQuote($str)
    {
        $escapedString = strtr($str, [
            '\\' => '\\\\',
            "\n" => '\n',
            "\r" => '\r',
            "\t" => '\t',
            '"' => '\"',
            '$' => '\$',
            "\v" => '\v',
            "\f" => '\f',
            "\e" => '\e',
        ]);

        return '"'.$escapedString.'"';
    }

    /**
     * スクリプトが endif で終わっていれば、endif を取り除いたスクリプトを返す（終わっていなければ null）
     */
    public static function stripEndif($script): ?string
    {
        return str_ends_with($script, BLOCS_ENDIF_SCRIPT) ? substr($script, 0, -strlen(BLOCS_ENDIF_SCRIPT)) : null;
    }

    /**
     * 文字列からクラス、メソッド、引数を取得する（data-convert）
     * 引数はPHPコードの断片（", 引数1, 引数2"）で返す
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function checkFunc($funcQuery)
    {
        [$class, $function, $argumentList] = self::splitFunc($funcQuery);

        return [$class, $function, implode('', array_map(fn ($argument) => ', '.$argument, $argumentList))];
    }

    /**
     * 文字列からクラス、メソッド、引数の値を取得する。
     * 引数をPHPコードとしてではなく値として扱いたい場合（data-filter）に使う。
     *
     * @return array{0: string, 1: string, 2: list<mixed>}
     */
    public static function checkFuncValues($funcQuery): array
    {
        [$class, $function, $argumentList] = self::splitFunc($funcQuery);

        return [$class, $function, array_map([self::class, 'literalValue'], $argumentList)];
    }

    /**
     * 「クラス::メソッド:引数1:引数2」をクラス、メソッド、引数（表記のまま）に分ける
     *
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private static function splitFunc($funcQuery): array
    {
        $class = '';
        $functionQuery = $funcQuery;

        if (str_contains($functionQuery, '::')) {
            [$class, $functionQuery] = explode('::', $functionQuery, 2);
        }

        if (! str_contains($functionQuery, ':')) {
            return [$class, $functionQuery, []];
        }

        [$function, $arguments] = explode(':', $functionQuery, 2);

        return [$class, $function, self::splitArguments($arguments)];
    }

    /**
     * 「:」区切りの引数を分割する（クォートの中の「:」では分割しない）
     *
     * @return list<string>
     */
    private static function splitArguments(string $arguments): array
    {
        $argumentFragments = preg_split('/([:"\'])/s', $arguments, -1, PREG_SPLIT_DELIM_CAPTURE);
        $quoteBuffer = '';
        $argumentList = [''];

        foreach ($argumentFragments as $fragment) {
            if ($fragment === "'" || $fragment === '"') {
                if ($quoteBuffer === '') {
                    $quoteBuffer = $fragment;
                } elseif ($quoteBuffer === $fragment) {
                    $quoteBuffer = '';
                }
            }

            if ($quoteBuffer === '' && $fragment === ':') {
                $argumentList[] = '';

                continue;
            }

            $argumentList[count($argumentList) - 1] .= $fragment;
        }

        return $argumentList;
    }

    /**
     * 引数のリテラル表記を値に変換する
     *
     * @return mixed
     */
    private static function literalValue(string $literal)
    {
        $literal = trim($literal);

        if (strlen($literal) >= 2 && ($literal[0] === '"' || $literal[0] === "'") && substr($literal, -1) === $literal[0]) {
            $inner = substr($literal, 1, -1);

            return $literal[0] === '"' ? stripcslashes($inner) : str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
        }

        if (is_numeric($literal)) {
            return $literal + 0;
        }

        return match (strtolower($literal)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $literal,
        };
    }

    /**
     * 閉じタグを持たないタグか（HTMLの空要素、または /> で閉じたタグ）
     */
    public static function isVoidTag($tagName, $rawTag): bool
    {
        $tagName = strtolower((string) $tagName);
        if ($tagName === '' || $tagName[0] === '/' || $tagName[0] === '!') {
            return false;
        }

        if (in_array($tagName, ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'], true)) {
            return true;
        }

        return substr(rtrim((string) $rawTag), -2) === '/>';
    }

    // 変数として使用できる文字列かチェック: data-assignの値がPHP変数として妥当か検証する
    // data-assignなどで指定された文字列が変数として使用できないとエラーが発生するため
    public static function checkValueName($valueName)
    {
        if (strncmp($valueName, '$', 1)) {
            return false;
        }

        $normalizedValueName = preg_replace('/\[[^\]]+\]/', '', $valueName);
        $normalizedValueName = str_replace('->', '', $normalizedValueName);

        // "$"だけでは変数名にならないため弾く
        if (! strlen(substr($normalizedValueName, 1))) {
            return false;
        }

        if (is_numeric(substr($normalizedValueName, 1, 1)) || preg_match('/[^a-zA-Z0-9\_]/', substr($normalizedValueName, 1))) {
            return false;
        }

        return true;
    }

    // フォーム名として使用できる文字列かチェック: フォーム名は変数として使用できる文字列でなければならない
    // フォーム名は変数として使用できる文字列でなければならない
    public static function checkFormName($valueName)
    {
        $normalizedValueName = substr($valueName, -2) === '[]' ? substr($valueName, 0, -2) : $valueName;

        // name="[]" のように中身が空になるものは変数名にできないので弾く
        if (! strlen($normalizedValueName)) {
            return false;
        }

        if (is_numeric(substr($normalizedValueName, 0, 1)) || preg_match('/[^a-zA-Z0-9\_]/', $normalizedValueName)) {
            return false;
        }

        return $normalizedValueName;
    }

    private static function buildConditionalAttributeValue($attributeBuffer, $attributePrefix, $attributeSuffix)
    {
        if (! trim($attributeBuffer)) {
            // data-valを指定しないケース
            return '';
        }

        $attributeParts = explode('<?php if', $attributeBuffer);
        if (preg_replace('/\s/', '', $attributeParts[0])) {
            return '';
        }

        $ifDepth = 0;
        $condition = "<?php \$preAttr='{$attributePrefix}'; \$postAttr=''; ?>\n".$attributeParts[0];
        array_shift($attributeParts);

        foreach ($attributeParts as $partBuffer) {
            $condition .= '<?php if';
            if ($ifDepth) {
                $condition .= $partBuffer;
                $partBuffer = explode(': ?>', $partBuffer, 2);
            } else {
                $partBuffer = explode(': ?>', $partBuffer, 2);
                $condition .= $partBuffer[0].": ?>\n<?php echo(\$preAttr); \$preAttr=''; \$postAttr='{$attributeSuffix}'; ?>".$partBuffer[1];
            }
            $ifDepth++;

            if (strpos($partBuffer[1], BLOCS_ENDIF_SCRIPT) !== false) {
                $endifBuffers = explode(BLOCS_ENDIF_SCRIPT, $partBuffer[1]);
                foreach ($endifBuffers as $endifBuffer) {
                    if (! $ifDepth && preg_replace('/\s/', '', $endifBuffer)) {
                        return '';
                    }

                    if ($ifDepth) {
                        $ifDepth--;
                    }
                }
            }
        }

        return $condition."<?php echo(\$postAttr); ?>\n";
    }

    private static function replaceExistingAttributeValue($compiledTag, $attributeName, &$attributeBuffer, $existingAttributeValue)
    {
        $conditionalBuffer = self::stripEndif($attributeBuffer);
        if ($conditionalBuffer !== null && ! str_contains($attributeBuffer, '<?php else: ?>')) {
            $attributeBuffer = $conditionalBuffer."<?php else: ?>\n".$existingAttributeValue.BLOCS_ENDIF_SCRIPT;
        }

        $pattern = '/(\s+'.$attributeName.'\s*=\s*["\']{0,1})'.str_replace('/', '\/', preg_quote($existingAttributeValue)).'((\[\]){0,1}["\']{0,1}[\s<>\/]+)/si';

        // 値に含まれる $1 や \1 が後方参照として解釈されないよう、コールバックで置き換える
        return preg_replace_callback($pattern, fn ($matchList) => $matchList[1].$attributeBuffer.$matchList[2], $compiledTag);
    }

    private static function appendAttribute($compiledTag, $attributeName, $attributeBuffer, $attributePrefix, $attributeSuffix)
    {
        $isSelfClosing = substr($compiledTag, -2) === '/>';
        $trimLength = $isSelfClosing ? 2 : 1;
        $close = $isSelfClosing ? ' />' : '>';

        $conditionalAttribute = self::buildConditionalAttributeValue($attributeBuffer, " {$attributeName}{$attributePrefix}", $attributeSuffix);
        if ($conditionalAttribute !== '') {
            return rtrim(substr($compiledTag, 0, -$trimLength)).$conditionalAttribute.$close;
        }

        return rtrim(substr($compiledTag, 0, -$trimLength))." {$attributeName}{$attributePrefix}{$attributeBuffer}{$attributeSuffix}".$close;
    }

    public static function extractAiClassNames($classAttr): array
    {
        $classList = [];
        foreach (preg_split("/\s/", $classAttr) as $className) {
            [$className] = preg_split("/\<\?php/", $className, 2);
            if (! strncmp($className, 'ai-', 3)) {
                $classList[] = substr($className, 3);
            }
        }

        return $classList;
    }

    public static function findConvertFunc($convertClass, $convertFunc)
    {
        return self::findDataFunc($convertClass, $convertFunc, '\Blocs\Data\Convert', 'B008', 'convert');
    }

    public static function findFilterFunc($filterClass, $filterFunc)
    {
        return self::findDataFunc($filterClass, $filterFunc, '\Blocs\Data\Filter', 'B010', 'filter');
    }

    private static function findDataFunc($class, $func, $defaultClass, $errorCode, $kind)
    {
        if ($class && method_exists($class, $func)) {
            return $class.'::'.$func;
        }
        if (method_exists($defaultClass, $func)) {
            return $defaultClass.'::'.$func;
        }
        if (function_exists($func)) {
            return $func;
        }

        throw new \RuntimeException($errorCode.': Can not find '.$kind.' function ('.$func.')');
    }
}
