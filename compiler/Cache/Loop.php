<?php

namespace Blocs\Compiler\Cache;

class Loop
{
    // data-loop属性からスクリプトを生成する
    public static function loop($attrList, $tagCounterNum)
    {
        $singularName = self::resolveSingularName($attrList);

        $compiledHtml = '';
        if (substr($attrList[BLOCS_DATA_LOOP], -2) !== '()') {
            // 未定義変数によるエラーを避ける
            $compiledHtml .= <<< END_of_HTML
<?php
    empty({$attrList[BLOCS_DATA_LOOP]}) && {$attrList[BLOCS_DATA_LOOP]} = [];
?>

END_of_HTML;

            // data-convert属性で値を変換する
            if (isset($attrList[BLOCS_DATA_CONVERT])) {
                [$convertClass, $convertFunction, $convertArgument] = Common::checkFunc($attrList[BLOCS_DATA_CONVERT]);
                $convertFunction = Common::findConvertFunc($convertClass, $convertFunction);

                $compiledHtml .= <<< END_of_HTML
<?php
    {$attrList[BLOCS_DATA_LOOP]} = {$convertFunction}({$attrList[BLOCS_DATA_LOOP]}{$convertArgument});
?>

END_of_HTML;
            }
        }

        // foreach構文を生成する
        $compiledHtml .= self::buildLoopOpening($attrList, $tagCounterNum, $singularName);

        // ループ要素を個別の変数として展開する
        $compiledHtml .= <<< END_of_HTML
<?php
        isset(\$loopIndex) && \$index_{$singularName} = \$loopIndex;
        \$loopIndex = \$loopIndex{$tagCounterNum};

        \$parentItemList = [];
        foreach(array_keys(\$loop_{$singularName}) as \$parentItem){
            isset(\$\$parentItem) && \$parentItemList[] = \$parentItem;
        }
        \$parent[] = compact(\$parentItemList);
        extract(\$loop_{$singularName});
    endif;
?>

END_of_HTML;

        return $compiledHtml;
    }

    // data-endloop属性からスクリプトを生成する
    public static function endloop($attrList)
    {
        $singularName = self::resolveSingularName($attrList);

        // 個別に展開した変数をリセットする
        $compiledHtml = <<< END_of_HTML
<?php
    if(isset(\$loop_{$singularName})):
        isset(\$index_{$singularName}) && \$loopIndex = \$index_{$singularName};

        foreach(array_keys(\$loop_{$singularName}) as \$workKey){
            unset(\$\$workKey);
        };
        extract(array_pop(\$parent));
        unset(\$loop_{$singularName});
    endif;
?>

END_of_HTML;

        return $compiledHtml.self::buildLoopClosing();
    }

    // テーブルフォームのためにloopIndexを付与する
    private static function buildLoopOpening($attrList, $tagCounterNum, $singularName)
    {
        if (! defined('BLOCS_NO_LARAVEL') && ! defined('BLOCS_BLADE_OFF')) {
            // Laravelが利用可能な場合の処理
            return <<< END_of_HTML
    @foreach({$attrList[BLOCS_DATA_LOOP]} as \$loopIndex{$tagCounterNum} => \${$singularName})
<?php
    if(is_array(\${$singularName}) || (is_object(\${$singularName}) && method_exists(\${$singularName}, 'toArray'))):
        \$loop_{$singularName} = is_array(\${$singularName}) ? \${$singularName} : \${$singularName}->toArray();
?>

END_of_HTML;
        }

        // Laravelが利用できない場合の処理
        return <<< END_of_HTML
<?php
    foreach({$attrList[BLOCS_DATA_LOOP]} as \$loopIndex{$tagCounterNum} => \${$singularName}):
    if(is_array(\${$singularName})):
        \$loop_{$singularName} = \${$singularName};
?>

END_of_HTML;
    }

    private static function buildLoopClosing()
    {
        if (! defined('BLOCS_NO_LARAVEL') && ! defined('BLOCS_BLADE_OFF')) {
            // Laravelが利用可能な場合の終了処理
            return <<< 'END_of_HTML'
    @endforeach

END_of_HTML;
        }

        // Laravelが利用できない場合の終了処理
        return <<< 'END_of_HTML'
<?php
    endforeach;
?>

END_of_HTML;
    }

    // ループ処理で使用する単数形の名前を導出する
    private static function resolveSingularName($attrList)
    {
        if (! empty($attrList[BLOCS_DATA_ASSIGN]) && Common::checkValueName($attrList[BLOCS_DATA_ASSIGN])) {
            // data-assign属性が有効な場合に利用する
            return substr($attrList[BLOCS_DATA_ASSIGN], 1);
        }

        if (defined('BLOCS_NO_LARAVEL')) {
            // Laravelが利用できない場合に一意の名前を生成する
            return '_'.md5($attrList[BLOCS_DATA_LOOP]);
        }

        // Laravelが利用可能な場合に単数形へ変換する
        $dataLoop = substr($attrList[BLOCS_DATA_LOOP], 1);
        $propertyName = explode('->', $dataLoop, 2);
        if (count($propertyName) > 1) {
            $dataLoop = $propertyName[1];
        }
        $dataLoop = str_replace('()', '', $dataLoop);

        $singularName = \Illuminate\Support\Str::singular($dataLoop);
        if ($singularName == $dataLoop) {
            // 既存の変数名と重複しないように一意の名前を生成する
            return '_'.md5($attrList[BLOCS_DATA_LOOP]);
        }

        return $singularName;
    }
}
