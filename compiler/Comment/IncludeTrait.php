<?php

namespace Blocs\Compiler\Comment;

use Blocs\Compiler\Cache\Condition;
use Blocs\Compiler\Parser;

trait IncludeTrait
{
    private function compileCommentInclude($attrList, &$htmlBuff, &$htmlArray, $quotesList)
    {
        // auto includeのタグの埋め込み
        $autoincludeDir = self::getAutoincludeDir();
        if ('auto' == $attrList[BLOCS_DATA_INCLUDE]) {
            if (false === $autoincludeDir) {
                if (isset($attrList[BLOCS_DATA_EXIST])) {
                    return;
                } else {
                    trigger_error('B003: Can not find template (autoinclude)', E_USER_ERROR);
                }
            }

            // 引数を渡せるように
            $htmlBuff = $this->assignValue($attrList, $quotesList);

            $htmlBuff .= '{{REPLACE_TO_AUTOINCLUDE}}';

            return;
        }

        $resultArray = [];
        if (isset($this->partInclude[$attrList[BLOCS_DATA_INCLUDE]])) {
            $resultArray = $this->partInclude[$attrList[BLOCS_DATA_INCLUDE]];
        } else {
            $resultArray = $this->addDataInclude($attrList, $htmlBuff, $quotesList);
        }

        if (empty($attrList[BLOCS_DATA_EXIST])) {
            unset($attrList[BLOCS_DATA_EXIST]);
        }

        if (empty($resultArray)) {
            $htmlBuff = '';

            return;
        }

        $resultArray[] = '<!-- '.BLOCS_DATA_CHDIR.'="'.getcwd().'" '.BLOCS_DATA_ASSIGN.' -->';

        // conditionで挟み込み
        $condition = Condition::condition('', $attrList, $quotesList);
        if (!empty($condition)) {
            array_unshift($resultArray, $condition);
            $resultArray[] = BLOCS_ENDIF_SCRIPT;
        }

        $htmlArray = array_merge($resultArray, $htmlArray);

        // 引数を渡せるように
        $htmlBuff = $this->assignValue($attrList, $quotesList, true);
    }

    private function addDataInclude($attrList, $htmlBuff, $quotesList)
    {
        $_ = fn ($s) => $s;
        eval("\$attrList[BLOCS_DATA_INCLUDE] = <<<EOS\n{$attrList[BLOCS_DATA_INCLUDE]}\nEOS;\n");

        if (!strncmp($attrList[BLOCS_DATA_INCLUDE], '/', 1) && !is_file($attrList[BLOCS_DATA_INCLUDE])) {
            // ルートディレクトリのパスを変換
            $attrList[BLOCS_DATA_INCLUDE] = BLOCS_ROOT_DIR.$attrList[BLOCS_DATA_INCLUDE];
        } elseif (empty($quotesList[BLOCS_DATA_INCLUDE])) {
            eval('$attrList[BLOCS_DATA_INCLUDE] = '.$attrList[BLOCS_DATA_INCLUDE].';');
        }

        if (!strlen($realpath = str_replace(DIRECTORY_SEPARATOR, '/', realpath($attrList[BLOCS_DATA_INCLUDE])))) {
            if (false !== ($resultBuff = $this->addAutoinclude($attrList, $htmlBuff))) {
                // data-includeができないのでauto includeしてみる
                return $resultBuff;
            }

            if (isset($attrList[BLOCS_DATA_EXIST]) && !strlen($attrList[BLOCS_DATA_EXIST])) {
                return [];
            }

            trigger_error('B003: Can not find template ('.getcwd().'/'.$attrList[BLOCS_DATA_INCLUDE].')', E_USER_ERROR);
        }

        if (count($this->include) > BLOCS_INCLUDE_MAX) {
            trigger_error('B004: Template loop error (over '.BLOCS_INCLUDE_MAX.')', E_USER_ERROR);
        }
        $this->include[] = $realpath;

        $autoincludeDir = self::getAutoincludeDir();
        $autoinclude = pathinfo($realpath, PATHINFO_FILENAME);
        if (false !== $autoincludeDir && !strncmp($realpath, $autoincludeDir, strlen($autoincludeDir))) {
            if (isset($this->autoincluded[$autoinclude])) {
                // auto includeは一回だけしかincludeしない
                return [];
            }
        }
        $this->autoincluded[$autoinclude] = true;

        if (!isset($this->partInclude[$realpath])) {
            // ファイルごとにタグを保持
            $this->partInclude[$realpath] = $this->parseTemplate(self::checkEncoding($realpath), $realpath);
        }

        return $this->partInclude[$realpath];
    }

    private function addAutoinclude($attrList, $htmlBuff)
    {
        $autoincludeDir = self::getAutoincludeDir();
        if (false === $autoincludeDir) {
            return false;
        }

        list($autoinclude) = explode('_', $attrList[BLOCS_DATA_INCLUDE]);
        if (!is_file($autoincludeDir.'/'.$autoinclude.'.html')) {
            return false;
        }

        if (isset($this->autoincluded[$autoinclude])) {
            // すでにincludeされている
            return false;
        }

        // auto includeの対象に追加（無限ループにならないよう注意）
        if ($autoinclude === $attrList[BLOCS_DATA_INCLUDE]) {
            return [
                '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->",
            ];
        }

        return [
            '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->",
            $htmlBuff,
        ];
    }

    // ファイルごとにパーシング
    private function parseTemplate($writeBuff, $realpath, $autoinclude = true)
    {
        // ブロックのために元ファイルのパスをセットするタグを追加
        $chdirBuff = '<!-- '.BLOCS_DATA_CHDIR.'="'.dirname($realpath).'" -->';
        $htmlArray = Parser::parse($writeBuff);
        array_unshift($htmlArray, $chdirBuff);

        $resultArray = [];
        foreach ($htmlArray as $htmlBuff) {
            $resultArray[] = $htmlBuff;

            if (!is_array($htmlBuff) && !strncmp($htmlBuff, '<!', 2)) {
                // data-blocのロケーションはオリジナルファイルのパスに設定
                list($includeBuff) = Parser::parse($htmlBuff, true);

                if (isset($includeBuff['attribute'][BLOCS_DATA_BLOC])) {
                    $resultArray[] = $chdirBuff;
                }
            }
        }

        if (!$autoinclude) {
            return $resultArray;
        }

        // auto inlcudeテンプレートは移動
        $autoincludeDir = self::getAutoincludeDir();
        if (false !== $autoincludeDir && !strncmp($realpath, $autoincludeDir, strlen($autoincludeDir))) {
            array_unshift($resultArray, '{{AUTOINCLUDE_START_FROM}}');
            $resultArray[] = '{{AUTOINCLUDE_END_TO}}';
        }

        return $resultArray;
    }

    private static function checkEncoding($realpath)
    {
        $viewBuff = file_get_contents($realpath);

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($viewBuff, 'UTF-8', true);
            if ('UTF-8' !== $encoding) {
                trigger_error('B011: Can not permit this encoding ('.$encoding.') and have to convert to "UTF-8"', E_USER_ERROR);
            }
        }

        return $viewBuff;
    }
}
