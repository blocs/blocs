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

            $compiledTag = '';
            return;
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

        // formのidを取得
        if ((isset($attrList['id']) || isset($attrList['for'])) && $arrayPath = $this->generateArrayFormName(1)) {
            isset($attrList['id']) && false === strpos($attrList['id'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrList['id'], $attrList, false);
            isset($attrList['for']) && false === strpos($attrList['for'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrList['for'], $attrList, false);
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
