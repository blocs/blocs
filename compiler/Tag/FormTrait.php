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
                    $classList = Common::extractAiClassNames($attrList['class']);
                    in_array(substr(BLOCS_CLASS_UPLOAD, 3), $classList) && $this->validateUpload[] = $attrList['name'];
                }
            }
        }

        if ($tagName === 'select') {
            // 名前のないselectで前のselect名が残らないように、開始タグで必ず入れ替える
            $this->selectName = '';

            if (isset($attrList['name']) && strlen($attrList['name'])) {
                $formName = Common::checkFormName($attrList['name']);
                if ($formName !== false) {
                    $attrList['name'] = $formName;
                    $this->selectName = $attrList['name'];

                    if (isset($attrList['multiple'])) {
                        $this->ensureDummyFormField($attrList['name'], $compiledTag);
                    }
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
            $formFieldName = $attrList['name'];

            if ($arrayForm = $this->buildArrayFormName()) {
                $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$formFieldName.']', $attrList);
            }

            $validateName = $this->buildArrayFormName(3).$formFieldName;

            if (isset($attrList[BLOCS_DATA_VALIDATE])) {
                // バリデーション設定を蓄積する
                foreach (explode('|', $attrList[BLOCS_DATA_VALIDATE]) as $validate) {
                    $this->validate[$validateName][] = $validate;
                }
            }

            // HTML5のフォームバリデーションに対応する
            $html5AttrList = $attrList;
            $html5AttrList['name'] = $formFieldName;
            self::addHtml5Validation($this->validate, $html5AttrList);
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
            } elseif ($format !== 3) {
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
        $normalized = [];
        foreach ($attrList as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }
        if (isset($normalized['type'])) {
            $normalized['type'] = strtolower((string) $normalized['type']);
        }

        $normalized['name'] = $this->buildArrayFormName(3).($normalized['name'] ?? $attrList['name']);

        if (isset($normalized['required'])) {
            $dataValidate[$normalized['name']][] = 'required';
            $required = true;
        }

        if (isset($normalized['minlength']) || isset($normalized['maxlength'])) {
            isset($required) || $dataValidate[$normalized['name']][] = 'nullable';
            $dataValidate[$normalized['name']][] = 'string';

            isset($normalized['minlength']) && $dataValidate[$normalized['name']][] = 'min:'.$normalized['minlength'];
            isset($normalized['maxlength']) && $dataValidate[$normalized['name']][] = 'max:'.$normalized['maxlength'];
        }

        if (isset($normalized['type']) && $normalized['type'] === 'number') {
            isset($required) || $dataValidate[$normalized['name']][] = 'nullable';
            $dataValidate[$normalized['name']][] = self::acceptsIntegerOnly($normalized['step'] ?? null) ? 'integer' : 'numeric';

            isset($normalized['min']) && $dataValidate[$normalized['name']][] = 'min:'.$normalized['min'];
            isset($normalized['max']) && $dataValidate[$normalized['name']][] = 'max:'.$normalized['max'];
        }

        if (isset($normalized['pattern'])) {
            isset($required) || $dataValidate[$normalized['name']][] = 'nullable';
            $dataValidate[$normalized['name']][] = 'regex:'.self::buildHtml5RegexRule($normalized['pattern']);
        }
    }

    /**
     * HTML5 の step から整数のみを受け付けるかを判定する。
     * ブラウザの step 判定（HTML Standard）に合わせ、小数を許容するのは "any" と正の小数 step のときだけ。
     * step 省略・空・解釈できない値・0以下は、いずれも既定の step=1 に戻るため整数のみになる。
     *
     * @param  string|int|float|null  $step
     */
    private static function acceptsIntegerOnly($step): bool
    {
        if (! isset($step)) {
            // step を書かない場合の既定は step=1
            return true;
        }

        $step = trim((string) $step);

        if (strcasecmp($step, 'any') === 0) {
            // "any" だけがステップの制約なし（大文字小文字は区別しない）
            return false;
        }

        if (! is_numeric($step)) {
            // 解釈できない値は既定の step=1 に戻る
            return true;
        }

        $step = (float) $step;

        if ($step <= 0) {
            // 0 以下も既定の step=1 に戻る
            return true;
        }

        // int へキャストすると巨大な step で桁あふれするため fmod で判定する
        return fmod($step, 1.0) === 0.0;
    }

    /**
     * HTML5 pattern を Laravel regex ルール用の正規表現へ変換する。
     * # 区切りにし、末尾の奇数個のバックスラッシュが区切り文字をエスケープしないようにする。
     */
    private static function buildHtml5RegexRule(string $pattern): string
    {
        $pattern = str_replace('#', '\\#', $pattern);
        $trailingBackslashes = strlen($pattern) - strlen(rtrim($pattern, '\\'));
        if ($trailingBackslashes % 2 === 1) {
            $pattern .= '\\';
        }

        return '#^(?:'.$pattern.')$#';
    }
}
