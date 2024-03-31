<?php

namespace Blocs\Compiler\Cache;

class Repeat
{
    // data-repeatのスクリプトを生成
    public static function repeat($attrList, $tagCounterNum)
    {
        $compiledTag = '';

        if (isset($attrList[BLOCS_DATA_CONVERT])) {
            list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
            $convertFunc = Common::findConvertFunc($convertClass, $convertFunc);

            $compiledTag .= "<?php {$attrList[BLOCS_DATA_REPEAT]} = {$convertFunc}({$attrList[BLOCS_DATA_REPEAT]}{$convertArg}); ?>\n";
        }

        $md5workKey = md5($attrList[BLOCS_DATA_REPEAT]);
        $compiledTag .= <<< END_of_HTML
<?php
    empty({$attrList[BLOCS_DATA_REPEAT]}) && {$attrList[BLOCS_DATA_REPEAT]} = [];
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
?>

END_of_HTML;

        return $compiledTag;
    }

    // data-loopのスクリプトを生成
    public static function loop($attrList, $tagCounterNum)
    {
        if (isset($attrList[BLOCS_DATA_REPEAT])) {
            return self::repeat($attrList, $tagCounterNum);
        }

        if (empty($attrList[BLOCS_DATA_QUERY])) {
            $strSingular = substr($attrList[BLOCS_DATA_LOOP], 1);
            $propertyName = explode('->', $strSingular, 2);
            if (count($propertyName) > 1) {
                $strSingular = $propertyName[1];
            }
            $strSingular = str_replace('()', '', $strSingular);

            $strSingular = '$'.\Str::singular($strSingular);
        } else {
            $strSingular = $attrList[BLOCS_DATA_QUERY];
        }

        if ('()' === substr($attrList[BLOCS_DATA_LOOP], -2)) {
            $compiledTag = '';
        } else {
            $compiledTag = "@php empty({$attrList[BLOCS_DATA_LOOP]}) && {$attrList[BLOCS_DATA_LOOP]} = []; @endphp\n";
        }
        $compiledTag .= "@foreach ({$attrList[BLOCS_DATA_LOOP]} as {$strSingular})\n";
        $compiledTag .= "@php \$repeatIndex{$tagCounterNum} = \$loop->index; @endphp\n";

        return $compiledTag;
    }

    // data-endloopのスクリプトを生成
    public static function endloop($attrList)
    {
        if (isset($attrList[BLOCS_DATA_REPEAT]) || isset($attrList[BLOCS_DATA_ENDREPEAT])) {
            return self::endrepeat($attrList);
        }

        return "@endforeach\n";
    }
}
