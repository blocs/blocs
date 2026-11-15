<?php

namespace Blocs\Compiler\Cache;

class Val
{
    // data-val属性に対応するPHPスクリプトを生成する
    public static function val($attrList, $quotesList, &$dataAttribute, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $outputBuffer = '';

        isset($quotesList[BLOCS_DATA_VAL]) || $quotesList[BLOCS_DATA_VAL] = '';
        $dataVal = $quotesList[BLOCS_DATA_VAL].$attrList[BLOCS_DATA_VAL].$quotesList[BLOCS_DATA_VAL];

        $hasDataAttribute = isset($attrList[BLOCS_DATA_ATTRIBUTE]);
        $usesAssignment = ! empty($attrList[BLOCS_DATA_ASSIGN]);
        $isNamedValue = Common::checkValueName($attrList[BLOCS_DATA_VAL]);

        if ($isNamedValue) {
            $condition = "isset({$attrList[BLOCS_DATA_VAL]}) && !is_object({$attrList[BLOCS_DATA_VAL]}) && !is_array({$attrList[BLOCS_DATA_VAL]})";
            $hasDataAttribute || $condition .= " && strlen({$attrList[BLOCS_DATA_VAL]})";
            $outputBuffer .= "<?php if({$condition}): ?>\n";
        } elseif (! $hasDataAttribute) {
            $outputBuffer .= "<?php if(strlen({$dataVal})): ?>\n";
        }

        if ($usesAssignment) {
            Common::checkValueName($attrList[BLOCS_DATA_ASSIGN]) || throw new \RuntimeException('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')');

            $outputBuffer .= "<?php \$dataVal = ''; ?>\n";
        }

        $outputBuffer = self::appendFixedValueSegment($attrList, $quotesList, $outputBuffer, BLOCS_DATA_PREFIX);

        if (! $usesAssignment) {
            $outputBuffer .= '<?php echo(';
        } else {
            $outputBuffer .= '<?php $dataVal .= ';
        }

        if (isset($attrList[BLOCS_DATA_CONVERT]) && $attrList[BLOCS_DATA_CONVERT] === 'raw') {
            unset($attrList[BLOCS_DATA_CONVERT]);
        } elseif (! $usesAssignment && (! isset($attrList[BLOCS_DATA_CONVERT]) || (strncmp($attrList[BLOCS_DATA_CONVERT], 'raw_', 4) && strpos($attrList[BLOCS_DATA_CONVERT], '::raw_') === false))) {
            $outputBuffer .= '\Blocs\Common::convertDefault(';
            $menuNameSuffix = self::resolveMenuNameSuffix($attrList[BLOCS_DATA_VAL]);
            strlen($menuNameSuffix) || $menuNameSuffix = ', null';

            // 属性値では改行を <br /> にしない。選択肢は表示中のテンプレートのものを使う
            $postConvertSuffix = $menuNameSuffix.', '.($hasDataAttribute ? 'false' : 'true').', ($__blocsPath ?? null))';
        }

        if (isset($attrList[BLOCS_DATA_CONVERT])) {
            [$convertClass, $convertFunc, $convertArg] = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
            $convertFunc = Common::findConvertFunc($convertClass, $convertFunc);

            $outputBuffer .= $convertFunc.'('.$dataVal.$convertArg.')';
        } else {
            $outputBuffer .= $dataVal;
        }

        isset($postConvertSuffix) && $outputBuffer .= $postConvertSuffix;

        if (! $usesAssignment) {
            $outputBuffer .= "); ?>\n";
        } else {
            $outputBuffer .= "; ?>\n";
        }

        $outputBuffer = self::appendFixedValueSegment($attrList, $quotesList, $outputBuffer, BLOCS_DATA_POSTFIX);

        if ($usesAssignment) {
            $outputBuffer .= "<?php {$attrList[BLOCS_DATA_ASSIGN]} = \$dataVal; ?>\n";
        }

        if ($isNamedValue || ! $hasDataAttribute) {
            $outputBuffer .= BLOCS_ENDIF_SCRIPT;
        }

        return self::deliverOutput($outputBuffer, $attrList, $dataAttribute, $tagName, $tagCounter, $htmlArray);
    }

    // data-notice属性に対応するPHPスクリプトを生成する
    public static function notice($attrList, &$dataAttribute, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        // 先頭2つ（カテゴリ:コード）と引数を分けて渡す
        // 引数を「:」で連結し直すと、値に「:」を含む変数（時刻やURL）が別の引数に分かれてしまう
        $messageCodeList = [];
        $messageArgList = [];
        foreach (explode(':', $attrList[BLOCS_DATA_NOTICE]) as $index => $notice) {
            $expression = Common::checkValueName($notice) ? $notice : var_export($notice, true);

            if ($index < 2) {
                $messageCodeList[] = $expression;
            } else {
                $messageArgList[] = $expression;
            }
        }

        $outputBuffer = '';
        $usesAssignment = ! empty($attrList[BLOCS_DATA_ASSIGN]);

        if (! $usesAssignment) {
            $outputBuffer .= '<?php echo(';
            if (! (isset($attrList[BLOCS_DATA_CONVERT]) && $attrList[BLOCS_DATA_CONVERT] === 'raw')) {
                $outputBuffer .= '\Blocs\Common::convertDefault(';
            }
        } else {
            if (! Common::checkValueName($attrList[BLOCS_DATA_ASSIGN])) {
                throw new \RuntimeException('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')');
            }

            $outputBuffer .= "<?php {$attrList[BLOCS_DATA_ASSIGN]} = ";
        }

        $outputBuffer .= '\Blocs\Lang::get('.implode(" . ':' . ", $messageCodeList);
        empty($messageArgList) || $outputBuffer .= ', ['.implode(', ', $messageArgList).']';
        $outputBuffer .= ')';

        if (! $usesAssignment) {
            if (! (isset($attrList[BLOCS_DATA_CONVERT]) && $attrList[BLOCS_DATA_CONVERT] === 'raw')) {
                // 属性値では改行を <br /> にしない
                $outputBuffer .= isset($attrList[BLOCS_DATA_ATTRIBUTE]) ? ', null, false)' : ')';
            }
            $outputBuffer .= "); ?>\n";
        } else {
            $outputBuffer .= "; ?>\n";
        }

        return self::deliverOutput($outputBuffer, $attrList, $dataAttribute, $tagName, $tagCounter, $htmlArray);
    }

    /**
     * 生成したスクリプトの出力先を決める
     *
     * - data-attribute があれば、属性値としてタグに反映させる（$dataAttribute に積む）
     * - タグ記法なら、タグの中身と置き換える（$tagCounter と $htmlArray で指示する）
     * - コメント記法なら、その場に出力するスクリプトを返す
     */
    private static function deliverOutput($outputBuffer, $attrList, &$dataAttribute, $tagName, &$tagCounter, &$htmlArray)
    {
        if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
            $dataAttribute[] = [
                'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                'value' => $outputBuffer,
            ];

            return '';
        }

        if (! $tagName) {
            return $outputBuffer;
        }

        $conditionalBuffer = Common::stripEndif($outputBuffer);
        if ($conditionalBuffer === null) {
            // 常に値を出力するので、タグの中身は捨てる
            $tagCounter = [
                'tag' => $tagName,
                'before' => $outputBuffer,
                'type' => 'ignore',
            ];
        } else {
            // 値がなければタグの中身を出力する
            array_unshift($htmlArray, $conditionalBuffer."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tagName,
                'before' => BLOCS_ENDIF_SCRIPT,
            ];
        }

        return '';
    }

    private static function appendFixedValueSegment($attrList, $quotesList, $outputBuffer, $attrName)
    {
        if (! (isset($attrList[$attrName]))) {
            return $outputBuffer;
        }

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            if (empty($quotesList[$attrName])) {
                // 変数パターンの固定値を追加
                return $outputBuffer.'<?php echo(htmlspecialchars((string)('.$attrList[$attrName]."), ENT_QUOTES, 'UTF-8')); ?>\n";
            } else {
                return $outputBuffer.$attrList[$attrName];
            }
        }

        empty($quotesList[$attrName]) && $quotesList[$attrName] = '';
        $outputBuffer .= "<?php \$dataVal .= {$quotesList[$attrName]}{$attrList[$attrName]}{$quotesList[$attrName]}; ?>\n";

        return $outputBuffer;
    }

    private static function resolveMenuNameSuffix($dataVal)
    {
        if (! Common::checkValueName($dataVal)) {
            return '';
        }

        $propertySegments = explode('->', $dataVal, 2);
        if (count($propertySegments) > 1) {
            $dataVal = '$'.$propertySegments[1];
        }

        $valueSegments = explode('[', $dataVal);
        if (count($valueSegments) === 1) {
            return ', \''.substr($dataVal, 1).'\'';
        }

        $lastSegment = end($valueSegments);
        if (! strncmp($lastSegment, "'", 1) || ! strncmp($lastSegment, '"', 1)) {
            return ', '.substr($lastSegment, 0, -1);
        } else {
            return ', \''.substr($lastSegment, 0, -1).'\'';
        }
    }
}
