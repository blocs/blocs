<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;
use Blocs\Compiler\Cache\Condition;
use Blocs\Compiler\Cache\Form;
use Blocs\Compiler\Cache\Repeat;
use Blocs\Compiler\Cache\Val;

class BlocsConfig
{
    public $include;
    public $filter;
    public $option;
    public $validate;
    public $message;
    public $upload;
}

class BlocsCompiler
{
    use CompileCommentTrait;
    use CompileTagTrait;

    private $include;
    private array $filter;
    private $option;

    // バリデーション変数
    private array $validate;
    private array $validateMessage;
    private array $validateUpload;

    private $dataAttribute;
    private $endrepeat;

    // タグ記法のための変数
    private $tagCounter;
    private bool $ignoreFlg;
    private $arrayFormName;

    // 処理中のdata-part
    private $partName;

    // ファイル、ブロックごとにタグを保持
    private array $partInclude;

    // partDepth=0の時に$compiledTemplateに書き出す
    private int $partDepth;

    // classでincludeするテンプレート
    private array $autoincludeClass;
    private array $autoincluded;

    private static $allAttrName;
    private static array $assignedValue;

    public function __construct()
    {
        $this->filter = [];
        $this->option = [];
        $this->validate = [];
        $this->validateMessage = [];
        $this->validateUpload = [];
        $this->label = [];

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

        self::$assignedValue = [];
    }

    // Bladeを参照
    public function compile($templatePath)
    {
        $this->include = [$templatePath];

        return $this->compileTemplate(self::checkEncoding($templatePath), $templatePath);
    }

    public function template($writeBuff, $val = [])
    {
        // 引数をセット
        extract($val);

        ob_start();
        eval(substr($this->compileTemplate($writeBuff, __FILE__), 5));
        $writeBuff = ob_get_clean();

        // 不要な改行を削除
        $writeBuff = preg_replace("/\n[\s\n]+\n/", "\n\n", $writeBuff);

        return $writeBuff;
    }

    // テンプレートの設定を取得
    // テンプレートの設定はディレクトリごとにまとめて保持
    // optionなどを同じディレクトリのテンプレートで共有するため
    public function getConfig()
    {
        foreach ($this->validate as $formName => $validate) {
            $this->validate[$formName] = array_merge(array_unique($validate));
        }

        foreach (array_keys($this->validate) as $formName) {
            if (false !== strpos($formName, '<?php')) {
                unset($this->validate[$formName]);
            }
        }

        $blocsConfig = new BlocsConfig();
        $blocsConfig->include = array_merge(array_unique($this->include));
        $blocsConfig->filter = $this->filter;
        $blocsConfig->option = $this->option;
        $blocsConfig->validate = $this->validate;
        $blocsConfig->message = $this->validateMessage;
        $blocsConfig->upload = $this->validateUpload;
        $blocsConfig->label = $this->label;

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

                // ラベルを取得
                isset($optionArray['label']) && $optionArray['label'] .= $htmlBuff;
                isset($labelArray['label']) && $labelArray['label'] .= $htmlBuff;

                if (!strncmp($htmlBuff, '<!', 2) && $this->isPart() < 2) {
                    // タグ記法でブロック処理中は実行しない
                    $this->compileComment($htmlBuff, $htmlArray);
                }

                // ブロックごとにタグを保持
                if ($this->isPart()) {
                    $this->partInclude[$this->partName][] = $htmlBuff;
                    continue;
                }

                // 結果出力
                if ($autoincludeDepth) {
                    $autoincludeTemplate .= self::escapeQuestionTag($htmlBuff);
                } else {
                    $compiledTemplate .= self::escapeQuestionTag($htmlBuff);
                }

                continue;
            }

            /* タグを処理 */

            $tagName = $htmlBuff['tag'];
            $attrList = $htmlBuff['attribute'];
            $quotesList = $htmlBuff['quotes'];
            $type = isset($attrList['type']) ? strtolower($attrList['type']) : '';

            // データ属性削除
            $compiledTag = self::deleteDataAttribute($htmlBuff['raw'], $attrList);

            // ラベルを取得
            if (isset($optionArray['label'])) {
                if ('/option' === $tagName) {
                    $optionArray['label'] = trim($optionArray['label']);
                    if (!isset($optionArray['value'])) {
                        $optionArray['value'] = $optionArray['label'];
                    }

                    $this->option[] = $optionArray;
                    unset($optionArray);
                } else {
                    $optionArray['label'] .= $compiledTag;
                }
            }

            if (isset($labelArray['label'])) {
                if ('/label' === $tagName) {
                    preg_match('/<br>$/si', $labelArray['label']) && $labelArray['label'] = substr($labelArray['label'], 0, -4);
                    preg_match('/<br \/>$/si', $labelArray['label']) && $labelArray['label'] = substr($labelArray['label'], 0, -6);
                    $labelArray['label'] = trim($labelArray['label']);

                    (count($labelArray) > 2) ? $this->option[] = $labelArray : array_unshift($this->option, $labelArray);

                    isset($labelArray['id']) && strlen($labelArray['label']) && $this->label[$labelArray['id']] = $labelArray['label'];
                    unset($labelArray);
                } elseif ('input' === $tagName) {
                    // ラベルに含めない
                } else {
                    $labelArray['label'] .= $compiledTag;
                }
            }

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
                continue;
            }

            // ブロックごとにタグを保持
            if ($this->isPart()) {
                $this->partInclude[$this->partName][] = $htmlBuff;
                continue;
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

                continue;
            }

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

                $compiledTag = Repeat::loop($attrList, count($this->tagCounter)).$compiledTag;

                $this->setTagCounter([
                    'tag' => $tagName,
                    'after' => Repeat::endloop($attrList),
                    'array_form' => substr($attrList[BLOCS_DATA_LOOP], 1),
                ]);
            }

            if (isset($attrList[BLOCS_DATA_FILTER]) && isset($attrList['name']) && strlen($attrList['name'])) {
                foreach (explode('|', $attrList[BLOCS_DATA_FILTER]) as $buff) {
                    isset($this->filter[$attrList['name']]) || $this->filter[$attrList['name']] = '';
                    $this->filter[$attrList['name']] .= $this->generateFilter($buff);
                }
            }

            // スクリプトの中のタグを無効化
            if ('script' === $tagName || 'style' === $tagName) {
                ++$scriptCounter;
            } elseif ('/script' === $tagName || '/style' === $tagName) {
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

            if ('input' === $tagName && isset($attrList['name']) && strlen($attrList['name'])) {
                $formName = Common::checkFormName($attrList['name']);
                if (false !== $formName) {
                    $attrList['name'] = $formName;
                    isset($labelArray) && $labelArray = array_merge($labelArray, $attrList);

                    if (('radio' === $type || 'checkbox' === $type) && isset($attrList['value'])) {
                        !isset($labelArray) && isset($attrList['id']) && $this->option[] = $attrList;

                        $selected = (isset($attrList['checked']) ? 'true' : 'false');
                        $compiledTag = Form::check($compiledTag, $attrList['name'], $attrList['value'], 'checked', $selected);

                        $this->generateDummyForm($attrList['name'], $compiledTag, $dummyArray);
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
                    isset($attrList['multiple']) && $this->generateDummyForm($attrList['name'], $compiledTag, $dummyArray);

                    $selectName = $attrList['name'];
                    $isSelect = true;
                }
            } elseif ('option' === $tagName && strlen($selectName) && isset($attrList['value'])) {
                $optionArray = $attrList;
                $optionArray['type'] = 'select';
                $optionArray['name'] = $selectName;
                isset($optionArray['label']) || $optionArray['label'] = '';

                $selected = (isset($attrList['selected']) ? 'true' : 'false');
                $compiledTag = Form::check($compiledTag, $selectName, $attrList['value'], 'selected', $selected);
            } elseif ('/select' === $tagName && isset($isSelect)) {
                // メニューのグループタグを追加
                Form::select($compiledTag, $htmlArray, $selectName);
                unset($isSelect);

                continue;
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

            if ('label' === $tagName) {
                isset($attrList['for']) && $labelArray['id'] = $attrList['for'];
                $labelArray['label'] = '';
            }

            // formのidを取得
            if ((isset($attrList['id']) || isset($attrList['for'])) && $arrayPath = $this->generateArrayFormName(1)) {
                isset($attrList['id']) && false === strpos($attrList['id'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'id', $arrayPath.'_'.$attrList['id'], $attrList, false);
                isset($attrList['for']) && false === strpos($attrList['for'], '<?php') && $compiledTag = Common::mergeAttribute($compiledTag, 'for', $arrayPath.'_'.$attrList['for'], $attrList, false);
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

            if (('input' === $tagName || 'select' === $tagName || 'textarea' === $tagName) && isset($attrList['name']) && strlen($attrList['name'])) {
                if ($arrayForm = $this->generateArrayFormName()) {
                    $compiledTag = Common::mergeAttribute($compiledTag, 'name', $arrayForm.'['.$attrList['name'].']', $attrList, false);
                    $arrayPath = $this->generateArrayFormName(1);
                    $arrayMsg = $this->generateArrayFormName(2);
                } else {
                    $arrayPath = $arrayMsg = '';
                }
                $arrayMsg .= "['{$attrList['name']}']";

                $formArray[] = [
                    'name' => $attrList['name'],
                    'array_path' => $arrayPath,
                    'array_msg' => $arrayMsg,
                ];

                if (isset($attrList[BLOCS_DATA_VALIDATE])) {
                    // バリデーションを設定
                    foreach (explode('|', $attrList[BLOCS_DATA_VALIDATE]) as $validate) {
                        $this->validate[$attrList['name']][] = $validate;
                    }
                }

                // HTML5のフォームバリデーション対応
                self::addHtml5Validation($this->validate, $attrList);
            }

            // 結果出力
            if ($autoincludeDepth) {
                $autoincludeTemplate .= $compiledTag;
            } else {
                $compiledTemplate .= $compiledTag;
            }
        }

        // auto includeを呼び出された場所に移動
        if ($autoincludeTemplate) {
            if (false !== strpos($compiledTemplate, '{{REPLACE_TO_AUTOINCLUDE}}')) {
                $compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', $autoincludeTemplate, $compiledTemplate);
            }
        } else {
            $compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', '', $compiledTemplate);
        }

        // 開始スクリプトを追加
        $initScript = self::getInitialScript();
        $compiledTemplate = $initScript.$compiledTemplate;

        // タグを削除してキャッシュを整形
        $compiledTemplate = preg_replace("/\?\>\n\<\?php/", "\n", $compiledTemplate);

        self::cleanupOption($this->option);

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

    /* data-includeのメソッド */

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

    private function getAutoincludeDir()
    {
        if (!empty($GLOBALS['BLOCS_AUTOINCLUDE_DIR'])) {
            $autoincludeDir = $GLOBALS['BLOCS_AUTOINCLUDE_DIR'];
        } elseif (defined('BLOCS_ROOT_DIR')) {
            $autoincludeDir = BLOCS_ROOT_DIR.'/autoinclude';
        }

        if (empty($autoincludeDir) || !is_dir($autoincludeDir)) {
            return false;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $autoincludeDir);
    }

    // テンプレートの初期処理
    private static function getInitialScript()
    {
        return <<< END_of_HTML
<?php
    \$_appendOption = \\Blocs\\Option::append();
    extract(\$_appendOption, EXTR_PREFIX_ALL, 'option');

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

    private static function cleanupOption(&$thisOption)
    {
        $idList = [];
        foreach ($thisOption as $num => $buff) {
            if (isset($buff['value']) && !strncmp($buff['value'], '<?php', 5)) {
                unset($thisOption[$num]);
                continue;
            }

            if (!isset($buff['id'])) {
                if (!isset($buff['name']) || !isset($buff['value'])) {
                    unset($thisOption[$num]);
                }
                continue;
            }

            if (isset($idList[$buff['id']])) {
                $thisOption[$num] = array_merge($buff, $thisOption[$idList[$buff['id']]]);
                unset($thisOption[$idList[$buff['id']]]);
            }
            $idList[$buff['id']] = $num;
        }
        $thisOption = array_merge($thisOption);

        $optionItemList = [];
        foreach ($thisOption as $num => $buff) {
            if (!isset($buff['name']) || !isset($buff['value']) || !isset($buff['label'])) {
                continue;
            }
            if (empty($buff['type']) || !('radio' === $buff['type'] || 'checkbox' === $buff['type'] || 'select' === $buff['type'])) {
                continue;
            }

            $optionItemList[$buff['name']][] = $buff;
        }
        $thisOption = $optionItemList;
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

    private static function escapeQuestionTag($rawString)
    {
        $rawString = explode('<?', $rawString);
        $resultBuff = array_shift($rawString);
        foreach ($rawString as $buff) {
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
}
