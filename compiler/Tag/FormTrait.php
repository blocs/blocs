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
                if (! empty($this->labelArray)) {
                    $this->labelArray = array_merge($this->labelArray, $attrList);
                }

                if (($type === 'radio' || $type === 'checkbox') && isset($attrList['value'])) {
                    if (empty($this->labelArray) && isset($attrList['id'])) {
                        $this->option[] = $attrList;
                    }

                    $selected = (isset($attrList['checked']) ? 'true' : 'false');
                    $compiledTag = Form::check($compiledTag, $attrList['name'], $attrList['value'], 'checked', $selected);

                    $this->ensureDummyFormField($attrList['name'], $compiledTag);
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

                if (isset($attrList['multiple'])) {
                    $this->ensureDummyFormField($attrList['name'], $compiledTag);
                }
            }
        } elseif ($tagName === 'option' && strlen($this->selectName) && isset($attrList['value'])) {
            $this->optionArray = $attrList;
            $this->optionArray['type'] = 'select';
            $this->optionArray['name'] = $this->selectName;
            if (! isset($this->optionArray['label'])) {
                $this->optionArray['label'] = '';
            }

            $selected = (isset($attrList['selected']) ? 'true' : 'false');
            $compiledTag = Form::check($compiledTag, $this->selectName, $attrList['value'], 'selected', $selected);
        } elseif ($tagName === '/select' && strlen($this->selectName)) {
            // メニューのグループタグを追加する処理を実行する
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
                if (! empty($tagCounter)) {
                    $this->registerTagCounter($tagCounter);
                }
            }
        }

        // formのid属性をループ構造に合わせた形式へ整形する
        if ((isset($attrList['id']) || isset($attrList['for'])) && $arrayPath = $this->buildArrayFormName(1)) {
            if (isset($attrList['id']) && strpos($attrList['id'], '<?php') === false) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrList['id'], $attrList);
            }
            if (isset($attrList['for']) && strpos($attrList['for'], '<?php') === false) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrList['for'], $attrList);
            }
        }

        if (($tagName === 'input' || $tagName === 'select' || $tagName === 'textarea') && isset($attrList['name']) && strlen($attrList['name'])) {
            if ($arrayForm = $this->buildArrayFormName()) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$attrList['name'].']', $attrList);
                $arrayPath = $this->buildArrayFormName(1);
                $arrayMsg = $this->buildArrayFormName(2);
            } else {
                $arrayPath = $arrayMsg = '';
            }
            $arrayMsg .= "['{$attrList['name']}']";

            if (isset($attrList[BLOCS_DATA_VALIDATE])) {
                // バリデーション設定を蓄積する
                foreach (explode('|', $attrList[BLOCS_DATA_VALIDATE]) as $validate) {
                    $this->validate[$attrList['name']][] = $validate;
                }
            }

            // HTML5のフォームバリデーションに対応する
            self::addHtml5Validation($this->validate, $attrList);
        }
    }

    private function buildArrayFormName($format = 0)
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

    private function ensureDummyFormField($attrName, &$rawString)
    {
        if ($dummyForm = $this->buildArrayFormName()) {
            $dummyForm .= '['.$attrName.']';
            $dummyMsg = $this->buildArrayFormName(2)."['{$attrName}']";
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

    // HTML5属性からバリデーションルールを組み立てる
    private function addHtml5Validation(&$dataValidate, $attrList)
    {
        $attrList['name'] = $this->buildArrayFormName(3).$attrList['name'];

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
