<?php

namespace Blocs\Compiler\Cache;

class Attribute
{
    // data-valのスクリプトを生成
    public static function val($attrArray, $quotesArray, &$dataAttribute, $tag = '', &$tagCounter = null, &$htmlArray = null)
    {
        $resultBuff = '';
        if (!empty($attrArray[BLOCS_DATA_QUERY])) {
            Common::checkValueName($attrArray[BLOCS_DATA_QUERY]) || trigger_error('B012: Invalid condition "'.BLOCS_DATA_QUERY.'" ('.$attrArray[BLOCS_DATA_QUERY].')', E_USER_ERROR);

            $resultBuff .= "<?php \$dataVal = ''; ?>\n";
        }

        isset($quotesArray[BLOCS_DATA_VAL]) || $quotesArray[BLOCS_DATA_VAL] = '';
        if (Common::checkValueName($attrArray[BLOCS_DATA_VAL])) {
            $resultBuff .= "<?php if(isset({$attrArray[BLOCS_DATA_VAL]}) && (is_array({$attrArray[BLOCS_DATA_VAL]}) || strlen({$attrArray[BLOCS_DATA_VAL]}))): ?>\n";
            $quotesArray[BLOCS_DATA_VAL] = '';
        }

        $resultBuff = self::addFixValue($attrArray, $quotesArray, $resultBuff, BLOCS_DATA_PREFIX);

        if (empty($attrArray[BLOCS_DATA_QUERY])) {
            $resultBuff .= '<?php echo(';
        } else {
            $resultBuff .= '<?php $dataVal .= ';
        }

        if (isset($attrArray[BLOCS_DATA_CONVERT]) && 'raw' === $attrArray[BLOCS_DATA_CONVERT]) {
            unset($attrArray[BLOCS_DATA_CONVERT]);
        } elseif (empty($attrArray[BLOCS_DATA_QUERY]) && (!isset($attrArray[BLOCS_DATA_CONVERT]) || (strncmp($attrArray[BLOCS_DATA_CONVERT], 'raw_', 4) && false === strpos($attrArray[BLOCS_DATA_CONVERT], '::raw_')))) {
            $resultBuff .= '\Blocs\Common::convertDefault(';
            $postConvert = self::getMenuName($attrArray[BLOCS_DATA_VAL]).')';
        }
        if (isset($attrArray[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrArray[BLOCS_DATA_CONVERT]);
            $convertFunc = self::findConvertFunc($convertClass, $convertFunc);

            $resultBuff .= $convertFunc.'('.$quotesArray[BLOCS_DATA_VAL].$attrArray[BLOCS_DATA_VAL].$quotesArray[BLOCS_DATA_VAL].$convertArg.')';
        } else {
            $resultBuff .= $quotesArray[BLOCS_DATA_VAL].$attrArray[BLOCS_DATA_VAL].$quotesArray[BLOCS_DATA_VAL];
        }
        isset($postConvert) && $resultBuff .= $postConvert;

        if (empty($attrArray[BLOCS_DATA_QUERY])) {
            $resultBuff .= "); ?>\n";
        } else {
            $resultBuff .= "; ?>\n";
        }

        $resultBuff = self::addFixValue($attrArray, $quotesArray, $resultBuff, BLOCS_DATA_POSTFIX);

        if (!empty($attrArray[BLOCS_DATA_QUERY])) {
            $resultBuff .= "<?php {$attrArray[BLOCS_DATA_QUERY]} = \$dataVal; ?>\n";
        }

        Common::checkValueName($attrArray[BLOCS_DATA_VAL]) && $resultBuff .= BLOCS_ENDIF_SCRIPT;

        if (isset($attrArray[BLOCS_DATA_ATTRIBUTE])) {
            // data-attributeが設定されている時の処理
            $dataAttribute[] = [
                'name' => $attrArray[BLOCS_DATA_ATTRIBUTE],
                'value' => $resultBuff,
            ];

            return '';
        }

        // data-attributeが設定されていない時の処理
        $tag && $resultBuff = self::setTagCounterVal($resultBuff, $attrArray, $tag, $tagCounter, $htmlArray);

        return $resultBuff;
    }

    // data-noticeのスクリプトを生成
    public static function notice($attrArray, $quotesArray, &$dataAttribute, $tag = '', &$tagCounter = null, &$htmlArray = null)
    {
        $noticeList = explode(':', $attrArray[BLOCS_DATA_NOTICE]);

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
        if (empty($attrArray[BLOCS_DATA_QUERY])) {
            $resultBuff .= '<?php echo(';
            if (!(isset($attrArray[BLOCS_DATA_CONVERT]) && 'raw' === $attrArray[BLOCS_DATA_CONVERT])) {
                $resultBuff .= '\Blocs\Common::convertDefault(';
            }
        } else {
            if (!Common::checkValueName($attrArray[BLOCS_DATA_QUERY])) {
                trigger_error('B012: Invalid condition "'.BLOCS_DATA_QUERY.'" ('.$attrArray[BLOCS_DATA_QUERY].')', E_USER_ERROR);
            }

            $resultBuff .= "<?php {$attrArray[BLOCS_DATA_QUERY]} = ";
        }

        $resultBuff .= '\Blocs\Lang::get("'.$attrArray[BLOCS_DATA_NOTICE].'")';

        if (empty($attrArray[BLOCS_DATA_QUERY])) {
            if (!(isset($attrArray[BLOCS_DATA_CONVERT]) && 'raw' === $attrArray[BLOCS_DATA_CONVERT])) {
                $resultBuff .= ')';
            }
            $resultBuff .= "); ?>\n";
        } else {
            $resultBuff .= "; ?>\n";
        }

        // data-attributeが設定されている時の処理
        if (isset($attrArray[BLOCS_DATA_ATTRIBUTE])) {
            $dataAttribute[] = [
                'name' => $attrArray[BLOCS_DATA_ATTRIBUTE],
                'value' => $resultBuff,
            ];
            $resultBuff = '';
        }

        // data-attributeが設定されていない時の処理
        $tag && $resultBuff = self::setTagCounterVal($resultBuff, $attrArray, $tag, $tagCounter, $htmlArray);

        return $resultBuff;
    }

    // data-existなどのスクリプトを生成
    public static function condition($compiledTag, $attrArray, $quotesArray, $tag = '', &$tagCounter = null, &$htmlArray = null)
    {
        if (isset($attrArray[BLOCS_DATA_EXIST])) {
            if (!Common::checkValueName($attrArray[BLOCS_DATA_EXIST])) {
                trigger_error('B006: Invalid condition "'.BLOCS_DATA_EXIST.'" ('.$attrArray[BLOCS_DATA_EXIST].')', E_USER_ERROR);
            }
            $compiledTag = "<?php if(!empty({$attrArray[BLOCS_DATA_EXIST]})): ?>\n".$compiledTag;
        } elseif (isset($attrArray[BLOCS_DATA_NONE])) {
            if (!Common::checkValueName($attrArray[BLOCS_DATA_NONE])) {
                trigger_error('B007: Invalid condition "'.BLOCS_DATA_NONE.'" ('.$attrArray[BLOCS_DATA_NONE].')', E_USER_ERROR);
            }
            $compiledTag = "<?php if(empty({$attrArray[BLOCS_DATA_NONE]})): ?>\n".$compiledTag;
        } elseif (isset($attrArray[BLOCS_DATA_IF])) {
            $compiledTag = "<?php if({$attrArray[BLOCS_DATA_IF]}): ?>\n".$compiledTag;
        } elseif (isset($attrArray[BLOCS_DATA_UNLESS])) {
            $compiledTag = "<?php if(!({$attrArray[BLOCS_DATA_UNLESS]})): ?>\n".$compiledTag;
        }

        if ($tag) {
            // タグ記法
            if ('/>' === substr($compiledTag, -2)) {
                // はさまないタグの場合
                array_unshift($htmlArray, BLOCS_ENDIF_SCRIPT);
            } else {
                $tagCounter = [
                    'tag' => $tag,
                    'after' => BLOCS_ENDIF_SCRIPT,
                ];
            }
        }

        return $compiledTag;
    }

    // data-repeatのスクリプトを生成
    public static function repeat($attrArray, $tagCounterNum)
    {
        $compiledTag = '';

        if (isset($attrArray[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrArray[BLOCS_DATA_CONVERT]);
            $convertFunc = self::findConvertFunc($convertClass, $convertFunc);

            $compiledTag .= "<?php {$attrArray[BLOCS_DATA_REPEAT]} = {$convertFunc}({$attrArray[BLOCS_DATA_REPEAT]}{$convertArg}); ?>\n";
        }

        $md5workKey = md5($attrArray[BLOCS_DATA_REPEAT]);
        $compiledTag .= <<< END_of_HTML
<?php
    if(!empty({$attrArray[BLOCS_DATA_REPEAT]})):
        foreach({$attrArray[BLOCS_DATA_REPEAT]} as \$repeatIndex => \$work_{$md5workKey}):
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
    public static function endrepeat($attrArray)
    {
        $md5workKey = md5($attrArray[BLOCS_DATA_REPEAT]);
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
    public static function loop($attrArray, $tagCounterNum)
    {
        $strSingular = \Str::singular(substr($attrArray[BLOCS_DATA_LOOP], 1));

        $compiledTag = "@foreach ({$attrArray[BLOCS_DATA_LOOP]} as \${$strSingular})\n";
        $compiledTag .= "@php \$repeatIndex{$tagCounterNum} = \$loop->index; @endphp\n";

        return $compiledTag;
    }

    // data-endloopのスクリプトを生成
    public static function endloop($attrArray)
    {
        return "@endforeach\n";
    }

    private static function addFixValue($attrArray, $quotesArray, $resultBuff, $attrName)
    {
        if (!(isset($attrArray[$attrName]) && Common::checkValueName($attrArray[BLOCS_DATA_VAL]))) {
            return $resultBuff;
        }

        if (empty($attrArray[BLOCS_DATA_QUERY])) {
            if (Common::checkValueName($attrArray[$attrName])) {
                // 変数の場合
                return $resultBuff."<?php echo({$attrArray[$attrName]}); ?>\n";
            } else {
                return $resultBuff.$attrArray[$attrName];
            }
        }

        empty($quotesArray[$attrName]) && $quotesArray[$attrName] = '';
        $resultBuff .= "<?php \$dataVal .= {$quotesArray[$attrName]}{$attrArray[$attrName]}{$quotesArray[$attrName]}; ?>\n";

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

    private static function setTagCounterVal($resultBuff, $attrArray, $tag, &$tagCounter, &$htmlArray)
    {
        if (BLOCS_ENDIF_SCRIPT !== substr($resultBuff, -16)) {
            $tagCounter = [
                'tag' => $tag,
                'before' => $resultBuff,
                'type' => 'ignore',
            ];
        } else {
            // 次回のタグ処理の先頭に入れる
            array_unshift($htmlArray, substr($resultBuff, 0, -16)."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tag,
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
