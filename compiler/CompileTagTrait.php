<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Cache\Condition;
use Blocs\Compiler\Cache\Form;
use Blocs\Compiler\Cache\Repeat;
use Blocs\Compiler\Cache\Val;

trait CompileTagTrait
{
    // コメントタグをパーシングしてコメント記法を処理
    private function compileTag($htmlBuff, &$htmlArray)
    {
        $tagName = $htmlBuff['tag'];
        $attrList = $htmlBuff['attribute'];
        $quotesList = $htmlBuff['quotes'];
        $type = isset($attrList['type']) ? strtolower($attrList['type']) : '';

        // データ属性削除
        // タグのコンパイル後の文字列
        $compiledTag = self::deleteDataAttribute($htmlBuff['raw'], $attrList);

        // ラベルを取得
        if (isset($this->optionArray['label'])) {
            if ('/option' === $tagName) {
                $this->optionArray['label'] = trim($this->optionArray['label']);
                if (!isset($this->optionArray['value'])) {
                    $this->optionArray['value'] = $this->optionArray['label'];
                }

                $this->option[] = $this->optionArray;
                $this->optionArray = [];
            } else {
                $this->optionArray['label'] .= $compiledTag;
            }
        }

        if (isset($this->labelArray['label'])) {
            if ('/label' === $tagName) {
                preg_match('/<br>$/si', $this->labelArray['label']) && $this->labelArray['label'] = substr($this->labelArray['label'], 0, -4);
                preg_match('/<br \/>$/si', $this->labelArray['label']) && $this->labelArray['label'] = substr($this->labelArray['label'], 0, -6);
                $this->labelArray['label'] = trim($this->labelArray['label']);

                (count($this->labelArray) > 2) ? $this->option[] = $this->labelArray : array_unshift($this->option, $this->labelArray);

                isset($this->labelArray['id']) && strlen($this->labelArray['label']) && $this->label[$this->labelArray['id']] = $this->labelArray['label'];
                $this->labelArray = [];
            } elseif ('input' === $tagName) {
                // ラベルに含めない
            } else {
                $this->labelArray['label'] .= $compiledTag;
            }
        }

        // タグ記法のためのカウンター
        $endTagCounterList = $this->checkTagCounter($tagName);

        // カウンターの終了処理
        foreach ($endTagCounterList as $endTagCounter) {
            empty($endTagCounter['before']) || $compiledTag = $endTagCounter['before'].$compiledTag;
            empty($endTagCounter['after']) || array_unshift($htmlArray, $endTagCounter['after']);

            if (!isset($endTagCounter['type'])) {
                continue;
            }

            'ignore' === $endTagCounter['type'] && $this->ignoreFlg = false;

            if ('part' === $endTagCounter['type'] && $this->isPart()) {
                // タグ記法でのdata-part終了処理
                $this->partInclude[$this->partName][] = $compiledTag;
                $this->partName = '';
                $compiledTag = '';
            }
        }

        if ($this->ignoreFlg) {
            return '';
        }

        // ブロックごとにタグを保持
        if ($this->isPart()) {
            $this->partInclude[$this->partName][] = $htmlBuff;

            return '';
        }

        // data_attributeをタグに反映
        $compiledTag = $this->mergeDataAttribute($compiledTag, $attrList);

        /* タグ記法のデータ属性処理 */

        if (isset($attrList[BLOCS_DATA_PART])) {
            // タグ記法でのdata-part開始処理
            $this->partName = $attrList[BLOCS_DATA_PART];
            $this->partInclude[$this->partName] = [$compiledTag];

            $this->setTagCounter([
                'tag' => $tagName,
                'type' => 'part',
            ]);

            return '';
        }

        if (isset($attrList[BLOCS_DATA_VAL])) {
            $tagCounter = [];
            Val::val($attrList, $quotesList, $this->dataAttribute, $tagName, $tagCounter, $htmlArray);
            count($tagCounter) && $this->setTagCounter($tagCounter);
        }

        if (isset($attrList[BLOCS_DATA_NOTICE])) {
            $tagCounter = [];
            Val::notice($attrList, $quotesList, $this->dataAttribute, $tagName, $tagCounter, $htmlArray);
            count($tagCounter) && $this->setTagCounter($tagCounter);
        }

        if (isset($attrList[BLOCS_DATA_EXIST]) || isset($attrList[BLOCS_DATA_NONE]) || isset($attrList[BLOCS_DATA_IF]) || isset($attrList[BLOCS_DATA_UNLESS])) {
            $tagCounter = [];
            $compiledTag = Condition::condition($compiledTag, $attrList, $quotesList, $tagName, $tagCounter, $htmlArray);
            count($tagCounter) && $this->setTagCounter($tagCounter);
        }

        // data-repeatとdata-loopの処理を共通化
        isset($attrList[BLOCS_DATA_REPEAT]) && $attrList[BLOCS_DATA_LOOP] = $attrList[BLOCS_DATA_REPEAT];

        if (isset($attrList[BLOCS_DATA_LOOP])) {
            // loop内のform名を置換するか
            isset($attrList[BLOCS_DATA_FORM]) && $this->arrayFormName = $attrList[BLOCS_DATA_FORM];

            if (!Common::checkValueName($attrList[BLOCS_DATA_LOOP])) {
                trigger_error('B002: Invalid condition "'.BLOCS_DATA_LOOP.'" ('.$attrList[BLOCS_DATA_LOOP].')', E_USER_ERROR);
            }

            $compiledTag = Repeat::loop($attrList, count($this->tagCounter)).$compiledTag;

            $this->setTagCounter([
                'tag' => $tagName,
                'after' => Repeat::endloop($attrList),
                'array_form' => substr($attrList[BLOCS_DATA_LOOP], 1),
            ]);
        }

        if (isset($attrList[BLOCS_DATA_FILTER]) && isset($attrList['name']) && strlen($attrList['name'])) {
            foreach (explode('|', $attrList[BLOCS_DATA_FILTER]) as $buff) {
                isset($this->filter[$attrList['name']]) || $this->filter[$attrList['name']] = '';
                $this->filter[$attrList['name']] .= $this->generateFilter($buff);
            }
        }

        // スクリプトの中のタグを無効化
        if ('script' === $tagName || 'style' === $tagName) {
            ++$this->scriptCounter;
        } elseif ('/script' === $tagName || '/style' === $tagName) {
            --$this->scriptCounter;
        }

        if ($this->scriptCounter > 0) {
            return $compiledTag;
        }

        /* フォーム部品の処理 */

        if ('input' === $tagName && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if (false !== $formName) {
                $attrList['name'] = $formName;
                count($this->labelArray) && $this->labelArray = array_merge($this->labelArray, $attrList);

                if (('radio' === $type || 'checkbox' === $type) && isset($attrList['value'])) {
                    !count($this->labelArray) && isset($attrList['id']) && $this->option[] = $attrList;

                    $selected = (isset($attrList['checked']) ? 'true' : 'false');
                    $compiledTag = Form::check($compiledTag, $attrList['name'], $attrList['value'], 'checked', $selected);

                    $this->generateDummyForm($attrList['name'], $compiledTag);
                }
                if (in_array($type, ['text', 'hidden', 'search', 'tel', 'url', 'email', 'datetime', 'date', 'month', 'week', 'time', 'datetime-local', 'number', 'range', 'color'])) {
                    $compiledTag = Form::value($compiledTag, $attrList);
                }
                if ('text' === $type && isset($attrList['class'])) {
                    $classList = preg_split("/\s/", $attrList['class']);
                    in_array(BLOCS_CLASS_UPLOAD, $classList) && $this->validateUpload[] = $attrList['name'];
                }
            }
        }

        if ('select' === $tagName && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if (false !== $formName) {
                $attrList['name'] = $formName;
                $this->selectName = $attrList['name'];

                isset($attrList['multiple']) && $this->generateDummyForm($attrList['name'], $compiledTag);
            }
        } elseif ('option' === $tagName && strlen($this->selectName) && isset($attrList['value'])) {
            $this->optionArray = $attrList;
            $this->optionArray['type'] = 'select';
            $this->optionArray['name'] = $this->selectName;
            isset($this->optionArray['label']) || $this->optionArray['label'] = '';

            $selected = (isset($attrList['selected']) ? 'true' : 'false');
            $compiledTag = Form::check($compiledTag, $this->selectName, $attrList['value'], 'selected', $selected);
        } elseif ('/select' === $tagName && strlen($this->selectName)) {
            // メニューのグループタグを追加
            Form::select($compiledTag, $htmlArray, $this->selectName);
            $this->selectName = '';

            return '';
        }

        if ('textarea' === $tagName && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if (false !== $formName) {
                $attrList['name'] = $formName;

                $tagCounter = [];
                Form::value($compiledTag, $attrList, $tagName, $tagCounter, $htmlArray);
                count($tagCounter) && $this->setTagCounter($tagCounter);
            }
        }

        if ('label' === $tagName) {
            isset($attrList['for']) && $this->labelArray['id'] = $attrList['for'];
            $this->labelArray['label'] = '';
        }

        // formのidを取得
        if ((isset($attrList['id']) || isset($attrList['for'])) && $arrayPath = $this->generateArrayFormName(1)) {
            isset($attrList['id']) && false === strpos($attrList['id'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrList['id'], $attrList, false);
            isset($attrList['for']) && false === strpos($attrList['for'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrList['for'], $attrList, false);
        }

        if (isset($attrList['class']) || isset($attrList['data-toggle'])) {
            $classList = [];
            if (isset($attrList['class'])) {
                $classNameList = preg_split("/\s/", $attrList['class']);
                foreach ($classNameList as $className) {
                    list($className) = preg_split("/\<\?php/", $className, 2);
                    if (!strncmp($className, 'ai-', 3)) {
                        $classList[] = substr($className, 3);
                    }
                }
            }

            isset($attrList['data-toggle']) && $classList[] = $attrList['data-toggle'];

            // auto includeの候補に追加
            $this->autoincludeClass = array_merge($this->autoincludeClass, $classList);
        }

        if (('input' === $tagName || 'select' === $tagName || 'textarea' === $tagName) && isset($attrList['name']) && strlen($attrList['name'])) {
            if ($arrayForm = $this->generateArrayFormName()) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$attrList['name'].']', $attrList, false);
                $arrayPath = $this->generateArrayFormName(1);
                $arrayMsg = $this->generateArrayFormName(2);
            } else {
                $arrayPath = $arrayMsg = '';
            }
            $arrayMsg .= "['{$attrList['name']}']";

            if (isset($attrList[BLOCS_DATA_VALIDATE])) {
                // バリデーションを設定
                foreach (explode('|', $attrList[BLOCS_DATA_VALIDATE]) as $validate) {
                    $this->validate[$attrList['name']][] = $validate;
                }
            }

            // HTML5のフォームバリデーション対応
            self::addHtml5Validation($this->validate, $attrList);
        }

        return $compiledTag;
    }

    /* タグ記法カウンターのメソッド */

    private function setTagCounter($tagCounter, $unshift = true)
    {
        isset($tagCounter['type']) && 'ignore' === $tagCounter['type'] && $this->ignoreFlg = true;

        if (!$unshift) {
            $this->tagCounter[] = $tagCounter;

            return;
        }

        array_unshift($this->tagCounter, $tagCounter);
    }

    private function checkTagCounter($tagName)
    {
        $endTagCounterList = [];

        foreach ($this->tagCounter as $num => $tagCounter) {
            isset($tagCounter['num']) || $this->tagCounter[$num]['num'] = 1;
            $tagName === $tagCounter['tag'] && $this->tagCounter[$num]['num']++;
            $tagName === '/'.$tagCounter['tag'] && $this->tagCounter[$num]['num']--;

            if ($this->tagCounter[$num]['num']) {
                // カウントが残っている時は何もしない
                continue;
            }

            $endTagCounterList[] = $tagCounter;
            unset($this->tagCounter[$num]);
        }
        $this->tagCounter = array_merge($this->tagCounter);

        return $endTagCounterList;
    }

    private static function deleteDataAttribute($rawString, $attrList)
    {
        foreach (self::$allAttrName as $attrName) {
            if (!isset($attrList[$attrName])) {
                continue;
            }

            $rawString = preg_replace('/\s+'.$attrName.'\s*=\s*["\']{0,1}'.str_replace('/', '\/', preg_quote($attrList[$attrName])).'["\']{0,1}([\s>\/]+)/si', '${1}', $rawString);
            $rawString = preg_replace('/\s+'.$attrName.'([\s>\/]+)/si', '${1}', $rawString);
        }

        return $rawString;
    }

    private function mergeDataAttribute($compiledTag, &$attrList)
    {
        // data-attributeで属性書き換え
        if (!isset($this->dataAttribute)) {
            return $compiledTag;
        }

        $noValue = [];
        $dataAttribute = [];
        foreach ($this->dataAttribute as $buff) {
            isset($dataAttribute[$buff['name']]) || $dataAttribute[$buff['name']] = '';
            if (isset($buff['value'])) {
                $dataAttribute[$buff['name']] .= $buff['value'];
            } else {
                $noValue[$buff['name']] = true;
            }
        }
        unset($this->dataAttribute);

        foreach ($dataAttribute as $name => $value) {
            $compiledTag = Common::mergeAttribute($compiledTag, $name, $value, $attrList, true, isset($noValue[$name]));
        }

        return $compiledTag;
    }

    private function generateArrayFormName($format = 0)
    {
        /*
            $format = 0(HTML form): matrix[<?php echo($repeatIndex); ?>]
            $format = 1(HTML id): matrix_<?php echo($repeatIndex); ?>
            $format = 2(PHP array): ['matrix'][$repeatIndex]
            $format = 3(Laravel validate): matrix.*.
        */

        if (empty($this->tagCounter) || (isset($this->arrayFormName) && !$this->arrayFormName)) {
            return '';
        }

        $formName = '';
        foreach (array_reverse($this->tagCounter) as $num => $buff) {
            if (!isset($buff['array_form']) || !strncmp($buff['array_form'], 'option_', 7)) {
                continue;
            }

            if (1 === $format) {
                if ($formName) {
                    $formName .= '_'.$buff['array_form'];
                } else {
                    $formName = $buff['array_form'];
                }
            } elseif (2 === $format) {
                $formName .= "['{$buff['array_form']}']";
            } elseif (3 === $format) {
                $formName .= "{$buff['array_form']}.*.";
            } else {
                if ($formName) {
                    $formName .= '['.$buff['array_form'].']';
                } else {
                    $formName = $buff['array_form'];
                }
            }

            if (1 === $format) {
                $formName .= "_<?php echo(\$repeatIndex{$num}); ?>";
            } elseif (2 === $format) {
                $formName .= '[$repeatIndex'.$num.']';
            } else {
                $formName .= "[<?php echo(\$repeatIndex{$num}); ?>]";
            }
        }

        return $formName;
    }

    private function generateDummyForm($attrName, &$rawString)
    {
        if ($dummyForm = $this->generateArrayFormName()) {
            $dummyForm .= '['.$attrName.']';
            $dummyMsg = $this->generateArrayFormName(2)."['{$attrName}']";
        } else {
            $dummyForm = $attrName;
            $dummyMsg = "['{$attrName}']";
        }

        if (in_array($dummyForm, $this->dummyArray)) {
            return;
        }

        $dummyBuff = "<?php if(!isset(\$dummyArray{$dummyMsg})): ?>\n";
        $dummyBuff .= "<input type='hidden' name='{$dummyForm}' value='' />";
        $dummyBuff .= "<?php \$dummyArray{$dummyMsg} = true; ?>\n";
        $dummyBuff .= BLOCS_ENDIF_SCRIPT;

        $rawString = $dummyBuff.$rawString;
        $this->dummyArray[] = $dummyForm;
    }

    private function generateFilter($filter)
    {
        list($filterClass, $filterFunc, $filterArg) = Common::checkFunc($filter);
        $filterFunc = self::findFilterFunc($filterClass, $filterFunc);

        return "\$value = {$filterFunc}(\$value{$filterArg});\n";
    }

    private static function findFilterFunc($filterClass, $filterFunc)
    {
        if ($filterClass && method_exists($filterClass, $filterFunc)) {
            return $filterClass.'::'.$filterFunc;
        }
        if (method_exists('\Blocs\Data\Filter', $filterFunc)) {
            return '\Blocs\Data\Filter::'.$filterFunc;
        }
        if (function_exists($filterFunc)) {
            return $filterFunc;
        }

        trigger_error('B010: Can not find filter function ('.$filterFunc.')', E_USER_ERROR);
    }

    // HTML5のフォームバリデーション対応
    private function addHtml5Validation(&$dataValidate, $attrList)
    {
        $attrList['name'] = $this->generateArrayFormName(3).$attrList['name'];

        if (isset($attrList['required'])) {
            $dataValidate[$attrList['name']][] = 'required';
        }
        if (isset($attrList['maxlength'])) {
            $dataValidate[$attrList['name']][] = 'string';
            $dataValidate[$attrList['name']][] = 'max:'.$attrList['maxlength'];
        }
        if (isset($attrList['minlength'])) {
            $dataValidate[$attrList['name']][] = 'string';
            $dataValidate[$attrList['name']][] = 'min:'.$attrList['minlength'];
        }
        if (isset($attrList['max'])) {
            if (isset($attrList['step'])) {
                $dataValidate[$attrList['name']][] = 'numeric';
            } else {
                $dataValidate[$attrList['name']][] = 'integer';
            }
            $dataValidate[$attrList['name']][] = 'max:'.$attrList['max'];
        }
        if (isset($attrList['min'])) {
            if (isset($attrList['step'])) {
                $dataValidate[$attrList['name']][] = 'numeric';
            } else {
                $dataValidate[$attrList['name']][] = 'integer';
            }
            $dataValidate[$attrList['name']][] = 'min:'.$attrList['min'];
        }
        if (isset($attrList['pattern'])) {
            $dataValidate[$attrList['name']][] = 'regex:/'.$attrList['pattern'].'/';
        }
    }
}
