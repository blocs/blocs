<?php

namespace Blocs\Compiler\Cache;

class Loop
{
    // data-loopのスクリプトを生成
    public static function loop($attrList, $tagCounterNum)
    {
        $strSingular = self::getSingular($attrList);

        $compiledTag = '';
        if ('()' !== substr($attrList[BLOCS_DATA_LOOP], -2)) {
            // 未定義でエラーにならないように
            $compiledTag .= <<< END_of_HTML
<?php
    empty({$attrList[BLOCS_DATA_LOOP]}) && {$attrList[BLOCS_DATA_LOOP]} = [];
?>

END_of_HTML;

            // data-convertで変換
            if (isset($attrList[BLOCS_DATA_CONVERT])) {
                list($convertClass, $convertFunc, $convertArg) = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
                $convertFunc = Common::findConvertFunc($convertClass, $convertFunc);

                $compiledTag .= <<< END_of_HTML
<?php
    {$attrList[BLOCS_DATA_LOOP]} = {$convertFunc}({$attrList[BLOCS_DATA_LOOP]}{$convertArg});
?>

END_of_HTML;
            }
        }

        // foreachをつける
        $compiledTag .= self::generateForeach($attrList, $tagCounterNum, $strSingular);

        // 配列を変数として使えるように展開
        $compiledTag .= <<< END_of_HTML
<?php
        isset(\$loopIndex) && \$index_{$strSingular} = \$loopIndex;
        \$loopIndex = \$loopIndex{$tagCounterNum};

        \$parentItemList = [];
        foreach(array_keys(\$loop_{$strSingular}) as \$parentItem){
            isset(\$\$parentItem) && \$parentItemList[] = \$parentItem;
        }
        \$parent[] = compact(\$parentItemList);
        extract(\$loop_{$strSingular});
    endif;
?>

END_of_HTML;

        return $compiledTag;
    }

    // data-endloopのスクリプトを生成
    public static function endloop($attrList)
    {
        $strSingular = self::getSingular($attrList);

        // 配列を変数として使えるように展開
        $compiledTag = <<< END_of_HTML
<?php
    if(isset(\$loop_{$strSingular})):
        isset(\$index_{$strSingular}) && \$loopIndex = \$index_{$strSingular};

        foreach(array_keys(\$loop_{$strSingular}) as \$workKey){
            unset(\$\$workKey);
        };
        extract(array_pop(\$parent));
        unset(\$loop_{$strSingular});
    endif;
?>

END_of_HTML;

        return $compiledTag.self::generateEndForeach();
    }

    // テーブルフォームのためにloopIndexをつける
    private static function generateForeach($attrList, $tagCounterNum, $strSingular)
    {
        if (method_exists('Str', 'singular') && !defined('BLOCS_BLADE_OFF')) {
            // Laravelあり
            return <<< END_of_HTML
    @foreach({$attrList[BLOCS_DATA_LOOP]} as \$loopIndex{$tagCounterNum} => \${$strSingular})
<?php
    if(is_array(\${$strSingular}) || (is_object(\${$strSingular}) && method_exists(\${$strSingular}, 'toArray'))):
        \$loop_{$strSingular} = is_array(\${$strSingular}) ? \${$strSingular} : \${$strSingular}->toArray();
?>

END_of_HTML;
        }

        // Laravelなし
        return <<< END_of_HTML
<?php
    foreach({$attrList[BLOCS_DATA_LOOP]} as \$loopIndex{$tagCounterNum} => \${$strSingular}):
    if(is_array(\${$strSingular})):
        \$loop_{$strSingular} = \${$strSingular};
?>

END_of_HTML;
    }

    private static function generateEndForeach()
    {
        if (method_exists('Str', 'singular') && !defined('BLOCS_BLADE_OFF')) {
            // Laravelあり
            return <<< END_of_HTML
    @endforeach

END_of_HTML;
        }

        // Laravelなし
        return <<< END_of_HTML
<?php
    endforeach;
?>

END_of_HTML;
    }

    // ループで使うために自動で単数型を取得する
    private static function getSingular($attrList)
    {
        if (!empty($attrList[BLOCS_DATA_ASSIGN]) && Common::checkValueName($attrList[BLOCS_DATA_ASSIGN])) {
            // data-assignで指定できる
            return substr($attrList[BLOCS_DATA_ASSIGN], 1);
        }

        if (!method_exists('Str', 'singular')) {
            // Laravelなし
            return '_'.md5($attrList[BLOCS_DATA_LOOP]);
        }

        // Laravelあり
        $dataLoop = substr($attrList[BLOCS_DATA_LOOP], 1);
        $propertyName = explode('->', $dataLoop, 2);
        if (count($propertyName) > 1) {
            $dataLoop = $propertyName[1];
        }
        $dataLoop = str_replace('()', '', $dataLoop);

        $strSingular = \Str::singular($dataLoop);
        if ($strSingular == $dataLoop) {
            // 同じ名前になって上書きされないように
            return '_'.md5($attrList[BLOCS_DATA_LOOP]);
        }

        return $strSingular;
    }
}
