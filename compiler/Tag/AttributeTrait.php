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
            empty($tagCounter) || $this->registerTagCounter($tagCounter);
        }

        if (isset($attrList[BLOCS_DATA_NOTICE])) {
            $tagCounter = [];
            Val::notice($attrList, $this->dataAttribute, $tagName, $tagCounter, $htmlArray);
            empty($tagCounter) || $this->registerTagCounter($tagCounter);
        }

        if (Condition::has($attrList)) {
            $tagCounter = [];
            $compiledTag = Condition::condition($compiledTag, $attrList, $quotesList, $this->partInclude, $tagName, $tagCounter);
            $this->registerTagCounter($tagCounter);
        }

        if (isset($attrList[BLOCS_DATA_LOOP])) {
            // ループ内でフォーム名を差し替える必要があるかどうかを確認する
            $this->arrayFormName = $attrList[BLOCS_DATA_FORM] ?? '';

            if (! Common::checkValueName($attrList[BLOCS_DATA_LOOP])) {
                throw new \RuntimeException('B002: Invalid condition "'.BLOCS_DATA_LOOP.'" ('.$attrList[BLOCS_DATA_LOOP].')');
            }

            $compiledTag = Loop::loop($attrList, count($this->tagCounter)).$compiledTag;

            $this->registerTagCounter([
                'tag' => $tagName,
                'after' => Loop::endloop($attrList),
                'array_form' => substr($attrList[BLOCS_DATA_LOOP], 1),
            ]);
        }

        if (isset($attrList[BLOCS_DATA_FILTER]) && isset($attrList['name']) && strlen($attrList['name'])) {
            // name="tags[]" はリクエストでは tags になる
            $filterName = Common::checkFormName($attrList['name']);
            $filterName === false && $filterName = $attrList['name'];

            foreach (explode('|', $attrList[BLOCS_DATA_FILTER]) as $filterToken) {
                $this->filter[$filterName][] = $this->buildFilter($filterToken);
            }
        }
    }

    /**
     * フィルターを「関数名と引数」として保持する（PHPコードとしては保存しない）
     *
     * @return array{func: string, args: list<mixed>}
     */
    private function buildFilter($filter): array
    {
        [$filterClass, $filterFunc, $filterArgs] = Common::checkFuncValues($filter);

        return [
            'func' => ltrim(Common::findFilterFunc($filterClass, $filterFunc), '\\'),
            'args' => $filterArgs,
        ];
    }
}
