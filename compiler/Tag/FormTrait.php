<?php

namespace Blocs\Compiler\Tag;

use Blocs\Compiler\Cache\Common;

trait FormTrait
{
    private function compileTagForm($htmlBuff, &$htmlArray, $attrList, &$compiledTag)
    {
        $tagName = $htmlBuff['tag'];
        $quotesList = $htmlBuff['quotes'];
        $type = isset($attrList['type']) ? strtolower($attrList['type']) : '';

        if ($tagName === 'input' && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if ($formName !== false) {
                $attrList['name'] = $formName;
                count($this->labelArray) && $this->labelArray = array_merge($this->labelArray, $attrList);

                if (($type === 'radio' || $type === 'checkbox') && isset($attrList['value'])) {
                    ! count($this->labelArray) && isset($attrList['id']) && $this->option[] = $attrList;

                    $selected = (isset($attrList['checked']) ? 'true' : 'false');
                    $compiledTag = Form::check($compiledTag, $attrList['name'], $attrList['value'], 'checked', $selected);

                    $this->generateDummyForm($attrList['name'], $compiledTag);
                }
                if (in_array($type, ['text', 'hidden', 'search', 'tel', 'url', 'email', 'datetime', 'date', 'month', 'week', 'time', 'datetime-local', 'number', 'range', 'color'])) {
                    $compiledTag = Form::value($compiledTag, $attrList);
                }
                if ($type === 'hidden' && isset($attrList['class'])) {
                    $classList = [];
                    if (isset($attrList['class'])) {
                        $classNameList = preg_split("/\s/", $attrList['class']);
                        foreach ($classNameList as $className) {
                            [$className] = preg_split("/\<\?php/", $className, 2);
                            if (! strncmp($className, 'ai-', 3)) {
                                $classList[] = substr($className, 3);
                            }
                        }
                    }

                    in_array(substr(BLOCS_CLASS_UPLOAD, 3), $classList) && $this->validateUpload[] = $attrList['name'];
                }
            }
        }

        if ($tagName === 'select' && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if ($formName !== false) {
                $attrList['name'] = $formName;
                $this->selectName = $attrList['name'];

                isset($attrList['multiple']) && $this->generateDummyForm($attrList['name'], $compiledTag);
            }
        } elseif ($tagName === 'option' && strlen($this->selectName) && isset($attrList['value'])) {
            $this->optionArray = $attrList;
            $this->optionArray['type'] = 'select';
            $this->optionArray['name'] = $this->selectName;
            isset($this->optionArray['label']) || $this->optionArray['label'] = '';

            $selected = (isset($attrList['selected']) ? 'true' : 'false');
            $compiledTag = Form::check($compiledTag, $this->selectName, $attrList['value'], 'selected', $selected);
        } elseif ($tagName === '/select' && strlen($this->selectName)) {
            // メニューのグループタグを追加
            Form::select($compiledTag, $htmlArray, $this->selectName);

            $compiledTag = '';

            return;
        }

        if ($tagName === 'textarea' && isset($attrList['name']) && strlen($attrList['name'])) {
            $formName = Common::checkFormName($attrList['name']);
            if ($formName !== false) {
                $attrList['name'] = $formName;

                $tagCounter = [];
                Form::value($compiledTag, $attrList, $tagName, $tagCounter, $htmlArray);
                count($tagCounter) && $this->setTagCounter($tagCounter);
            }
        }

        // formのidを取得
        if ((isset($attrList['id']) || isset($attrList['for'])) && $arrayPath = $this->generateArrayFormName(1)) {
            isset($attrList['id']) && strpos($attrList['id'], '<?php') === false && $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrList['id'], $attrList);
            isset($attrList['for']) && strpos($attrList['for'], '<?php') === false && $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrList['for'], $attrList);
        }

        if (($tagName === 'input' || $tagName === 'select' || $tagName === 'textarea') && isset($attrList['name']) && strlen($attrList['name'])) {
            if ($arrayForm = $this->generateArrayFormName()) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$attrList['name'].']', $attrList);
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
    }

    private function generateArrayFormName($format = 0)
    {
        /*
            $format = 0(HTML form): matrix[<?php echo($loopIndex); ?>]
            $format = 1(HTML id): matrix_<?php echo($loopIndex); ?>
            $format = 2(PHP array): ['matrix'][$loopIndex]
            $format = 3(Laravel validate): matrix.*.
        */

        if (empty($this->tagCounter) || ! empty($this->arrayFormName)) {
            return '';
        }

        $formName = '';
        foreach (array_reverse($this->tagCounter) as $num => $buff) {
            if (! isset($buff['array_form']) || ! strncmp($buff['array_form'], 'option_', 7)) {
                continue;
            }

            if ($format === 1) {
                if ($formName) {
                    $formName .= '_'.$buff['array_form'];
                } else {
                    $formName = $buff['array_form'];
                }
            } elseif ($format === 2) {
                $formName .= "['{$buff['array_form']}']";
            } elseif ($format === 3) {
                $formName .= "{$buff['array_form']}.*.";
            } else {
                if ($formName) {
                    $formName .= '['.$buff['array_form'].']';
                } else {
                    $formName = $buff['array_form'];
                }
            }

            if ($format === 1) {
                $formName .= "_<?php echo(\$loopIndex{$num}); ?>";
            } elseif ($format === 2) {
                $formName .= '[$loopIndex'.$num.']';
            } else {
                $formName .= "[<?php echo(\$loopIndex{$num}); ?>]";
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

    // HTML5のフォームバリデーション対応
    private function addHtml5Validation(&$dataValidate, $attrList)
    {
        $attrList['name'] = $this->generateArrayFormName(3).$attrList['name'];

        if (isset($attrList['required'])) {
            $dataValidate[$attrList['name']][] = 'required';
            $required = true;
        }

        if (isset($attrList['minlength']) || isset($attrList['maxlength'])) {
            isset($required) || $dataValidate[$attrList['name']][] = 'nullable';
            $dataValidate[$attrList['name']][] = 'string';

            isset($attrList['minlength']) && $dataValidate[$attrList['name']][] = 'min:'.$attrList['minlength'];
            isset($attrList['maxlength']) && $dataValidate[$attrList['name']][] = 'max:'.$attrList['maxlength'];
        }

        if (isset($attrList['type']) && $attrList['type'] === 'number') {
            if (isset($attrList['min']) && isset($attrList['max'])) {
                isset($required) || $dataValidate[$attrList['name']][] = 'nullable';
                if (isset($attrList['step'])) {
                    $dataValidate[$attrList['name']][] = 'numeric';
                } else {
                    $dataValidate[$attrList['name']][] = 'integer';
                }

                isset($attrList['min']) && $dataValidate[$attrList['name']][] = 'min:'.$attrList['min'];
                isset($attrList['max']) && $dataValidate[$attrList['name']][] = 'max:'.$attrList['max'];
            }
        }

        if (isset($attrList['pattern'])) {
            isset($required) || $dataValidate[$attrList['name']][] = 'nullable';
            $dataValidate[$attrList['name']][] = 'regex:/'.$attrList['pattern'].'/';
        }
    }
}
