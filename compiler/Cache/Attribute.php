<?php

namespace Blocs\Compiler\Cache;

class Attribute
{
    // data-valのスクリプトを生成
    public static function val($attrList, $quotesList, &$dataAttribute, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $resultBuff = '';

        isset($quotesList[BLOCS_DATA_VAL]) || $quotesList[BLOCS_DATA_VAL] = '';
        $dataVal = $quotesList[BLOCS_DATA_VAL].$attrList[BLOCS_DATA_VAL].$quotesList[BLOCS_DATA_VAL];

        if (Common::checkValueName($attrList[BLOCS_DATA_VAL])) {
            $resultBuff .= "<?php if(isset({$attrList[BLOCS_DATA_VAL]}) && !is_object({$attrList[BLOCS_DATA_VAL]}) && !is_array({$attrList[BLOCS_DATA_VAL]}) && strlen({$attrList[BLOCS_DATA_VAL]})): ?>\n";
        } else {
            $resultBuff .= "<?php if(strlen({$dataVal})): ?>\n";
        }

        if (!empty($attrList[BLOCS_DATA_QUERY])) {
            Common::checkValueName($attrList[BLOCS_DATA_QUERY]) || trigger_error('B012: Invalid condition "'.BLOCS_DATA_QUERY.'" ('.$attrList[BLOCS_DATA_QUERY].')', E_USER_ERROR);

            $resultBuff .= "<?php \$dataVal = ''; ?>\n";
        }

        $resultBuff = self::addFixValue($attrList, $quotesList, $resultBuff, BLOCS_DATA_PREFIX);

        if (empty($attrList[BLOCS_DATA_QUERY])) {
            $resultBuff .= '<?php echo(';
        } else {
            $resultBuff .= '<?php $dataVal .= ';
        }

        if (isset($attrList[BLOCS_DATA_CONVERT]) && 'raw' === $attrList[BLOCS_DATA_CONVERT]) {
            unset($attrList[BLOCS_DATA_CONVERT]);
        } elseif (empty($attrList[BLOCS_DATA_QUERY]) && (!isset($attrList[BLOCS_DATA_CONVERT]) || (strncmp($attrList[BLOCS_DATA_CONVERT], 'raw_', 4) && false === strpos($attrList[BLOCS_DATA_CONVERT], '::raw_')))) {
            $resultBuff .= '\Blocs\Common::convertDefault(';
            $postConvert = self::getMenuName($attrList[BLOCS_DATA_VAL]).')';
        }
        if (isset($attrList[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
            $convertFunc = self::findConvertFunc($convertClass, $convertFunc);

            $resultBuff .= $convertFunc.'('.$dataVal.$convertArg.')';
        } else {
            $resultBuff .= $dataVal;
        }
        isset($postConvert) && $resultBuff .= $postConvert;

        if (empty($attrList[BLOCS_DATA_QUERY])) {
            $resultBuff .= "); ?>\n";
        } else {
            $resultBuff .= "; ?>\n";
        }

        $resultBuff = self::addFixValue($attrList, $quotesList, $resultBuff, BLOCS_DATA_POSTFIX);

        if (!empty($attrList[BLOCS_DATA_QUERY])) {
            $resultBuff .= "<?php {$attrList[BLOCS_DATA_QUERY]} = \$dataVal; ?>\n";
        }

        $resultBuff .= BLOCS_ENDIF_SCRIPT;

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
        if (empty($attrList[BLOCS_DATA_QUERY])) {
            $resultBuff .= '<?php echo(';
            if (!(isset($attrList[BLOCS_DATA_CONVERT]) && 'raw' === $attrList[BLOCS_DATA_CONVERT])) {
                $resultBuff .= '\Blocs\Common::convertDefault(';
            }
        } else {
            if (!Common::checkValueName($attrList[BLOCS_DATA_QUERY])) {
                trigger_error('B012: Invalid condition "'.BLOCS_DATA_QUERY.'" ('.$attrList[BLOCS_DATA_QUERY].')', E_USER_ERROR);
            }

            $resultBuff .= "<?php {$attrList[BLOCS_DATA_QUERY]} = ";
        }

        $resultBuff .= '\Blocs\Lang::get("'.$attrList[BLOCS_DATA_NOTICE].'")';

        if (empty($attrList[BLOCS_DATA_QUERY])) {
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

    // data-existなどのスクリプトを生成
    public static function condition($compiledTag, $attrList, $quotesList, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        if (isset($attrList[BLOCS_DATA_EXIST])) {
            if (!Common::checkValueName($attrList[BLOCS_DATA_EXIST])) {
                trigger_error('B006: Invalid condition "'.BLOCS_DATA_EXIST.'" ('.$attrList[BLOCS_DATA_EXIST].')', E_USER_ERROR);
            }
            $compiledTag = "<?php if(!empty({$attrList[BLOCS_DATA_EXIST]})): ?>\n".$compiledTag;
        } elseif (isset($attrList[BLOCS_DATA_NONE])) {
            if (!Common::checkValueName($attrList[BLOCS_DATA_NONE])) {
                trigger_error('B007: Invalid condition "'.BLOCS_DATA_NONE.'" ('.$attrList[BLOCS_DATA_NONE].')', E_USER_ERROR);
            }
            $compiledTag = "<?php if(empty({$attrList[BLOCS_DATA_NONE]})): ?>\n".$compiledTag;
        } elseif (isset($attrList[BLOCS_DATA_IF])) {
            $compiledTag = "<?php if({$attrList[BLOCS_DATA_IF]}): ?>\n".$compiledTag;
        } elseif (isset($attrList[BLOCS_DATA_UNLESS])) {
            $compiledTag = "<?php if(!({$attrList[BLOCS_DATA_UNLESS]})): ?>\n".$compiledTag;
        }

        if ($tagName) {
            // タグ記法
            if ('/>' === substr($compiledTag, -2)) {
                // はさまないタグの場合
                array_unshift($htmlArray, BLOCS_ENDIF_SCRIPT);
            } else {
                $tagCounter = [
                    'tag' => $tagName,
                    'after' => BLOCS_ENDIF_SCRIPT,
                ];
            }
        }

        return $compiledTag;
    }

    // data-repeatのスクリプトを生成
    public static function repeat($attrList, $tagCounterNum)
    {
        $compiledTag = '';

        if (isset($attrList[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
            $convertFunc = self::findConvertFunc($convertClass, $convertFunc);

            $compiledTag .= "<?php {$attrList[BLOCS_DATA_REPEAT]} = {$convertFunc}({$attrList[BLOCS_DATA_REPEAT]}{$convertArg}); ?>\n";
        }

        $md5workKey = md5($attrList[BLOCS_DATA_REPEAT]);
        $compiledTag .= <<< END_of_HTML
<?php
    if(!empty({$attrList[BLOCS_DATA_REPEAT]})):
        foreach({$attrList[BLOCS_DATA_REPEAT]} as \$repeatIndex => \$work_{$md5workKey}):
            \$repeatIndex{$tagCounterNum} = \$repeatIndex;
            \$parentItemList = [];
            foreach(array_keys(\$work_{$md5workKey}) as \$parentItem){
                isset(\$\$parentItem) && \$parentItemList[] = \$parentItem;
            }
            \$parent[] = compact(\$parentItemList);
            extract(\$work_{$md5workKey});
?>

END_of_HTML;

        return $compiledTag;
    }

    // data-endrepeatのスクリプトを生成
    public static function endrepeat($attrList)
    {
        $md5workKey = md5($attrList[BLOCS_DATA_REPEAT]);
        $compiledTag = <<< END_of_HTML
<?php
            foreach(array_keys(\$work_{$md5workKey}) as \$workKey){
                unset(\$\$workKey);
            };
            extract(array_pop(\$parent));
        endforeach;
    endif;
?>

END_of_HTML;

        return $compiledTag;
    }

    // data-loopのスクリプトを生成
    public static function loop($attrList, $tagCounterNum)
    {
        $strSingular = \Str::singular(substr($attrList[BLOCS_DATA_LOOP], 1));

        $compiledTag = "@foreach ({$attrList[BLOCS_DATA_LOOP]} as \${$strSingular})\n";
        $compiledTag .= "@php \$repeatIndex{$tagCounterNum} = \$loop->index; @endphp\n";

        return $compiledTag;
    }

    // data-endloopのスクリプトを生成
    public static function endloop($attrList)
    {
        return "@endforeach\n";
    }

    private static function addFixValue($attrList, $quotesList, $resultBuff, $attrName)
    {
        if (!(isset($attrList[$attrName]))) {
            return $resultBuff;
        }

        if (empty($attrList[BLOCS_DATA_QUERY])) {
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

    private static function findConvertFunc($convertClass, $convertFunc)
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
