<?php

namespace Blocs\Compiler\Tag;

use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Cache\Condition;
use Blocs\Compiler\Cache\Loop;
use Blocs\Compiler\Cache\Val;

trait AttributeTrait
{
    private function compileTagAttribute($htmlBuff, &$htmlArray, $attrList, &$compiledTag)
    {
        $tagName = $htmlBuff['tag'];
        $quotesList = $htmlBuff['quotes'];

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

            $compiledTag = Loop::loop($attrList, count($this->tagCounter)).$compiledTag;

            $this->setTagCounter([
                'tag' => $tagName,
                'after' => Loop::endloop($attrList),
                'array_form' => substr($attrList[BLOCS_DATA_LOOP], 1),
            ]);
        }

        if (isset($attrList[BLOCS_DATA_FILTER]) && isset($attrList['name']) && strlen($attrList['name'])) {
            foreach (explode('|', $attrList[BLOCS_DATA_FILTER]) as $buff) {
                isset($this->filter[$attrList['name']]) || $this->filter[$attrList['name']] = '';
                $this->filter[$attrList['name']] .= $this->generateFilter($buff);
            }
        }
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
}
