<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait CompileTagTrait
{
    use Tag\AttributeTrait;
    use Tag\FormTrait;
    use Tag\LabelTrait;

    private static $allAttrName;

    // タグを解析してタグ記法を処理する
    private function processTagDirective($htmlBuff, &$htmlArray)
    {
        $tagName = $htmlBuff['tag'];
        $attrList = $htmlBuff['attribute'];
        $quotesList = $htmlBuff['quotes'];
        $type = isset($attrList['type']) ? strtolower($attrList['type']) : '';

        // データ属性を取り除いたタグの文字列を生成する
        $compiledTag = self::removeDataAttributes($htmlBuff['raw'], $attrList);

        // optionの値に利用するラベルを取得する
        $this->compileTagLabel($tagName, $compiledTag);

        // タグ記法用のカウンターを更新する
        $endTagCounterList = $this->collectClosingTagCounters($tagName);

        // カウンターの終了処理を行う
        foreach ($endTagCounterList as $endTagCounter) {
            empty($endTagCounter['before']) || $compiledTag = $endTagCounter['before'].$compiledTag;
            empty($endTagCounter['after']) || array_unshift($htmlArray, $endTagCounter['after']);

            if (! isset($endTagCounter['type'])) {
                continue;
            }

            $endTagCounter['type'] === 'ignore' && $this->ignoreFlg = false;

            if ($endTagCounter['type'] === 'part' && $this->getPartProcessingState()) {
                // タグ記法によるdata-bloc終了を処理する
                $this->partInclude[$this->partName][] = $compiledTag;
                $this->partName = '';
                $compiledTag = '';
            }
        }

        if ($this->ignoreFlg) {
            return '';
        }

        // ブロック単位でタグを保持する
        if ($this->getPartProcessingState()) {
            $this->partInclude[$this->partName][] = $htmlBuff;

            return '';
        }

        /* タグ記法のデータ属性を処理する */

        if (isset($attrList[BLOCS_DATA_BLOC])) {
            // タグ記法によるdata-bloc開始を処理する
            $this->partName = $attrList[BLOCS_DATA_BLOC];
            $this->partInclude[$this->partName] = [$compiledTag];

            $this->registerTagCounter([
                'tag' => $tagName,
                'type' => 'part',
            ]);

            return '';
        }

        $this->compileTagAttribute($htmlBuff, $htmlArray, $attrList, $compiledTag);

        // data-attributeで集めた属性を反映する
        $compiledTag = $this->mergeDeferredDataAttributes($compiledTag, $attrList);

        // script/style内ではタグ処理を無効化する
        if ($tagName === 'script' || $tagName === 'style') {
            $this->scriptCounter++;
        } elseif ($tagName === '/script' || $tagName === '/style') {
            $this->scriptCounter--;
        }

        if ($this->scriptCounter > 0) {
            return $compiledTag;
        }

        if ($tagName === 'label') {
            isset($attrList['for']) && $this->labelArray['id'] = $attrList['for'];
            $this->labelArray['label'] = '';
        }

        if (isset($attrList['class']) || isset($attrList['data-bs-toggle'])) {
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

            isset($attrList['data-bs-toggle']) && $classList[] = $attrList['data-bs-toggle'];

            // auto include候補にクラスを追加する
            $this->autoincludeClass = array_merge($this->autoincludeClass, $classList);
        }

        /* フォーム部品の処理を行う */
        $this->compileTagForm($htmlBuff, $htmlArray, $attrList, $compiledTag);

        return $compiledTag;
    }

    private static function removeDataAttributes($rawString, $attrList)
    {
        if (! isset(self::$allAttrName)) {
            $allConstant = get_defined_constants();
            foreach ($allConstant as $key => $value) {
                strncmp($key, 'BLOCS_DATA_', 11) || self::$allAttrName[] = $value;
            }
        }

        foreach (self::$allAttrName as $attrName) {
            if (! isset($attrList[$attrName])) {
                continue;
            }

            $rawString = preg_replace('/\s+'.$attrName.'\s*=\s*["\']{0,1}'.str_replace('/', '\/', preg_quote($attrList[$attrName])).'["\']{0,1}([\s>\/]+)/si', '${1}', $rawString);
            $rawString = preg_replace('/\s+'.$attrName.'([\s>\/]+)/si', '${1}', $rawString);
        }

        return $rawString;
    }

    /* タグ記法カウンターのメソッド */

    private function registerTagCounter($tagCounter, $unshift = true)
    {
        isset($tagCounter['type']) && $tagCounter['type'] === 'ignore' && $this->ignoreFlg = true;

        if (! $unshift) {
            $this->tagCounter[] = $tagCounter;

            return;
        }

        array_unshift($this->tagCounter, $tagCounter);
    }

    private function collectClosingTagCounters($tagName)
    {
        $endTagCounterList = [];

        foreach ($this->tagCounter as $num => $tagCounter) {
            isset($tagCounter['num']) || $this->tagCounter[$num]['num'] = 1;
            $tagName === $tagCounter['tag'] && $this->tagCounter[$num]['num']++;
            $tagName === '/'.$tagCounter['tag'] && $this->tagCounter[$num]['num']--;

            if ($this->tagCounter[$num]['num']) {
                // カウントが残っている場合は処理を継続する
                continue;
            }

            $endTagCounterList[] = $tagCounter;
            unset($this->tagCounter[$num]);
        }
        $this->tagCounter = array_merge($this->tagCounter);

        return $endTagCounterList;
    }

    private function mergeDeferredDataAttributes($compiledTag, &$attrList)
    {
        // data-attributeで保持した属性を適用する
        if (empty($this->dataAttribute)) {
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
        $this->dataAttribute = [];

        foreach ($dataAttribute as $name => $value) {
            $compiledTag = Common::mergeAttribute($compiledTag, $name, $value, $attrList, isset($noValue[$name]));
        }

        return $compiledTag;
    }
}
