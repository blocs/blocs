<?php

namespace Blocs\Compiler\Cache;

class Common
{
    // attributeの置き換え
    public static function mergeAttribute($compiledTag, $attrName, $attrBuff, &$attrList, $noValue = false)
    {
        // data-attributeと空白のdata-val時（値のない属性）の処理
        if ($noValue) {
            $preAttr = '';
            $postAttr = '';
        } else {
            $preAttr = '="';
            $postAttr = '"';
        }

        if (isset($attrList[$attrName])) {
            if (BLOCS_ENDIF_SCRIPT === substr($attrBuff, -16) && false === strpos($attrBuff, '<?php else: ?>')) {
                $attrBuff = substr($attrBuff, 0, -16)."<?php else: ?>\n".$attrList[$attrName].BLOCS_ENDIF_SCRIPT;
            }

            $compiledTag = preg_replace('/(\s+'.$attrName.'\s*=\s*["\']{0,1})'.str_replace('/', '\/', preg_quote($attrList[$attrName])).'((\[\]){0,1}["\']{0,1}[\s<>\/]+)/si', '${1}'.$attrBuff.'${2}', $compiledTag);
        } elseif ('/>' === substr($compiledTag, -2)) {
            if ($condition = self::checkAttributeValue($attrBuff, " {$attrName}{$preAttr}", $postAttr)) {
                $compiledTag = rtrim(substr($compiledTag, 0, -2))."{$condition} />";
            } else {
                $compiledTag = rtrim(substr($compiledTag, 0, -2))." {$attrName}{$preAttr}{$attrBuff}{$postAttr} />";
            }
        } else {
            if ($condition = self::checkAttributeValue($attrBuff, " {$attrName}{$preAttr}", $postAttr)) {
                $compiledTag = rtrim(substr($compiledTag, 0, -1))."{$condition}>";
            } else {
                $compiledTag = rtrim(substr($compiledTag, 0, -1))." {$attrName}{$preAttr}{$attrBuff}{$postAttr}>";
            }
        }

        $attrList[$attrName] = $attrBuff;

        return $compiledTag;
    }

    // 特定文字をエスケープ
    public static function escapeDoubleQuote($str)
    {
        $str = str_replace(['\\', "\n", "\r", "\t", '"'], ['\\\\', '\n', '\r', '\t', '\"'], $str);
        $str = str_replace(["\v", "\f"], ['\v', '\f'], $str);
        $str = str_replace("\e", '\e', $str);

        return '"'.$str.'"';
    }

    // 文字列からクラス、メソッド、引数を取得
    public static function checkFunc($funcQuery)
    {
        if (false !== strpos($funcQuery, '::')) {
            list($class, $funcQuery) = explode('::', $funcQuery, 2);
        } else {
            $class = '';
        }

        if (false === strpos($funcQuery, ':')) {
            $func = $funcQuery;
            $arg = '';
        } else {
            list($func, $arg) = explode(':', $funcQuery, 2);

            $argArray = preg_split('/([:"\'])/s', $arg, -1, PREG_SPLIT_DELIM_CAPTURE);
            $quotesBuff = $resultBuff = '';
            foreach ($argArray as $buff) {
                if ("'" == $buff || '"' == $buff) {
                    if (!strlen($quotesBuff)) {
                        $quotesBuff = $buff;
                    } elseif ($quotesBuff == $buff) {
                        $quotesBuff = '';
                    }
                }

                if (!strlen($quotesBuff) && ':' == $buff) {
                    $resultBuff .= ', ';
                } else {
                    $resultBuff .= $buff;
                }
            }

            $arg = ', '.$resultBuff;
        }

        return [$class, $func, $arg];
    }

    // 変数として使用できる文字列かチェック
    // data-assignなどで指定された文字列が変数として使用できないとエラーが発生するため
    public static function checkValueName($valueName)
    {
        if (strncmp($valueName, '$', 1)) {
            return false;
        }

        list($valueName) = explode('[', $valueName, 2);
        $valueName = str_replace('->', '', $valueName);

        if (is_numeric(substr($valueName, 1, 1)) || preg_match('/[^a-zA-Z0-9_]/', substr($valueName, 1))) {
            return false;
        }

        return true;
    }

    // フォーム名として使用できる文字列かチェック
    // フォーム名は変数として使用できる文字列でなければならない
    public static function checkFormName($valueName)
    {
        '[]' === substr($valueName, -2) && $valueName = substr($valueName, 0, -2);

        if (is_numeric(substr($valueName, 0, 1)) || preg_match('/[^a-zA-Z0-9_]/', $valueName)) {
            return false;
        }

        return $valueName;
    }

    private static function checkAttributeValue($attrBuff, $preAttr, $postAttr)
    {
        if (!trim($attrBuff)) {
            // data-valを指定しないケース
            return '';
        }

        $partList = explode('<?php if', $attrBuff);
        if (preg_replace("/\s/", '', $partList[0])) {
            return '';
        }

        $ifDepth = 0;
        $condition = "<?php \$preAttr='{$preAttr}'; \$postAttr=''; ?>\n".$partList[0];
        array_shift($partList);
        foreach ($partList as $buff) {
            $condition .= '<?php if';
            if ($ifDepth) {
                $condition .= $buff;
                $buff = explode(': ?>', $buff, 2);
            } else {
                $buff = explode(': ?>', $buff, 2);
                $condition .= $buff[0].": ?>\n<?php echo(\$preAttr); \$preAttr=''; \$postAttr='{$postAttr}'; ?>".$buff[1];
            }
            ++$ifDepth;

            if (false !== strpos($buff[1], BLOCS_ENDIF_SCRIPT)) {
                $buff = explode(BLOCS_ENDIF_SCRIPT, $buff[1]);
                foreach ($buff as $endif) {
                    if (!$ifDepth && preg_replace("/\s/", '', $endif)) {
                        return '';
                    }
                    $ifDepth && $ifDepth--;
                }
            }
        }

        return $condition."<?php echo(\$postAttr); ?>\n";
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
