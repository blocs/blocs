<?php

namespace Blocs\Compiler\Tag;

use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Parser;

class Form
{
    // フォーム部品に値をつける
    public static function value($compiledTag, &$attrList, $tagName = '', &$tagCounter = null, &$htmlArray = null)
    {
        $valueBuff = "<?php if(isset(\${$attrList['name']})): ?>\n";
        $valueBuff .= "<?php echo(htmlspecialchars(\${$attrList['name']}, ENT_QUOTES, 'UTF-8')); ?>\n";

        if ($tagName) {
            // textarea
            array_unshift($htmlArray, $valueBuff."<?php else: ?>\n");

            $tagCounter = [
                'tag' => $tagName,
                'before' => BLOCS_ENDIF_SCRIPT,
            ];

            return;
        }

        isset($attrList['value']) && $valueBuff .= "<?php else: ?>\n".$attrList['value'];
        $valueBuff .= BLOCS_ENDIF_SCRIPT;

        return Common::mergeAttribute($compiledTag, 'value', $valueBuff, $attrList);
    }

    // selectなどのフォーム部品にchecked、selectedをつける
    public static function check($compiledTag, $attrName, $attrValue, $checkFlg, $attrChecked)
    {
        if (isset($attrValue) && !strncmp($attrValue, '<?', 2)) {
            $valueBuff = '(isset($check_'.$attrName.') ? $check_'.$attrName.' : null)';
            $checkTag = str_replace('echo(', '$check_'.$attrName.' = (', $attrValue);
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
        $optionBuff = <<< END_of_HTML
<!-- \$preGroup=null -->
<!-- data-loop=\$option_{$selectName} -->

    <!-- data-if="!empty(\$preGroup) && (!isset(\$optgroup) || \$optgroup != \$preGroup)" -->
        </optgroup>
        <!-- \$preGroup=null -->
    <!-- data-endif -->

    <!-- data-if="isset(\$optgroup) && \$optgroup != \$preGroup" -->
        <optgroup :label=\$optgroup>
        <!-- \$preGroup=\$optgroup -->
    <!-- data-endif -->

    <option :value=\$value><!-- \$label --></option>

<!-- data-endloop -->

<!-- data-exist=\$preGroup -->
</optgroup>
<!-- data-endexist -->

END_of_HTML;

        $optionArray = Parser::parse($optionBuff);
        $optionArray[] = $compiledTag;
        $htmlArray = array_merge($optionArray, $htmlArray);
    }
}
