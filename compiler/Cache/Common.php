<?php

namespace Blocs\Compiler\Cache;

class Common
{
    // attributeの置き換え: ビルド済みタグへ属性を統合する
    public static function mergeAttribute($compiledTag, $attrName, $attrBuff, &$attrList, $noValue = false)
    {
        // data-attributeと空白のdata-val時（値のない属性）の処理
        [$attributePrefix, $attributeSuffix] = self::buildAttributeWrapper((bool) $noValue);

        $attributeName = $attrName;
        $attributeBuffer = $attrBuff;
        $attributeList = &$attrList;

        if (isset($attributeList[$attributeName])) {
            $compiledTag = self::replaceExistingAttributeValue($compiledTag, $attributeName, $attributeBuffer, $attributeList[$attributeName]);
        } elseif (self::isSelfClosingTag($compiledTag)) {
            $compiledTag = self::appendAttributeToSelfClosingTag($compiledTag, $attributeName, $attributeBuffer, $attributePrefix, $attributeSuffix);
        } else {
            $compiledTag = self::appendAttributeToStandardTag($compiledTag, $attributeName, $attributeBuffer, $attributePrefix, $attributeSuffix);
        }

        $attributeList[$attributeName] = $attributeBuffer;

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
            "\v" => '\v',
            "\f" => '\f',
            "\e" => '\e',
        ]);

        return '"'.$escapedString.'"';
    }

    // 文字列からクラス、メソッド、引数を取得: data-call指定をパースする
    public static function checkFunc($funcQuery)
    {
        $class = '';
        $functionQuery = $funcQuery;

        if (strpos($functionQuery, '::') !== false) {
            [$class, $functionQuery] = explode('::', $functionQuery, 2);
        }

        if (strpos($functionQuery, ':') === false) {
            $function = $functionQuery;
            $arguments = '';
        } else {
            [$function, $arguments] = explode(':', $functionQuery, 2);
            $argumentFragments = preg_split('/([:"\'])/s', $arguments, -1, PREG_SPLIT_DELIM_CAPTURE);
            $quoteBuffer = '';
            $resultBuffer = '';

            foreach ($argumentFragments as $fragment) {
                if ($fragment === "'" || $fragment === '"') {
                    if ($quoteBuffer === '') {
                        $quoteBuffer = $fragment;
                    } elseif ($quoteBuffer === $fragment) {
                        $quoteBuffer = '';
                    }
                }

                $resultBuffer .= ($quoteBuffer === '' && $fragment === ':') ? ', ' : $fragment;
            }

            $arguments = ', '.$resultBuffer;
        }

        return [$class, $function, $arguments];
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

    private static function buildAttributeWrapper(bool $isValueLess)
    {
        if ($isValueLess) {
            return ['', ''];
        }

        return ['="', '"'];
    }

    private static function replaceExistingAttributeValue($compiledTag, $attributeName, &$attributeBuffer, $existingAttributeValue)
    {
        if (substr($attributeBuffer, -16) === BLOCS_ENDIF_SCRIPT && strpos($attributeBuffer, '<?php else: ?>') === false) {
            $attributeBuffer = substr($attributeBuffer, 0, -16)."<?php else: ?>\n".$existingAttributeValue.BLOCS_ENDIF_SCRIPT;
        }

        $pattern = '/(\s+'.$attributeName.'\s*=\s*["\']{0,1})'.str_replace('/', '\/', preg_quote($existingAttributeValue)).'((\[\]){0,1}["\']{0,1}[\s<>\/]+)/si';

        return preg_replace($pattern, '${1}'.$attributeBuffer.'${2}', $compiledTag);
    }

    private static function isSelfClosingTag($compiledTag)
    {
        return substr($compiledTag, -2) === '/>';
    }

    private static function appendAttributeToSelfClosingTag($compiledTag, $attributeName, $attributeBuffer, $attributePrefix, $attributeSuffix)
    {
        $conditionalAttribute = self::buildConditionalAttributeValue($attributeBuffer, " {$attributeName}{$attributePrefix}", $attributeSuffix);

        if ($conditionalAttribute !== '') {
            return rtrim(substr($compiledTag, 0, -2))."{$conditionalAttribute} />";
        }

        return rtrim(substr($compiledTag, 0, -2))." {$attributeName}{$attributePrefix}{$attributeBuffer}{$attributeSuffix} />";
    }

    private static function appendAttributeToStandardTag($compiledTag, $attributeName, $attributeBuffer, $attributePrefix, $attributeSuffix)
    {
        $conditionalAttribute = self::buildConditionalAttributeValue($attributeBuffer, " {$attributeName}{$attributePrefix}", $attributeSuffix);

        if ($conditionalAttribute !== '') {
            return rtrim(substr($compiledTag, 0, -1))."{$conditionalAttribute}>";
        }

        return rtrim(substr($compiledTag, 0, -1))." {$attributeName}{$attributePrefix}{$attributeBuffer}{$attributeSuffix}>";
    }

    public static function findConvertFunc($convertClass, $convertFunc)
    {
        if ($convertClass && method_exists($convertClass, $convertFunc)) {
            return $convertClass.'::'.$convertFunc;
        }
        if (method_exists('\Blocs\Data\Convert', $convertFunc)) {
            return '\Blocs\Data\Convert::'.$convertFunc;
        }
        if (function_exists($convertFunc)) {
            return $convertFunc;
        }

        trigger_error('B008: Can not find convert function ('.$convertFunc.')', E_USER_ERROR);
    }
}
