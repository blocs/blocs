<?php

namespace Blocs\Compiler\Cache;

class Form
{
    // フォーム部品に値をつける
    public static function value($compiledTag, &$attrArray, $tag = '', &$tagCounter = null, &$htmlArray = null)
    {
        $valueBuff = "<?php if(isset(\${$attrArray['name']})): ?>\n";
        $valueBuff .= "<?php echo(htmlspecialchars(\${$attrArray['name']}, ENT_QUOTES, 'UTF-8')); ?>\n";

        if ($tag) {
            // textarea
            array_unshift($htmlArray, $valueBuff."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tag,
                'before' => BLOCS_ENDIF_SCRIPT,
            ];

            return;
        }

        isset($attrArray['value']) && $valueBuff .= "<?php else: ?>\n".$attrArray['value'];
        $valueBuff .= BLOCS_ENDIF_SCRIPT;

        return Common::mergeAttribute($compiledTag, 'value', $valueBuff, $attrArray);
    }

    // selectなどのフォーム部品にchecked、selectedをつける
    public static function check($compiledTag, $attrName, $attrValue, $checkFlg, $attrChecked)
    {
        if (isset($attrValue) && !strncmp($attrValue, '<?', 2)) {
            $valueBuff = '(isset($work_'.$attrName.') ? $work_'.$attrName.' : null)';
            $checkTag = str_replace('echo(', '$work_'.$attrName.' = (', $attrValue);
        } else {
            $valueBuff = (isset($attrValue) ? Common::escapeDoubleQuote($attrValue) : 'null');
            $checkTag = '';
        }
        $checkTag .= "<?php \Blocs\Common::addChecked((isset(\${$attrName}) ? \${$attrName} : null), {$valueBuff}, {$attrChecked}, '{$checkFlg}'); ?>\n";

        $compiledTag = preg_replace('/\s+'.$checkFlg.'([\s>]+)/i', '${1}', $compiledTag);
        $compiledTag = preg_replace('/\s+'.$checkFlg.'\s*=\s*["\']{0,1}'.$checkFlg.'["\']{0,1}([\s>\/]+)/i', '${1}', $compiledTag);
        $compiledTag = preg_replace('/\s+'.$checkFlg.'\s*=\s*["\']{0,1}true["\']{0,1}([\s>\/]+)/i', '${1}', $compiledTag);

        if ('/>' === substr($compiledTag, -2)) {
            $compiledTag = rtrim(substr($compiledTag, 0, -2)).$checkTag.' />';
        } else {
            $compiledTag = rtrim(substr($compiledTag, 0, -1)).$checkTag.'>';
        }

        return $compiledTag;
    }

    // optionグループのHTMLを生成
    public static function select($compiledTag, &$htmlArray, $selectName)
    {
        $menuBuff = <<< END_of_HTML
<!-- data-query=\$preGroup data-val="" -->
<!-- data-repeat=\$menu_{$selectName} -->

    <!-- data-if="!empty(\$preGroup) && (!isset(\$optionGroupList) || \$optionGroupList != \$preGroup)" -->
        </optgroup>
        <!-- data-query=\$preGroup data-val="" -->
    <!-- data-endif -->

    <!-- data-if="isset(\$optionGroupList) && \$optionGroupList != \$preGroup" -->
        <!-- data-attribute="label" data-val=\$optionGroupList -->
        <optgroup>
        <!-- data-query=\$preGroup data-val=\$optionGroupList -->
    <!-- data-endif -->

    <!-- data-attribute="value" data-val=\$value -->
    <option data-val=\$label></option>

<!-- data-endrepeat -->

<!-- data-exist=\$preGroup -->
</optgroup>
<!-- data-endexist -->

END_of_HTML;

        $menuArray = \Blocs\Compiler\Parser::parse($menuBuff);
        $menuArray[] = $compiledTag;
        $htmlArray = array_merge($menuArray, $htmlArray);
    }
}
