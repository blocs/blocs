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
            Common::checkValueName($attrList[BLOCS_DATA_ASSIGN]) || trigger_error('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')', E_USER_ERROR);

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
            $postConvertSuffix = self::resolveMenuNameSuffix($attrList[BLOCS_DATA_VAL]).')';
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

        if ($hasDataAttribute) {
            // data-attributeが設定されている場合の出力専用処理
            $dataAttribute[] = [
                'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                'value' => $outputBuffer,
            ];

            return '';
        }

        // data-attributeが設定されていない場合のタグ計数処理
        if ($tagName) {
            return self::updateTagCounterState($outputBuffer, $attrList, $tagName, $tagCounter, $htmlArray);
        }

        return $outputBuffer;
    }

    // data-notice属性に対応するPHPスクリプトを生成する
    public static function notice($attrList, $quotesList, &$dataAttribute, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $noticeList = explode(':', $attrList[BLOCS_DATA_NOTICE]);

        $noticeArgList = [];
        foreach ($noticeList as $notice) {
            if (Common::checkValueName($notice)) {
                $noticeArgList[] = $notice;
            } else {
                $notice = str_replace("'", "\\'", $notice);
                $noticeArgList[] = "'{$notice}'";
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
                trigger_error('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')', E_USER_ERROR);
            }

            $outputBuffer .= "<?php {$attrList[BLOCS_DATA_ASSIGN]} = ";
        }

        $outputBuffer .= '\Blocs\Lang::get("'.$attrList[BLOCS_DATA_NOTICE].'")';

        if (! $usesAssignment) {
            if (! (isset($attrList[BLOCS_DATA_CONVERT]) && $attrList[BLOCS_DATA_CONVERT] === 'raw')) {
                $outputBuffer .= ')';
            }
            $outputBuffer .= "); ?>\n";
        } else {
            $outputBuffer .= "; ?>\n";
        }

        // data-attributeが設定されている場合の出力専用処理
        if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
            $dataAttribute[] = [
                'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                'value' => $outputBuffer,
            ];
            $outputBuffer = '';
        }

        // data-attributeが設定されていない場合のタグ計数処理
        if ($tagName) {
            $outputBuffer = self::updateTagCounterState($outputBuffer, $attrList, $tagName, $tagCounter, $htmlArray);
        }

        return $outputBuffer;
    }

    private static function appendFixedValueSegment($attrList, $quotesList, $outputBuffer, $attrName)
    {
        if (! (isset($attrList[$attrName]))) {
            return $outputBuffer;
        }

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            if (empty($quotesList[$attrName])) {
                // 変数パターンの固定値を追加
                return $outputBuffer."<?php echo({$attrList[$attrName]}); ?>\n";
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

    private static function updateTagCounterState($outputBuffer, $attrList, $tagName, &$tagCounter, &$htmlArray)
    {
        if (substr($outputBuffer, -16) !== BLOCS_ENDIF_SCRIPT) {
            $tagCounter = [
                'tag' => $tagName,
                'before' => $outputBuffer,
                'type' => 'ignore',
            ];
        } else {
            // 次回のタグ処理の先頭に入れる
            array_unshift($htmlArray, substr($outputBuffer, 0, -16)."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tagName,
                'before' => BLOCS_ENDIF_SCRIPT,
            ];
        }

        return '';
    }
}
