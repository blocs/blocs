<?php

namespace Blocs\Compiler\Cache;

class Val
{
    // data-valのスクリプトを生成
    public static function val($attrList, $quotesList, &$dataAttribute, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $resultBuff = '';

        isset($quotesList[BLOCS_DATA_VAL]) || $quotesList[BLOCS_DATA_VAL] = '';
        $dataVal = $quotesList[BLOCS_DATA_VAL].$attrList[BLOCS_DATA_VAL].$quotesList[BLOCS_DATA_VAL];

        if (Common::checkValueName($attrList[BLOCS_DATA_VAL])) {
            if (!isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
                $resultBuff .= "<?php if(isset({$attrList[BLOCS_DATA_VAL]}) && !is_object({$attrList[BLOCS_DATA_VAL]}) && !is_array({$attrList[BLOCS_DATA_VAL]}) && strlen({$attrList[BLOCS_DATA_VAL]})): ?>\n";
            } else {
                $resultBuff .= "<?php if(isset({$attrList[BLOCS_DATA_VAL]}) && !is_object({$attrList[BLOCS_DATA_VAL]}) && !is_array({$attrList[BLOCS_DATA_VAL]})): ?>\n";
            }
        } else {
            if (!isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
                $resultBuff .= "<?php if(strlen({$dataVal})): ?>\n";
            }
        }

        if (!empty($attrList[BLOCS_DATA_ASSIGN])) {
            Common::checkValueName($attrList[BLOCS_DATA_ASSIGN]) || trigger_error('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')', E_USER_ERROR);

            $resultBuff .= "<?php \$dataVal = ''; ?>\n";
        }

        $resultBuff = self::addFixValue($attrList, $quotesList, $resultBuff, BLOCS_DATA_PREFIX);

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            $resultBuff .= '<?php echo(';
        } else {
            $resultBuff .= '<?php $dataVal .= ';
        }

        if (isset($attrList[BLOCS_DATA_CONVERT]) && 'raw' === $attrList[BLOCS_DATA_CONVERT]) {
            unset($attrList[BLOCS_DATA_CONVERT]);
        } elseif (empty($attrList[BLOCS_DATA_ASSIGN]) && (!isset($attrList[BLOCS_DATA_CONVERT]) || (strncmp($attrList[BLOCS_DATA_CONVERT], 'raw_', 4) && false === strpos($attrList[BLOCS_DATA_CONVERT], '::raw_')))) {
            $resultBuff .= '\Blocs\Common::convertDefault(';
            $postConvert = self::getMenuName($attrList[BLOCS_DATA_VAL]).')';
        }
        if (isset($attrList[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
            $convertFunc = Common::findConvertFunc($convertClass, $convertFunc);

            $resultBuff .= $convertFunc.'('.$dataVal.$convertArg.')';
        } else {
            $resultBuff .= $dataVal;
        }
        isset($postConvert) && $resultBuff .= $postConvert;

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            $resultBuff .= "); ?>\n";
        } else {
            $resultBuff .= "; ?>\n";
        }

        $resultBuff = self::addFixValue($attrList, $quotesList, $resultBuff, BLOCS_DATA_POSTFIX);

        if (!empty($attrList[BLOCS_DATA_ASSIGN])) {
            $resultBuff .= "<?php {$attrList[BLOCS_DATA_ASSIGN]} = \$dataVal; ?>\n";
        }

        if (Common::checkValueName($attrList[BLOCS_DATA_VAL]) || !isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
            $resultBuff .= BLOCS_ENDIF_SCRIPT;
        }

        if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
            // data-attributeが設定されている時の処理
            $dataAttribute[] = [
                'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                'value' => $resultBuff,
            ];

            return '';
        }

        // data-attributeが設定されていない時の処理
        $tagName && $resultBuff = self::setTagCounterVal($resultBuff, $attrList, $tagName, $tagCounter, $htmlArray);

        return $resultBuff;
    }

    // data-noticeのスクリプトを生成
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

        $resultBuff = '';
        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            $resultBuff .= '<?php echo(';
            if (!(isset($attrList[BLOCS_DATA_CONVERT]) && 'raw' === $attrList[BLOCS_DATA_CONVERT])) {
                $resultBuff .= '\Blocs\Common::convertDefault(';
            }
        } else {
            if (!Common::checkValueName($attrList[BLOCS_DATA_ASSIGN])) {
                trigger_error('B012: Invalid condition "'.BLOCS_DATA_ASSIGN.'" ('.$attrList[BLOCS_DATA_ASSIGN].')', E_USER_ERROR);
            }

            $resultBuff .= "<?php {$attrList[BLOCS_DATA_ASSIGN]} = ";
        }

        $resultBuff .= '\Blocs\Lang::get("'.$attrList[BLOCS_DATA_NOTICE].'")';

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            if (!(isset($attrList[BLOCS_DATA_CONVERT]) && 'raw' === $attrList[BLOCS_DATA_CONVERT])) {
                $resultBuff .= ')';
            }
            $resultBuff .= "); ?>\n";
        } else {
            $resultBuff .= "; ?>\n";
        }

        // data-attributeが設定されている時の処理
        if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
            $dataAttribute[] = [
                'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                'value' => $resultBuff,
            ];
            $resultBuff = '';
        }

        // data-attributeが設定されていない時の処理
        $tagName && $resultBuff = self::setTagCounterVal($resultBuff, $attrList, $tagName, $tagCounter, $htmlArray);

        return $resultBuff;
    }

    private static function addFixValue($attrList, $quotesList, $resultBuff, $attrName)
    {
        if (!(isset($attrList[$attrName]))) {
            return $resultBuff;
        }

        if (empty($attrList[BLOCS_DATA_ASSIGN])) {
            if (empty($quotesList[$attrName])) {
                // 変数の場合
                return $resultBuff."<?php echo({$attrList[$attrName]}); ?>\n";
            } else {
                return $resultBuff.$attrList[$attrName];
            }
        }

        empty($quotesList[$attrName]) && $quotesList[$attrName] = '';
        $resultBuff .= "<?php \$dataVal .= {$quotesList[$attrName]}{$attrList[$attrName]}{$quotesList[$attrName]}; ?>\n";

        return $resultBuff;
    }

    private static function getMenuName($dataVal)
    {
        if (!Common::checkValueName($dataVal)) {
            return '';
        }

        $propertyName = explode('->', $dataVal, 2);
        if (count($propertyName) > 1) {
            $dataVal = '$'.$propertyName[1];
        }

        $valueName = explode('[', $dataVal);
        if (1 === count($valueName)) {
            return ', \''.substr($dataVal, 1).'\'';
        }

        if (!strncmp(end($valueName), "'", 1) || !strncmp(end($valueName), '"', 1)) {
            return ', '.substr(end($valueName), 0, -1);
        } else {
            return ', \''.substr(end($valueName), 0, -1).'\'';
        }
    }

    private static function setTagCounterVal($resultBuff, $attrList, $tagName, &$tagCounter, &$htmlArray)
    {
        if (BLOCS_ENDIF_SCRIPT !== substr($resultBuff, -16)) {
            $tagCounter = [
                'tag' => $tagName,
                'before' => $resultBuff,
                'type' => 'ignore',
            ];
        } else {
            // 次回のタグ処理の先頭に入れる
            array_unshift($htmlArray, substr($resultBuff, 0, -16)."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tagName,
                'before' => BLOCS_ENDIF_SCRIPT,
            ];
        }

        return '';
    }
}
