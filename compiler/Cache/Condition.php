<?php

namespace Blocs\Compiler\Cache;

class Condition
{
    // data-existなどのスクリプトを生成
    public static function condition($compiledTag, $attrList, $quotesList, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        if (isset($attrList[BLOCS_DATA_EXIST])) {
            $compiledTag = "<?php if(!empty({$attrList[BLOCS_DATA_EXIST]})): ?>\n".$compiledTag;
        } elseif (isset($attrList[BLOCS_DATA_NONE])) {
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
}
