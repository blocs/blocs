<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Cache\Condition;
use Blocs\Compiler\Cache\Repeat;
use Blocs\Compiler\Cache\Val;

trait CompileCommentTrait
{
    // コメントタグをパーシングしてコメント記法を処理
    private function compileComment(&$htmlBuff, &$htmlArray)
    {
        // コメントタグをパース
        list($includeBuff) = Parser::parse($htmlBuff, true);
        if (!isset($includeBuff['attribute'])) {
            return;
        }

        $rawString = $includeBuff['raw'];
        $attrList = $includeBuff['attribute'];
        $quotesList = $includeBuff['quotes'];

        // 変数の代入だけの時は簡単に記述できるように
        $isAssignValue = true;
        foreach ($attrList as $key => $value) {
            if (!Common::checkValueName($key) && '--' !== $key) {
                $isAssignValue = false;
                break;
            }

            if (2 == count($attrList) && Common::checkValueName($key) && !strlen($value) && empty($quotesList[$key])) {
                // data-valの省略表記
                $attrList[BLOCS_DATA_VAL] = $key;
                unset($attrList[$key]);

                $isAssignValue = false;
                break;
            }
        }

        if ($isAssignValue) {
            $htmlBuff = self::assignValue($attrList, $quotesList);

            return;
        }

        /* コメント記法のデータ属性処理 */

        if (isset($attrList[BLOCS_DATA_PART])) {
            // コメント記法でのdata-part開始処理
            ++$this->partDepth;

            if (1 === $this->partDepth) {
                // ブロック処理開始
                $this->partName = $attrList[BLOCS_DATA_PART];
                $this->partInclude[$this->partName] = [];
                $htmlBuff = '';
            }

            return;
        }
        if (isset($attrList[BLOCS_DATA_ENDPART])) {
            // コメント記法でのdata-part終了処理
            --$this->partDepth;
            $this->partDepth < 0 && $this->partDepth = 0;

            if (0 === $this->partDepth) {
                // ブロック処理終了
                $this->partName = '';
                $htmlBuff = '';
            }

            return;
        }
        // ブロック処理中なので後続の処理は不要
        if ($this->isPart()) {
            return;
        }

        if (isset($attrList[BLOCS_DATA_CHDIR])) {
            chdir($attrList[BLOCS_DATA_CHDIR]);
            $htmlBuff = '';

            // 引数継承のために属性値を保持
            isset($attrList[BLOCS_DATA_QUERY]) && array_pop(self::$assignedValue);

            return;
        }

        if (isset($attrList[BLOCS_DATA_INCLUDE])) {
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

            return;
        }

        if (isset($attrList[BLOCS_DATA_VAL])) {
            $htmlBuff = Val::val($attrList, $quotesList, $this->dataAttribute);
        }
        if (isset($attrList[BLOCS_DATA_NOTICE])) {
            $htmlBuff = Val::notice($attrList, $quotesList, $this->dataAttribute);
        }

        // data-attributeだけが設定されている時の処理
        if (isset($attrList[BLOCS_DATA_ATTRIBUTE]) && !isset($attrList[BLOCS_DATA_VAL])) {
            $condition = Condition::condition($attrList[BLOCS_DATA_ATTRIBUTE], $attrList, $quotesList);
            unset($attrList[BLOCS_DATA_EXIST], $attrList[BLOCS_DATA_NONE], $attrList[BLOCS_DATA_IF], $attrList[BLOCS_DATA_UNLESS]);

            if ($condition === $attrList[BLOCS_DATA_ATTRIBUTE]) {
                $this->dataAttribute[] = [
                    'name' => $attrList[BLOCS_DATA_ATTRIBUTE],
                ];
            } else {
                $this->dataAttribute[] = [
                    'name' => $condition.BLOCS_ENDIF_SCRIPT,
                ];
            }

            $htmlBuff = '';
        }

        if (isset($attrList[BLOCS_DATA_VALIDATE]) && isset($attrList[BLOCS_DATA_FORM])) {
            self::checkDataValidate($this->validate, $attrList[BLOCS_DATA_FORM], $attrList[BLOCS_DATA_VALIDATE]) && $this->validate[$attrList[BLOCS_DATA_FORM]][] = $attrList[BLOCS_DATA_VALIDATE];

            if (isset($attrList[BLOCS_DATA_NOTICE])) {
                $validateMethod = self::getValidateMethod($attrList[BLOCS_DATA_VALIDATE]);
                $this->validateMessage[$attrList[BLOCS_DATA_FORM]][$validateMethod] = $attrList[BLOCS_DATA_NOTICE];
            }
            $htmlBuff = '';

            return;
        }

        if (isset($attrList[BLOCS_DATA_EXIST]) || isset($attrList[BLOCS_DATA_NONE]) || isset($attrList[BLOCS_DATA_IF]) || isset($attrList[BLOCS_DATA_UNLESS])) {
            if (isset($attrList[BLOCS_DATA_VAL]) || isset($attrList[BLOCS_DATA_NOTICE])) {
                if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
                    $rawString = $this->dataAttribute[count($this->dataAttribute) - 1]['value'];
                } else {
                    $rawString = $htmlBuff;
                }
            } else {
                $rawString = '';
            }

            $htmlBuff = Condition::condition($rawString, $attrList, $quotesList);

            if (isset($attrList[BLOCS_DATA_VAL]) || isset($attrList[BLOCS_DATA_NOTICE])) {
                $htmlBuff .= BLOCS_ENDIF_SCRIPT;
                if (isset($attrList[BLOCS_DATA_ATTRIBUTE])) {
                    $this->dataAttribute[count($this->dataAttribute) - 1]['value'] = $htmlBuff;
                    $htmlBuff = '';
                }
            }
        }
        if (isset($attrList[BLOCS_DATA_ENDEXIST]) || isset($attrList[BLOCS_DATA_ENDNONE]) || isset($attrList[BLOCS_DATA_ENDIF]) || isset($attrList[BLOCS_DATA_ENDUNLESS])) {
            $htmlBuff = BLOCS_ENDIF_SCRIPT;
        }

        // data-repeatとdata-loopの処理を共通化
        isset($attrList[BLOCS_DATA_REPEAT]) && $attrList[BLOCS_DATA_LOOP] = $attrList[BLOCS_DATA_REPEAT];
        isset($attrList[BLOCS_DATA_ENDREPEAT]) && $attrList[BLOCS_DATA_ENDLOOP] = $attrList[BLOCS_DATA_ENDREPEAT];

        if (isset($attrList[BLOCS_DATA_LOOP])) {
            // loop内のform名を置換するか
            isset($attrList[BLOCS_DATA_FORM]) && $this->arrayFormName = $attrList[BLOCS_DATA_FORM];

            $rawString = '';
            $htmlBuff = Repeat::loop($attrList, count($this->tagCounter));
            $this->endrepeat[] = $attrList;

            $this->setTagCounter([
                'tag' => BLOCS_DATA_LOOP,
                'array_form' => substr($attrList[BLOCS_DATA_LOOP], 1),
            ], false);
        }
        if (isset($attrList[BLOCS_DATA_ENDLOOP]) && !empty($this->endrepeat)) {
            $htmlBuff = Repeat::endloop(array_pop($this->endrepeat));

            $target = '';
            foreach ($this->tagCounter as $num => $buff) {
                if (!isset($buff['array_form']) || BLOCS_DATA_LOOP !== $buff['tag']) {
                    continue;
                }
                $target = $num;
            }
            if (strlen($target)) {
                unset($this->tagCounter[$target]);
                $this->tagCounter = array_merge($this->tagCounter);
            }
        }
    }

    /* data-includeのメソッド */

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

    // 変数を定義
    private static function assignValue($attrList, $quotesList, $assigned = false)
    {
        $htmlBuff = '';
        $assignedValue = [];
        $assigned && $assignedValue = count(self::$assignedValue) ? end(self::$assignedValue) : [];

        foreach ($attrList as $key => $value) {
            if (!Common::checkValueName($key)) {
                continue;
            }

            $quotes = empty($quotesList[$key]) ? '' : $quotesList[$key];
            $value = "{$quotes}{$value}{$quotes}";

            if (Common::checkValueName($value)) {
                // 変数代入は継承しない
                $assignedValue[$key] = "<?php isset({$value}) && {$key} = {$value}; ?>\n";
            } else {
                if (!isset($assignedValue[$key])) {
                    // 変数を継承する
                    $assignedValue[$key] = "<?php {$key} = {$value}; ?>\n";
                }
            }

            $htmlBuff .= $assignedValue[$key];
        }

        // 引数継承のために属性値を保持
        $assigned && self::$assignedValue[] = $assignedValue;

        return $htmlBuff;
    }

    // data-validateの重複を確認
    private static function checkDataValidate($checkValidate, $dataForm, $dataValidate)
    {
        if (empty($checkValidate[$dataForm])) {
            return true;
        }

        $validateMethod = self::getValidateMethod($dataValidate);
        foreach ($checkValidate[$dataForm] as $validate) {
            if (BlocsCompiler::getValidateMethod($validate) === $validateMethod) {
                return false;
            }
        }

        return true;
    }

    // バリデーションから引数を除く
    private static function getValidateMethod($dataValidate)
    {
        list($validateMethod) = explode(':', $dataValidate, 2);
        'maxlength' === $validateMethod && $validateMethod = 'max';
        'minlength' === $validateMethod && $validateMethod = 'min';

        return $validateMethod;
    }
}
