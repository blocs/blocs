<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait CompileTagTrait
{
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

    private function generateDummyForm($attrName, &$rawString, &$dummyArray)
    {
        if ($dummyForm = $this->generateArrayFormName()) {
            $dummyForm .= '['.$attrName.']';
            $dummyMsg = $this->generateArrayFormName(2)."['{$attrName}']";
        } else {
            $dummyForm = $attrName;
            $dummyMsg = "['{$attrName}']";
        }

        if (isset($dummyArray[$dummyForm])) {
            return;
        }

        $dummyBuff = "<?php if(!isset(\$dummyArray{$dummyMsg})): ?>\n";
        $dummyBuff .= "<input type='hidden' name='{$dummyForm}' value='' />";
        $dummyBuff .= "<?php \$dummyArray{$dummyMsg} = true; ?>\n";
        $dummyBuff .= BLOCS_ENDIF_SCRIPT;

        $rawString = $dummyBuff.$rawString;
        $dummyArray[$dummyForm] = true;
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
