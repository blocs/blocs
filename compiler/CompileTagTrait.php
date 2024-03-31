<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait CompileTagTrait
{
    use Tag\AttributeTrait;
    use Tag\FormTrait;
    use Tag\LabelTrait;

    // コメントタグをパーシングしてコメント記法を処理
    private function compileTag($htmlBuff, &$htmlArray)
    {
        $tagName = $htmlBuff['tag'];
        $attrList = $htmlBuff['attribute'];
        $quotesList = $htmlBuff['quotes'];
        $type = isset($attrList['type']) ? strtolower($attrList['type']) : '';

        // データ属性削除
        // タグのコンパイル後の文字列
        $compiledTag = self::deleteDataAttribute($htmlBuff['raw'], $attrList);

        // optionの値のためにラベルを取得
        $this->compileTagLabel($tagName, $compiledTag);

        // タグ記法のためのカウンター
        $endTagCounterList = $this->checkTagCounter($tagName);

        // カウンターの終了処理
        foreach ($endTagCounterList as $endTagCounter) {
            empty($endTagCounter['before']) || $compiledTag = $endTagCounter['before'].$compiledTag;
            empty($endTagCounter['after']) || array_unshift($htmlArray, $endTagCounter['after']);

            if (!isset($endTagCounter['type'])) {
                continue;
            }

            'ignore' === $endTagCounter['type'] && $this->ignoreFlg = false;

            if ('part' === $endTagCounter['type'] && $this->isPart()) {
                // タグ記法でのdata-part終了処理
                $this->partInclude[$this->partName][] = $compiledTag;
                $this->partName = '';
                $compiledTag = '';
            }
        }

        if ($this->ignoreFlg) {
            return '';
        }

        // ブロックごとにタグを保持
        if ($this->isPart()) {
            $this->partInclude[$this->partName][] = $htmlBuff;

            return '';
        }

        // data_attributeをタグに反映
        $compiledTag = $this->mergeDataAttribute($compiledTag, $attrList);

        /* タグ記法のデータ属性処理 */

        if (isset($attrList[BLOCS_DATA_PART])) {
            // タグ記法でのdata-part開始処理
            $this->partName = $attrList[BLOCS_DATA_PART];
            $this->partInclude[$this->partName] = [$compiledTag];

            $this->setTagCounter([
                'tag' => $tagName,
                'type' => 'part',
            ]);

            return '';
        }

        $this->compileTagAttribute($htmlBuff, $htmlArray, $attrList, $compiledTag);

        // スクリプトの中のタグを無効化
        if ('script' === $tagName || 'style' === $tagName) {
            ++$this->scriptCounter;
        } elseif ('/script' === $tagName || '/style' === $tagName) {
            --$this->scriptCounter;
        }

        if ($this->scriptCounter > 0) {
            return $compiledTag;
        }

        if ('label' === $tagName) {
            isset($attrList['for']) && $this->labelArray['id'] = $attrList['for'];
            $this->labelArray['label'] = '';
        }

        if (isset($attrList['class']) || isset($attrList['data-toggle'])) {
            $classList = [];
            if (isset($attrList['class'])) {
                $classNameList = preg_split("/\s/", $attrList['class']);
                foreach ($classNameList as $className) {
                    list($className) = preg_split("/\<\?php/", $className, 2);
                    if (!strncmp($className, 'ai-', 3)) {
                        $classList[] = substr($className, 3);
                    }
                }
            }

            isset($attrList['data-toggle']) && $classList[] = $attrList['data-toggle'];

            // auto includeの候補に追加
            $this->autoincludeClass = array_merge($this->autoincludeClass, $classList);
        }

        /* フォーム部品の処理 */
        $this->compileTagForm($htmlBuff, $htmlArray, $attrList, $compiledTag);

        return $compiledTag;
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
}
