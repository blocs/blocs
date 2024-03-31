<?php

namespace Blocs\Compiler\Comment;

use Blocs\Compiler\Cache\Condition;

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
            $htmlBuff = self::assignValue($attrList, $quotesList);

            $htmlBuff .= '{{REPLACE_TO_AUTOINCLUDE}}';

            return;
        }

        $resultArray = [];
        if (isset($this->partInclude[$attrList[BLOCS_DATA_INCLUDE]])) {
            $resultArray = $this->partInclude[$attrList[BLOCS_DATA_INCLUDE]];
        } else {
            $resultArray = $this->addDataInclude($attrList, $htmlBuff);
        }

        if (empty($attrList[BLOCS_DATA_EXIST])) {
            unset($attrList[BLOCS_DATA_EXIST]);
        }

        if (empty($resultArray)) {
            $htmlBuff = '';

            return;
        }

        $resultArray[] = '<!-- '.BLOCS_DATA_CHDIR.'="'.getcwd().'" '.BLOCS_DATA_QUERY.' -->';

        // conditionで挟み込み
        $condition = Condition::condition('', $attrList, $quotesList);
        if (!empty($condition)) {
            array_unshift($resultArray, $condition);
            $resultArray[] = BLOCS_ENDIF_SCRIPT;
        }

        $htmlArray = array_merge($resultArray, $htmlArray);

        // 引数を渡せるように
        $htmlBuff = self::assignValue($attrList, $quotesList, true);
    }

    private function addDataInclude($attrList, $htmlBuff)
    {
        $_ = fn ($s) => $s;
        eval("\$attrList[BLOCS_DATA_INCLUDE] = <<<EOS\n{$attrList[BLOCS_DATA_INCLUDE]}\nEOS;\n");

        if (!strncmp($attrList[BLOCS_DATA_INCLUDE], '/', 1) && !is_file($attrList[BLOCS_DATA_INCLUDE])) {
            // ルートディレクトリのパスを変換
            $attrList[BLOCS_DATA_INCLUDE] = BLOCS_ROOT_DIR.$attrList[BLOCS_DATA_INCLUDE];
        }

        if (!strlen($realpath = str_replace(DIRECTORY_SEPARATOR, '/', realpath($attrList[BLOCS_DATA_INCLUDE])))) {
            if (false !== ($resultBuff = $this->addAutoinclude($attrList, $htmlBuff))) {
                // data-includeができないのでauto includeしてみる
                return $resultBuff;
            }

            if (isset($attrList[BLOCS_DATA_EXIST])) {
                return [];
            }

            trigger_error('B003: Can not find template ('.getcwd().'/'.$attrList[BLOCS_DATA_INCLUDE].')', E_USER_ERROR);
        }

        if (count($this->include) > BLOCS_INCLUDE_MAX) {
            trigger_error('B004: Template loop error (over '.BLOCS_INCLUDE_MAX.')', E_USER_ERROR);
        }
        $this->include[] = $realpath;

        $autoincludeDir = self::getAutoincludeDir();
        if (false !== $autoincludeDir && !strncmp($realpath, $autoincludeDir, strlen($autoincludeDir))) {
            $autoinclude = pathinfo($realpath, PATHINFO_FILENAME);
            if (isset($this->autoincluded[$autoinclude])) {
                // auto includeは一回だけしかincludeしない
                return [];
            }
            $this->autoincluded[$autoinclude] = true;
        }

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
        return [
            '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->",
            $htmlBuff,
        ];
    }
}
