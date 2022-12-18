<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Attribute;
use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Cache\Form;

class BlocsConfig
{
    public $include;
    public $filter;
    public $menu;
    public $validate;
    public $message;
    public $upload;
}

class BlocsCompiler
{
    private $include;
    private $filter;
    private $menu;

    // バリデーション変数
    private $validate;
    private $validateMessage;
    private $validateUpload;

    private $dataAttribute;
    private $endrepeat;

    // タグ記法のための変数
    private $tagCounter;
    private $ignoreFlg;

    // 処理中のdata-part
    private $partName;

    // ファイル、ブロックごとにタグを保持
    private $partInclude;

    // partDepth=0の時に$compiledTemplateに書き出す
    private $partDepth;

    // classでincludeするテンプレート
    private $autoincludeClass;
    private $autoincluded;

    private static $allAttrName;

    public function __construct()
    {
        $this->filter = [];
        $this->menu = [];
        $this->validate = [];
        $this->validateMessage = [];
        $this->validateUpload = [];

        $this->tagCounter = [];
        $this->ignoreFlg = false;

        $this->partName = '';
        $this->partInclude = [];
        $this->partDepth = 0;

        $this->autoincludeClass = [];
        $this->autoincluded = [];

        if (!isset(self::$allAttrName)) {
            $allConstant = get_defined_constants();
            foreach ($allConstant as $key => $value) {
                strncmp($key, 'BLOCS_DATA_', 11) || self::$allAttrName[] = $value;
            }
        }
    }

    // Bladeを参照
    public function compile($templatePath)
    {
        $this->include = [$templatePath];

        return $this->compileTemplate(self::checkEncoding($templatePath), $templatePath);
    }

    // テンプレートの設定を取得
    // テンプレートの設定はディレクトリごとにまとめて保持
    // optionなどを同じディレクトリのテンプレートで共有するため
    public function getConfig()
    {
        foreach ($this->validate as $formName => $validate) {
            $this->validate[$formName] = array_merge(array_unique($validate));
        }

        $blocsConfig = new BlocsConfig();
        $blocsConfig->include = array_merge(array_unique($this->include));
        $blocsConfig->filter = $this->filter;
        $blocsConfig->menu = $this->menu;
        $blocsConfig->validate = $this->validate;
        $blocsConfig->message = $this->validateMessage;
        $blocsConfig->upload = $this->validateUpload;

        return $blocsConfig;
    }

    private function compileTemplate($writeBuff, $realpath)
    {
        // コンパイル後の文字列
        $compiledTemplate = '';

        // タグのコンパイル後の文字列
        $compiledTag = '';

        // auto includeのコンパイル後の文字列
        $autoincludeDepth = 0;
        $autoincludeTemplate = '';

        $scriptCounter = 0;
        $selectName = '';
        $formArray = [];
        $dummyArray = [];
        $labelArray = [];

        $validateMsg = [];

        $this->partInclude[$realpath] = $this->parseTemplate($writeBuff, $realpath, false);
        $htmlArray = $this->partInclude[$realpath];
        $htmlArray[] = '<!-- '.BLOCS_DATA_CHDIR.'="'.getcwd().'" -->';

        while ($htmlArray) {
            $htmlBuff = array_shift($htmlArray);

            if (!count($htmlArray) && !isset($autoincludeFlg)) {
                // classでのauto includeは最後に一回だけ
                $this->addAutoincludeClass($htmlArray);
                $autoincludeFlg = true;
            }

            if (!is_array($htmlBuff)) {
                /* テキストとコメントを処理 */

                // autoincludeの深さを取得
                if ('{{AUTOINCLUDE_START_FROM}}' === $htmlBuff) {
                    ++$autoincludeDepth;
                    continue;
                }
                if ('{{AUTOINCLUDE_END_TO}}' === $htmlBuff) {
                    --$autoincludeDepth;
                    continue;
                }

                if ($this->ignoreFlg) {
                    continue;
                }

                if (!strncmp($htmlBuff, '<!', 2) && $this->isPart() < 2) {
                    // タグ記法でブロック処理中は実行しない
                    $this->parseCommentTag($htmlBuff, $htmlArray);
                }

                // ブロックごとにタグを保持
                if ($this->isPart()) {
                    $this->partInclude[$this->partName][] = $htmlBuff;
                    continue;
                }

                isset($dataForm) && $validateMsg[$dataForm][end($this->validate[$dataForm])] .= $htmlBuff;
                isset($optionArray['label']) && $optionArray['label'] .= $htmlBuff;
                isset($labelArray['label']) && $labelArray['label'] .= $htmlBuff;

                // 結果出力
                if ($autoincludeDepth) {
                    $autoincludeTemplate .= self::escapeQuestionTag($htmlBuff);
                } else {
                    $compiledTemplate .= self::escapeQuestionTag($htmlBuff);
                }

                continue;
            }

            /* タグを処理 */

            $attrArray = $htmlBuff['attribute'];
            $quotesArray = $htmlBuff['quotes'];
            $tag = $htmlBuff['tag'];
            $type = isset($attrArray['type']) ? strtolower($attrArray['type']) : '';

            // データ属性削除
            $compiledTag = self::deleteDataAttribute($htmlBuff['raw'], $attrArray);

            // タグ記法のためのカウンター
            $endTagCounterList = $this->checkTagCounter($tag);

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
                continue;
            }

            // ブロックごとにタグを保持
            if ($this->isPart()) {
                $this->partInclude[$this->partName][] = $htmlBuff;
                continue;
            }

            // data_attributeをタグに反映
            $compiledTag = $this->mergeDataAttribute($compiledTag, $tag, $attrArray);

            /* タグ記法のデータ属性処理 */

            if (isset($attrArray[BLOCS_DATA_PART])) {
                // タグ記法でのdata-part開始処理
                $this->partName = $attrArray[BLOCS_DATA_PART];
                $this->partInclude[$this->partName] = [$compiledTag];

                $this->setTagCounter([
                    'tag' => $tag,
                    'type' => 'part',
                ]);

                continue;
            }

            if (isset($attrArray[BLOCS_DATA_VAL])) {
                $tagCounter = [];
                Attribute::val($attrArray, $quotesArray, $this->dataAttribute, $tag, $tagCounter, $htmlArray);
                count($tagCounter) && $this->setTagCounter($tagCounter);
            }

            if (isset($attrArray[BLOCS_DATA_NOTICE])) {
                $tagCounter = [];
                Attribute::notice($attrArray, $quotesArray, $this->dataAttribute, $tag, $tagCounter, $htmlArray);
                count($tagCounter) && $this->setTagCounter($tagCounter);
            }

            if (isset($attrArray[BLOCS_DATA_EXIST]) || isset($attrArray[BLOCS_DATA_NONE]) || isset($attrArray[BLOCS_DATA_IF]) || isset($attrArray[BLOCS_DATA_UNLESS])) {
                $tagCounter = [];
                $compiledTag = Attribute::condition($compiledTag, $attrArray, $quotesArray, $tag, $tagCounter, $htmlArray);
                count($tagCounter) && $this->setTagCounter($tagCounter);
            }

            if (isset($attrArray[BLOCS_DATA_REPEAT])) {
                if (!Common::checkValueName($attrArray[BLOCS_DATA_REPEAT])) {
                    trigger_error('B002: Invalid condition "'.BLOCS_DATA_REPEAT.'" ('.$attrArray[BLOCS_DATA_REPEAT].')', E_USER_ERROR);
                }

                $compiledTag = Attribute::repeat($attrArray, count($this->tagCounter)).$compiledTag;

                $this->setTagCounter([
                    'tag' => $tag,
                    'after' => Attribute::endrepeat($attrArray),
                    'array_form' => substr($attrArray[BLOCS_DATA_REPEAT], 1),
                ]);
            }

            if (isset($attrArray[BLOCS_DATA_LOOP])) {
                if (!Common::checkValueName($attrArray[BLOCS_DATA_LOOP])) {
                    trigger_error('B002: Invalid condition "'.BLOCS_DATA_LOOP.'" ('.$attrArray[BLOCS_DATA_LOOP].')', E_USER_ERROR);
                }

                $compiledTag = Attribute::loop($attrArray, count($this->tagCounter)).$compiledTag;

                $this->setTagCounter([
                    'tag' => $tag,
                    'after' => Attribute::endloop($attrArray),
                    'array_form' => substr($attrArray[BLOCS_DATA_LOOP], 1),
                ]);
            }

            if (isset($attrArray[BLOCS_DATA_FILTER]) && isset($attrArray['name']) && strlen($attrArray['name'])) {
                foreach (explode('|', $attrArray[BLOCS_DATA_FILTER]) as $buff) {
                    isset($this->filter[$attrArray['name']]) || $this->filter[$attrArray['name']] = '';
                    $this->filter[$attrArray['name']] .= $this->generateFilter($buff);
                }
            }

            // スクリプトの中のタグを無効化
            if ('script' === $tag || 'style' === $tag) {
                ++$scriptCounter;
            } elseif ('/script' === $tag || '/style' === $tag) {
                --$scriptCounter;
            }

            if ($scriptCounter > 0) {
                if ($autoincludeDepth) {
                    $autoincludeTemplate .= $compiledTag;
                } else {
                    $compiledTemplate .= $compiledTag;
                }

                continue;
            }

            /* フォーム部品の処理 */

            if ('input' === $tag && isset($attrArray['name']) && strlen($attrArray['name'])) {
                $formName = Common::checkFormName($attrArray['name']);
                if (false !== $formName) {
                    $attrArray['name'] = $formName;
                    isset($labelArray) && $labelArray = array_merge($labelArray, $attrArray);

                    if (('radio' === $type || 'checkbox' === $type) && isset($attrArray['value'])) {
                        !isset($labelArray) && isset($attrArray['id']) && $this->menu[] = $attrArray;

                        $selected = (isset($attrArray['checked']) ? 'true' : 'false');
                        $compiledTag = Form::check($compiledTag, $attrArray['name'], $attrArray['value'], 'checked', $selected);

                        $this->generateDummyForm($attrArray['name'], $compiledTag, $dummyArray);
                    }
                    if (in_array($type, ['text', 'hidden', 'search', 'tel', 'url', 'email', 'datetime', 'date', 'month', 'week', 'time', 'datetime-local', 'number', 'range', 'color'])) {
                        $compiledTag = Form::value($compiledTag, $attrArray);
                    }
                    if ('text' === $type && isset($attrArray['class'])) {
                        $classList = preg_split("/\s/", $attrArray['class']);
                        in_array('upload', $classList) && $this->validateUpload[] = $attrArray['name'];
                    }
                }
            }

            if ('select' === $tag && isset($attrArray['name']) && strlen($attrArray['name'])) {
                $formName = Common::checkFormName($attrArray['name']);
                if (false !== $formName) {
                    $attrArray['name'] = $formName;
                    isset($attrArray['multiple']) && $this->generateDummyForm($attrArray['name'], $compiledTag, $dummyArray);

                    $selectName = $attrArray['name'];
                }
            } elseif ('option' === $tag && strlen($selectName) && isset($attrArray['value'])) {
                $optionArray = $attrArray;
                $optionArray['type'] = 'select';
                $optionArray['name'] = $selectName;
                isset($optionArray['label']) || $optionArray['label'] = '';

                $selected = (isset($attrArray['selected']) ? 'true' : 'false');
                $compiledTag = Form::check($compiledTag, $selectName, $attrArray['value'], 'selected', $selected);
            } elseif ('/option' === $tag && isset($optionArray)) {
                $optionArray['label'] = trim($optionArray['label']);
                if (!isset($optionArray['value'])) {
                    $optionArray['value'] = $optionArray['label'];
                }

                $this->menu[] = $optionArray;
                unset($optionArray);
            } elseif ('/select' === $tag) {
                // メニューのグループタグを追加
                Form::select($compiledTag, $htmlArray, $selectName);

                continue;
            }

            if ('textarea' === $tag && isset($attrArray['name']) && strlen($attrArray['name'])) {
                $formName = Common::checkFormName($attrArray['name']);
                if (false !== $formName) {
                    $attrArray['name'] = $formName;

                    $tagCounter = [];
                    Form::value($compiledTag, $attrArray, $tag, $tagCounter, $htmlArray);
                    count($tagCounter) && $this->setTagCounter($tagCounter);
                }
            }

            if ('label' === $tag) {
                isset($attrArray['for']) && $labelArray['id'] = $attrArray['for'];
                $labelArray['label'] = '';
            } elseif ('/label' === $tag) {
                preg_match('/<br>$/si', $labelArray['label']) && $labelArray['label'] = substr($labelArray['label'], 0, -4);
                preg_match('/<br \/>$/si', $labelArray['label']) && $labelArray['label'] = substr($labelArray['label'], 0, -6);
                $labelArray['label'] = trim($labelArray['label']);

                (count($labelArray) > 2) ? $this->menu[] = $labelArray : array_unshift($this->menu, $labelArray);
                unset($labelArray);
            }

            // formのidを取得
            if ((isset($attrArray['id']) || isset($attrArray['for'])) && $arrayPath = $this->generateArrayFormName(1)) {
                isset($attrArray['id']) && false === strpos($attrArray['id'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrArray['id'], $attrArray, false);
                isset($attrArray['for']) && false === strpos($attrArray['for'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrArray['for'], $attrArray, false);
            }

            if (isset($attrArray['class'])) {
                $classList = preg_split("/\s/", $attrArray['class']);

                // auto includeの候補に追加
                $this->autoincludeClass = array_merge($this->autoincludeClass, $classList);
            }

            if (('input' === $tag || 'select' === $tag || 'textarea' === $tag) && isset($attrArray['name']) && strlen($attrArray['name'])) {
                // HTML5のフォームバリデーション対応
                self::addHtml5Validation($this->validate, $attrArray);

                if ($arrayForm = $this->generateArrayFormName()) {
                    $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$attrArray['name'].']', $attrArray, false);
                    $arrayPath = $this->generateArrayFormName(1);
                    $arrayMsg = $this->generateArrayFormName(2);
                } else {
                    $arrayPath = $arrayMsg = '';
                }
                $arrayMsg .= "['{$attrArray['name']}']";

                $formArray[] = [
                    'name' => $attrArray['name'],
                    'array_path' => $arrayPath,
                    'array_msg' => $arrayMsg,
                ];

                if (isset($attrArray[BLOCS_DATA_VALIDATE])) {
                    // バリデーションを設定
                    $this->validate[$attrArray['name']][] = $attrArray[BLOCS_DATA_VALIDATE];
                }
            }

            'option' !== $tag && isset($optionArray['label']) && $optionArray['label'] .= $compiledTag;
            'label' !== $tag && 'input' !== $tag && isset($labelArray['label']) && $labelArray['label'] .= $compiledTag;

            // 結果出力
            if ($autoincludeDepth) {
                $autoincludeTemplate .= $compiledTag;
            } else {
                $compiledTemplate .= $compiledTag;
            }
        }

        // auto includeを呼び出された場所に移動
        $compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', $autoincludeTemplate, $compiledTemplate);

        // 開始スクリプトを追加
        $initScript = self::getInitialScript();
        $compiledTemplate = $initScript.$compiledTemplate;

        // タグを削除してキャッシュを整形
        $compiledTemplate = preg_replace("/\?\>\n\<\?php/", "\n", $compiledTemplate);

        self::cleanupMenu($this->menu);

        return $compiledTemplate;
    }

    // ファイルごとにパーシング
    private function parseTemplate($writeBuff, $realpath, $autoinclude = true)
    {
        // ブロックのために元ファイルのパスをセットするタグを追加
        $chdirBuff = '<!-- '.BLOCS_DATA_CHDIR.'="'.dirname($realpath).'" -->';
        $htmlArray = Parser::parse($writeBuff);
        array_unshift($htmlArray, $chdirBuff);

        $resultArray = [];
        foreach ($htmlArray as $htmlBuff) {
            $resultArray[] = $htmlBuff;

            if (!is_array($htmlBuff) && !strncmp($htmlBuff, '<!', 2)) {
                // data-partのロケーションはオリジナルファイルのパスに設定
                list($includeBuff) = Parser::parse($htmlBuff, true);

                if (isset($includeBuff['attribute'][BLOCS_DATA_PART])) {
                    $resultArray[] = $chdirBuff;
                }
            }
        }

        if (!$autoinclude) {
            return $resultArray;
        }

        // auto inlcudeテンプレートは移動
        $autoincludeDir = self::getAutoincludeDir();
        if (false !== $autoincludeDir && !strncmp($realpath, $autoincludeDir, strlen($autoincludeDir))) {
            array_unshift($resultArray, '{{AUTOINCLUDE_START_FROM}}');
            $resultArray[] = '{{AUTOINCLUDE_END_TO}}';
        }

        return $resultArray;
    }

    // コメントタグをパーシングしてコメント記法を処理
    private function parseCommentTag(&$htmlBuff, &$htmlArray)
    {
        if (false === strpos($htmlBuff, 'data-')) {
            return;
        }

        // コメントタグをパース
        list($includeBuff) = Parser::parse($htmlBuff, true);
        if (!isset($includeBuff['attribute'])) {
            return;
        }

        $rawBuff = $includeBuff['raw'];
        $attrArray = $includeBuff['attribute'];
        $quotesArray = $includeBuff['quotes'];

        /* コメント記法のデータ属性処理 */

        if (isset($attrArray[BLOCS_DATA_PART])) {
            // コメント記法でのdata-part開始処理
            ++$this->partDepth;

            if (1 === $this->partDepth) {
                // ブロック処理開始
                $this->partName = $attrArray[BLOCS_DATA_PART];
                $this->partInclude[$this->partName] = [];
                $htmlBuff = '';
            }

            return;
        }
        if (isset($attrArray[BLOCS_DATA_ENDPART])) {
            // コメント記法でのdata-part終了処理
            --$this->partDepth;
            $this->partDepth < 0 && $this->partDepth = 0;

            if (0 === $this->partDepth) {
                // ブロック処理終了
                $this->partName = '';
                $htmlBuff = '';
            }

            return;
        }
        // ブロック処理中なので後続の処理は不要
        if ($this->isPart()) {
            return;
        }

        if (isset($attrArray[BLOCS_DATA_CHDIR])) {
            chdir($attrArray[BLOCS_DATA_CHDIR]);
            $htmlBuff = '';

            return;
        }

        if (isset($attrArray[BLOCS_DATA_INCLUDE])) {
            // auto includeのタグの埋め込み
            $autoincludeDir = self::getAutoincludeDir();
            if (false !== $autoincludeDir && basename($autoincludeDir) == $attrArray[BLOCS_DATA_INCLUDE]) {
                $htmlBuff = '{{REPLACE_TO_AUTOINCLUDE}}';

                return;
            }

            $resultArray = [];
            if (isset($this->partInclude[$attrArray[BLOCS_DATA_INCLUDE]])) {
                $resultArray = $this->partInclude[$attrArray[BLOCS_DATA_INCLUDE]];
            } else {
                $resultArray = $this->addDataInclude($attrArray, $htmlBuff);
            }

            if (empty($attrArray[BLOCS_DATA_EXIST])) {
                unset($attrArray[BLOCS_DATA_EXIST]);
            }

            if (empty($resultArray)) {
                $htmlBuff = '';

                return;
            }

            $resultArray[] = '<!-- '.BLOCS_DATA_CHDIR.'="'.getcwd().'" -->';

            // conditionで挟み込み
            $condition = Attribute::condition('', $attrArray, $quotesArray);
            if (!empty($condition)) {
                array_unshift($resultArray, $condition);
                $resultArray[] = BLOCS_ENDIF_SCRIPT;
            }

            $htmlArray = array_merge($resultArray, $htmlArray);
            $htmlBuff = '';

            // 引数を渡せるように
            foreach ($attrArray as $key => $value) {
                if (Common::checkValueName($key)) {
                    $quotes = empty($quotesArray[$key]) ? '' : $quotesArray[$key];
                    $htmlBuff .= "<?php {$key} = {$quotes}{$value}{$quotes}; ?>";
                }
            }

            return;
        }

        if (isset($attrArray[BLOCS_DATA_VAL])) {
            $htmlBuff = Attribute::val($attrArray, $quotesArray, $this->dataAttribute);
        }
        if (isset($attrArray[BLOCS_DATA_NOTICE])) {
            $htmlBuff = Attribute::notice($attrArray, $quotesArray, $this->dataAttribute);
        }

        if (isset($attrArray[BLOCS_DATA_VALIDATE]) && isset($attrArray[BLOCS_DATA_FORM])) {
            self::checkDataValidate($this->validate, $attrArray[BLOCS_DATA_FORM], $attrArray[BLOCS_DATA_VALIDATE]) && $this->validate[$attrArray[BLOCS_DATA_FORM]][] = $attrArray[BLOCS_DATA_VALIDATE];

            if (isset($attrArray[BLOCS_DATA_NOTICE])) {
                $validateMethod = self::getValidateMethod($attrArray[BLOCS_DATA_VALIDATE]);
                $this->validateMessage[$attrArray[BLOCS_DATA_FORM]][$validateMethod] = $attrArray[BLOCS_DATA_NOTICE];
            }
            $htmlBuff = '';

            return;
        }

        if (isset($attrArray[BLOCS_DATA_EXIST]) || isset($attrArray[BLOCS_DATA_NONE]) || isset($attrArray[BLOCS_DATA_IF]) || isset($attrArray[BLOCS_DATA_UNLESS])) {
            if (isset($attrArray[BLOCS_DATA_VAL]) || isset($attrArray[BLOCS_DATA_NOTICE])) {
                if (isset($attrArray[BLOCS_DATA_ATTRIBUTE])) {
                    $rawBuff = $this->dataAttribute[count($this->dataAttribute) - 1]['value'];
                } else {
                    $rawBuff = $htmlBuff;
                }
            } else {
                $rawBuff = '';
            }

            $htmlBuff = Attribute::condition($rawBuff, $attrArray, $quotesArray);

            if (isset($attrArray[BLOCS_DATA_VAL]) || isset($attrArray[BLOCS_DATA_NOTICE])) {
                $htmlBuff .= BLOCS_ENDIF_SCRIPT;
                if (isset($attrArray[BLOCS_DATA_ATTRIBUTE])) {
                    $this->dataAttribute[count($this->dataAttribute) - 1]['value'] = $htmlBuff;
                    $htmlBuff = '';
                }
            }
        }
        if (isset($attrArray[BLOCS_DATA_ENDEXIST]) || isset($attrArray[BLOCS_DATA_ENDNONE]) || isset($attrArray[BLOCS_DATA_ENDIF]) || isset($attrArray[BLOCS_DATA_ENDUNLESS])) {
            $htmlBuff = BLOCS_ENDIF_SCRIPT;
        }

        if (isset($attrArray[BLOCS_DATA_REPEAT])) {
            $rawBuff = '';
            $htmlBuff = Attribute::repeat($attrArray, count($this->tagCounter));
            $this->endrepeat[] = $attrArray;

            $this->setTagCounter([
                'tag' => BLOCS_DATA_REPEAT,
                'array_form' => substr($attrArray[BLOCS_DATA_REPEAT], 1),
            ], false);
        }
        if (isset($attrArray[BLOCS_DATA_ENDREPEAT]) && !empty($this->endrepeat)) {
            $htmlBuff = Attribute::endrepeat(array_pop($this->endrepeat));

            $target = '';
            foreach ($this->tagCounter as $num => $buff) {
                if (!isset($buff['array_form']) || BLOCS_DATA_REPEAT !== $buff['tag']) {
                    continue;
                }
                $target = $num;
            }
            if (strlen($target)) {
                unset($this->tagCounter[$target]);
                $this->tagCounter = array_merge($this->tagCounter);
            }
        }
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

    private function checkTagCounter($tag)
    {
        $endTagCounterList = [];

        foreach ($this->tagCounter as $num => $tagCounter) {
            isset($tagCounter['num']) || $this->tagCounter[$num]['num'] = 1;
            $tag === $tagCounter['tag'] && $this->tagCounter[$num]['num']++;
            $tag === '/'.$tagCounter['tag'] && $this->tagCounter[$num]['num']--;

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

    /* data-includeのメソッド */

    private function addDataInclude($attrArray, $htmlBuff)
    {
        if (!strncmp($attrArray[BLOCS_DATA_INCLUDE], '/', 1)) {
            // ルートディレクトリのパスを変換
            $attrArray[BLOCS_DATA_INCLUDE] = BLOCS_ROOT_DIR.$attrArray[BLOCS_DATA_INCLUDE];
        }

        $_ = function ($s) {
            return $s;
        };
        eval("\$attrArray[BLOCS_DATA_INCLUDE] = <<<EOS\n{$attrArray[BLOCS_DATA_INCLUDE]}\nEOS;\n");
        if (!strlen(($realpath = str_replace(DIRECTORY_SEPARATOR, '/', realpath($attrArray[BLOCS_DATA_INCLUDE]))))) {
            if (false !== ($resultBuff = $this->addAutoinclude($attrArray, $htmlBuff))) {
                // data-includeができないのでauto includeしてみる
                return $resultBuff;
            }

            if (isset($attrArray[BLOCS_DATA_EXIST])) {
                return [];
            }

            trigger_error('B003: Can not find template ('.getcwd().'/'.$attrArray[BLOCS_DATA_INCLUDE].')', E_USER_ERROR);
        }

        if (count($this->include) > BLOCS_INCLUDE_MAX) {
            trigger_error('B004: Template loop error (over '.BLOCS_INCLUDE_MAX.')', E_USER_ERROR);
        }
        $this->include[] = $realpath;

        $autoincludeDir = self::getAutoincludeDir();
        if (false !== $autoincludeDir && !strncmp($realpath, $autoincludeDir, strlen($autoincludeDir))) {
            $autoinclude = pathinfo($realpath, PATHINFO_FILENAME);
            if (isset($this->autoincluded[$autoinclude])) {
                // auto includeは一回だけしかincludeしない
                return [];
            }
            $this->autoincluded[$autoinclude] = true;
        }

        if (!isset($this->partInclude[$realpath])) {
            // ファイルごとにタグを保持
            $this->partInclude[$realpath] = $this->parseTemplate(self::checkEncoding($realpath), $realpath);
        }

        return $this->partInclude[$realpath];
    }

    private function addAutoinclude($attrArray, $htmlBuff)
    {
        $autoincludeDir = self::getAutoincludeDir();
        if (false === $autoincludeDir) {
            return false;
        }

        list($autoinclude) = explode('_', $attrArray[BLOCS_DATA_INCLUDE]);
        if (!is_file($autoincludeDir.'/'.$autoinclude.'.html')) {
            return false;
        }

        if (isset($this->autoincluded[$autoinclude])) {
            // すでにincludeされている
            return false;
        }

        // auto includeの対象に追加（無限ループにならないよう注意）
        return [
            '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->",
            $htmlBuff,
        ];
    }

    private function addAutoincludeClass(&$htmlArray)
    {
        $autoincludeDir = self::getAutoincludeDir();
        if (false === $autoincludeDir) {
            return;
        }

        // auto includeの候補
        if (!count($this->autoincludeClass)) {
            return;
        }
        $this->autoincludeClass = array_merge(array_unique($this->autoincludeClass));

        $targetFileList = scandir($autoincludeDir);
        foreach ($targetFileList as $targetFile) {
            if ('.' == substr($targetFile, 0, 1)) {
                continue;
            }
            $targetFile = $autoincludeDir.'/'.$targetFile;
            if (!is_file($targetFile)) {
                continue;
            }

            $autoinclude = pathinfo($targetFile, PATHINFO_FILENAME);

            if (!in_array($autoinclude, $this->autoincludeClass)) {
                // classでのコールされていないのでauto includeしない
                continue;
            }

            if (isset($this->autoincluded[$autoinclude])) {
                // すでにincludeされている
                continue;
            }

            $htmlArray[] = '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->";
        }

        return;
    }

    private function isPart()
    {
        if (strlen($this->partName)) {
            if ($this->partDepth) {
                // コメント記法でブロック処理中
                return 1;
            } else {
                // タグ記法でブロック処理中
                return 2;
            }
        }

        // ブロック処理中ではない
        return 0;
    }

    private function generateArrayFormName($format = 0)
    {
        /*
            $format = 0(HTML form): matrix[<?php echo($repeatIndex); ?>]
            $format = 1(HTML id): matrix_<?php echo($repeatIndex); ?>
            $format = 2(PHP array): ['matrix'][$repeatIndex]
        */

        if (empty($this->tagCounter)) {
            return '';
        }

        $formName = '';
        foreach (array_reverse($this->tagCounter) as $num => $buff) {
            if (!isset($buff['array_form']) || !strncmp($buff['array_form'], 'menu_', 5)) {
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

    private function generateDummyForm($attrName, &$rawBuff, &$dummyArray)
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

        $rawBuff = $dummyBuff.$rawBuff;
        $dummyArray[$dummyForm] = true;
    }

    private function mergeDataAttribute($compiledTag, $tag, &$attrArray)
    {
        // data-attributeで属性書き換え
        if (isset($this->dataAttribute)) {
            $dataAttribute = [];
            foreach ($this->dataAttribute as $buff) {
                isset($dataAttribute[$buff['name']]) || $dataAttribute[$buff['name']] = '';
                $dataAttribute[$buff['name']] .= $buff['value'];
            }
            unset($this->dataAttribute);

            foreach ($dataAttribute as $name => $value) {
                $compiledTag = Common::mergeAttribute($compiledTag, $name, $value, $attrArray);
            }
        }

        return $compiledTag;
    }

    private function getAutoincludeDir()
    {
        if (!defined('BLOCS_ROOT_DIR')) {
            return false;
        }

        if (defined('VIEW_PREFIX')) {
            $autoincludeDir = BLOCS_ROOT_DIR.'/'.VIEW_PREFIX.'/';
        } else {
            $autoincludeDir = BLOCS_ROOT_DIR.'/';
        }

        if (!is_dir($autoincludeDir.'autoinclude')) {
            return false;
        }

        return $autoincludeDir.'autoinclude';
    }

    // テンプレートの初期処理
    private static function getInitialScript()
    {
        return <<< END_of_HTML
<?php
    \$_appendOption = \\Blocs\\Option::append();
    extract(\$_appendOption, EXTR_PREFIX_ALL, 'menu');

    if (function_exists('old')) {
        \$_oldValue = old();
        !empty(\$_oldValue) && is_array(\$_oldValue) && extract(\$_oldValue, EXTR_SKIP);

        \$_userData = \Auth::user();

        !empty(\$_userData) && \$_userData = \$_userData->toArray();
        !empty(\$_userData) && is_array(\$_userData) && extract(\$_userData, EXTR_PREFIX_ALL, 'auth');
    }

    \$_ = function(\$s){ return \$s; };
?>

END_of_HTML;
    }

    // data-validateの重複を確認
    private static function checkDataValidate($checkValidate, $dataForm, $dataValidate)
    {
        if (empty($checkValidate[$dataForm])) {
            return true;
        }

        $validateMethod = self::getValidateMethod($dataValidate);
        foreach ($checkValidate[$dataForm] as $validate) {
            if (BlocsCompiler::getValidateMethod($validate) === $validateMethod) {
                return false;
            }
        }

        return true;
    }

    // バリデーションから引数を除く
    private static function getValidateMethod($dataValidate)
    {
        list($validateMethod) = explode(':', $dataValidate, 2);
        'maxlength' === $validateMethod && $validateMethod = 'max';
        'minlength' === $validateMethod && $validateMethod = 'min';

        return $validateMethod;
    }

    // HTML5のフォームバリデーション対応
    private static function addHtml5Validation(&$dataValidate, $attrArray)
    {
        if (isset($attrArray['required'])) {
            $dataValidate[$attrArray['name']][] = 'required';
        }
        if (isset($attrArray['maxlength'])) {
            $dataValidate[$attrArray['name']][] = 'string';
            $dataValidate[$attrArray['name']][] = 'max:'.$attrArray['maxlength'];
        }
        if (isset($attrArray['minlength'])) {
            $dataValidate[$attrArray['name']][] = 'string';
            $dataValidate[$attrArray['name']][] = 'min:'.$attrArray['minlength'];
        }
        if (isset($attrArray['max'])) {
            $dataValidate[$attrArray['name']][] = 'numeric';
            $dataValidate[$attrArray['name']][] = 'max:'.$attrArray['max'];
        }
        if (isset($attrArray['min'])) {
            $dataValidate[$attrArray['name']][] = 'numeric';
            $dataValidate[$attrArray['name']][] = 'min:'.$attrArray['min'];
        }
        if (isset($attrArray['pattern'])) {
            $dataValidate[$attrArray['name']][] = 'regex:/'.$attrArray['pattern'].'/';
        }
    }

    private static function cleanupMenu(&$thisMenu)
    {
        $idList = [];
        foreach ($thisMenu as $num => $buff) {
            if ((isset($buff['label']) && !strncmp($buff['label'], '<?php', 5)) || (isset($buff['value']) && !strncmp($buff['value'], '<?php', 5))) {
                unset($thisMenu[$num]);
                continue;
            }

            if (!isset($buff['id'])) {
                if (!isset($buff['name']) || !isset($buff['value'])) {
                    unset($thisMenu[$num]);
                }
                continue;
            }

            if (isset($idList[$buff['id']])) {
                $thisMenu[$num] = array_merge($buff, $thisMenu[$idList[$buff['id']]]);
                unset($thisMenu[$idList[$buff['id']]]);
            }
            $idList[$buff['id']] = $num;
        }
        $thisMenu = array_merge($thisMenu);

        $menuItemList = [];
        foreach ($thisMenu as $num => $buff) {
            if (!isset($buff['name']) || !isset($buff['value']) || !isset($buff['label'])) {
                continue;
            }
            if (empty($buff['type']) || !('radio' === $buff['type'] || 'checkbox' === $buff['type'] || 'select' === $buff['type'])) {
                continue;
            }

            $menuItemList[$buff['name']][] = $buff;
        }
        $thisMenu = $menuItemList;
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

    private static function checkEncoding($realpath)
    {
        $viewBuff = file_get_contents($realpath);

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($viewBuff, 'UTF-8', true);
            if ('UTF-8' !== $encoding) {
                trigger_error('B011: Can not permit this encoding ('.$encoding.') and have to convert to "UTF-8"', E_USER_ERROR);
            }
        }

        return $viewBuff;
    }

    private static function escapeQuestionTag($rawBuff)
    {
        $rawBuff = explode('<?', $rawBuff);
        $resultBuff = array_shift($rawBuff);
        foreach ($rawBuff as $buff) {
            $resultBuff .= '<?';
            if (false === strpos($buff, '?>')) {
                $resultBuff .= $buff;
            }

            if (strncmp($buff, 'php', 3) && strncmp($buff, '=', 1)) {
                $buff = explode('?>', $buff, 2);
                $resultBuff .= 'php echo('.Common::escapeDoubleQuote('<?'.$buff[0].'?>')."); ?>\n".$buff[1];
            } else {
                $resultBuff .= $buff;
            }
        }

        return $resultBuff;
    }

    private static function deleteDataAttribute($rawBuff, $attrArray)
    {
        foreach (self::$allAttrName as $attrName) {
            if (!isset($attrArray[$attrName])) {
                continue;
            }

            $rawBuff = preg_replace('/\s+'.$attrName.'\s*=\s*["\']{0,1}'.str_replace('/', '\/', preg_quote($attrArray[$attrName])).'["\']{0,1}([\s>\/]+)/si', '${1}', $rawBuff);
            $rawBuff = preg_replace('/\s+'.$attrName.'([\s>\/]+)/si', '${1}', $rawBuff);
        }

        return $rawBuff;
    }
}

/* End of file */
