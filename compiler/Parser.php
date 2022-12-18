<?php

namespace Blocs\Compiler;

class Parser
{
    // データ属性名のエイリアス
    private static $aliasAttrName = [
        BLOCS_DATA_BLOC => BLOCS_DATA_PART,
        BLOCS_DATA_ENDBLOC => BLOCS_DATA_ENDPART,
        BLOCS_DATA_LANG => BLOCS_DATA_NOTICE,
        BLOCS_DATA_ASSIGN => BLOCS_DATA_QUERY,
    ];

    private static $escapeOperatorList = [
        '->',
    ];

    // HTMLをタグ単位で配列に分解
    public static function parse($writeBuff, $commentParse = false)
    {
        $pairTag = [];
        $commentTagRegex = '<!(?:--[^-]*-(?:[^-]+-)*?-(?:[^>-]*(?:-[^>-]+)*?)??)*(?:>|$(?!\n)|--.*$)';
        $tagNameRegex = '[a-zA-Z\_\:\!\$][a-zA-Z0-9\_\:\-\.]*';

        $pairBuff = '';
        $phpBuff = '';
        $resultArray = [''];

        if ($commentParse) {
            // コメントもパースする
            $comArray = [$writeBuff];
        } else {
            // コメントはパースしない
            $comArray = preg_split('/('.$commentTagRegex.')/s', $writeBuff, -1, PREG_SPLIT_DELIM_CAPTURE);
        }

        foreach ($comArray as $comNum => $comBuff) {
            if ($comNum % 2) {
                strlen(end($resultArray)) || array_pop($resultArray);
                array_push($resultArray, $comBuff, '');
                continue;
            }

            $comBuff = self::escepeOperator($comBuff);

            $tagName = '';
            $attrName = '';
            $tmpBuff = '';
            $quotesBuff = '';
            $attrBuff = '';
            $attrList = [];
            $quotesArray = [];
            $tagArray = preg_split('/([<>="\'])/s', $comBuff, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($tagArray as $tagNum => $tagBuff) {
                $tagBuff = self::backOperator($tagBuff);

                if ('<' === $tagBuff) {
                    if (isset($tagArray[$tagNum + 1])) {
                        if (!strncmp($tagArray[$tagNum + 1], '?', 1)) {
                            $phpBuff = 1;
                        } elseif (!strlen($quotesBuff) && !strlen($tagName) && !strlen($phpBuff)) {
                            if (preg_match('/^('.$tagNameRegex.')/s', $tagArray[$tagNum + 1], $matcheList) || preg_match('/^(\/\s*'.$tagNameRegex.')/s', $tagArray[$tagNum + 1], $matcheList)) {
                                $tagName = $matcheList[0];
                            }
                        }
                    }
                }

                if ('>' === $tagBuff && isset($tagArray[$tagNum - 1]) && '?' === substr($tagArray[$tagNum - 1], -1)) {
                    $phpBuff = '';
                }

                if (!strlen($tagName)) {
                    $resultArray[count($resultArray) - 1] .= $tagBuff;
                    continue;
                }

                $tmpBuff .= $tagBuff;

                if ('>' === $tagBuff && !strlen($quotesBuff)) {
                    !strncmp($attrBuff, '<'.$tagName, strlen('<'.$tagName)) && !count($attrList) && $attrBuff = substr($attrBuff, strlen('<'.$tagName));
                    $buff = array_filter(preg_split("/\s/", $attrBuff), 'strlen');

                    // collectionでなければ、data-loopをdata-repeatに置換
                    if (BLOCS_DATA_LOOP === $attrName && !empty($buff[0])) {
                        $strSingular = method_exists('Str', 'singular') ? \Str::singular(substr($buff[0], 1)) : false;
                        if (!$strSingular || !strpos($writeBuff, '$'.$strSingular.'->')) {
                            $tmpBuff = self::replaceAttribute($tmpBuff, [BLOCS_DATA_REPEAT => $buff[0]], BLOCS_DATA_REPEAT, BLOCS_DATA_LOOP);
                            $attrName = BLOCS_DATA_REPEAT;
                            unset($attrList[BLOCS_DATA_LOOP]);
                        }
                    }

                    self::addAttrList($attrList, $attrName, $buff);

                    $tagName = strtolower(preg_replace("/\s/s", '', $tagName));
                    $pairBuff && $tagName === '/'.$pairBuff && $pairBuff = '';

                    if ($pairBuff) {
                        $resultArray[count($resultArray) - 1] .= $tmpBuff;
                        $tmpBuff = '';
                    } else {
                        strlen(end($resultArray)) || array_pop($resultArray);
                        in_array($tagName, $pairTag) && $pairBuff = $tagName;

                        array_push($resultArray, [
                            'raw' => self::replaceDataAttribute($tmpBuff, $attrList),
                            'tag' => $tagName,
                            'attribute' => $attrList,
                            'quotes' => $quotesArray,
                        ], '');
                    }

                    $tagName = '';
                    $attrName = '';
                    $tmpBuff = '';
                    $quotesBuff = '';
                    $attrBuff = '';
                    $attrList = [];
                    $quotesArray = [];
                    continue;
                }

                if ('=' === $tagBuff && !strlen($quotesBuff) && !strlen($phpBuff)) {
                    $buff = ((!strncmp($attrBuff, '<'.$tagName, strlen('<'.$tagName)) && !count($attrList)) ? substr($attrBuff, strlen('<'.$tagName)) : $attrBuff);
                    $buff = array_filter(preg_split("/\s/", $buff), 'strlen');
                    if (count($buff) && preg_match('/^'.$tagNameRegex.'$/s', end($buff))) {
                        self::addAttrList($attrList, $attrName, $buff);

                        $attrName = array_pop($buff);
                        $attrBuff = '';
                        continue;
                    }
                }

                if (('"' === $tagBuff || '\'' === $tagBuff) && strlen($attrName) && !strlen($phpBuff)) {
                    if ($quotesBuff) {
                        if ($quotesBuff === $tagBuff) {
                            $attrList[self::aliasAttrName($attrName)] .= $attrBuff;
                            $quotesArray[self::aliasAttrName($attrName)] = $quotesBuff;
                            $attrName = '';
                            $quotesBuff = '';
                            $attrBuff = '';
                            continue;
                        }
                    } else {
                        if (isset($tagArray[$tagNum - 1]) && !trim($tagArray[$tagNum - 1])) {
                            if (isset($tagArray[$tagNum - 2]) && '=' === $tagArray[$tagNum - 2]) {
                                $quotesBuff = $tagBuff;
                                $attrBuff = '';
                                continue;
                            }
                        }
                    }
                }

                $attrBuff .= $tagBuff;
            }

            $tmpBuff && $resultArray[count($resultArray) - 1] .= $tmpBuff;
        }

        strlen(end($resultArray)) || array_pop($resultArray);

        return $resultArray;
    }

    private static function addAttrList(&$attrList, $attrName, $attrBuff)
    {
        foreach ($attrBuff as $buff) {
            if ($attrName) {
                $attrList[self::aliasAttrName($attrName)] = $buff;
                $attrName = '';
            } else {
                $attrList[self::aliasAttrName($buff)] = '';
            }
        }
    }

    private static function aliasAttrName($attrName)
    {
        foreach (self::$aliasAttrName as $aliasName => $realName) {
            if ($aliasName === $attrName) {
                return $realName;
            }
        }

        return $attrName;
    }

    private static function replaceAttribute($rawBuff, $attrArray, $attrName, $aliasName)
    {
        $rawBuff = preg_replace('/(\s+)'.$aliasName.'(\s*=\s*["\']{0,1}'.str_replace('/', '\/', preg_quote($attrArray[$attrName])).'["\']{0,1}[\s>\/]+)/si', '${1}'.$attrName.'${2}', $rawBuff);
        $rawBuff = preg_replace('/(\s+)'.$aliasName.'([\s>\/]+)/si', '${1}'.$attrName.'${2}', $rawBuff);

        return $rawBuff;
    }

    private static function replaceDataAttribute($rawBuff, $attrArray)
    {
        // エイリアス名に変換
        foreach (self::$aliasAttrName as $aliasName => $attrName) {
            isset($attrArray[$attrName]) && $rawBuff = self::replaceAttribute($rawBuff, $attrArray, $attrName, $aliasName);
        }

        return $rawBuff;
    }

    private static function escepeOperator($comBuff)
    {
        if (strlen($comBuff) < 2) {
            return $comBuff;
        }

        $firstChar = substr($comBuff, 0, 1);
        $lastChar = substr($comBuff, -1);
        $comBuff = substr($comBuff, 1, -1);

        foreach (self::$escapeOperatorList as $num => $escapeOperator) {
            $comBuff = str_replace($escapeOperator, "REPLACE_TO_OPERATOR_{$num}", $comBuff);
        }

        return $firstChar.$comBuff.$lastChar;
    }

    private static function backOperator($comBuff)
    {
        foreach (self::$escapeOperatorList as $num => $escapeOperator) {
            $comBuff = str_replace("REPLACE_TO_OPERATOR_{$num}", $escapeOperator, $comBuff);
        }

        return $comBuff;
    }
}

/* End of file */
