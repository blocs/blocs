<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

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

    // autoincludeのコンパイル後の文字列
    private $autoincludeDepth;
    private $autoincludeTemplate;

    private static $allAttrName;
    private static array $assignedValue;

    private array $optionArray;
    private array $labelArray;

    // dummyを付与済フラグ
    private array $dummyArray;

    private $scriptCounter;
    private $selectName;

    // コンパイル後の文字列
    private $compiledTemplate;

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

        $this->autoincludeDepth = 0;
        $this->autoincludeTemplate = '';

        if (!isset(self::$allAttrName)) {
            $allConstant = get_defined_constants();
            foreach ($allConstant as $key => $value) {
                strncmp($key, 'BLOCS_DATA_', 11) || self::$allAttrName[] = $value;
            }
        }

        self::$assignedValue = [];

        $this->optionArray = [];
        $this->labelArray = [];
        $this->dummyArray = [];

        $this->scriptCounter = 0;
        $this->selectName = '';

        $this->compiledTemplate = '';
    }

    // Bladeを参照
    public function compile($templatePath)
    {
        $this->include = [$templatePath];
        $this->compileTemplate(self::checkEncoding($templatePath), $templatePath);

        return $this->compiledTemplate;
    }

    public function template($writeBuff, $val = [])
    {
        $this->compileTemplate($writeBuff, __FILE__);

        // 引数をセット
        extract($val);

        ob_start();
        eval(substr($this->compiledTemplate, 5));
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
                    ++$this->autoincludeDepth;
                    continue;
                }
                if ('{{AUTOINCLUDE_END_TO}}' === $htmlBuff) {
                    --$this->autoincludeDepth;
                    continue;
                }

                if ($this->ignoreFlg) {
                    continue;
                }

                // ラベルを取得
                isset($this->optionArray['label']) && $this->optionArray['label'] .= $htmlBuff;
                isset($this->labelArray['label']) && $this->labelArray['label'] .= $htmlBuff;

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
                if ($this->autoincludeDepth) {
                    $this->autoincludeTemplate .= self::escapeQuestionTag($htmlBuff);
                } else {
                    $this->compiledTemplate .= self::escapeQuestionTag($htmlBuff);
                }

                continue;
            }

            /* タグを処理 */
            $compiledTag = $this->compileTag($htmlBuff, $htmlArray);

            // 結果出力
            if ($this->autoincludeDepth) {
                $this->autoincludeTemplate .= $compiledTag;
            } else {
                $this->compiledTemplate .= $compiledTag;
            }
        }

        // auto includeを呼び出された場所に移動
        if ($this->autoincludeTemplate) {
            if (false !== strpos($this->compiledTemplate, '{{REPLACE_TO_AUTOINCLUDE}}')) {
                $this->compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', $this->autoincludeTemplate, $this->compiledTemplate);
            }
        } else {
            $this->compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', '', $this->compiledTemplate);
        }

        // 開始スクリプトを追加
        $initScript = self::getInitialScript();
        $this->compiledTemplate = $initScript.$this->compiledTemplate;

        // タグを削除してキャッシュを整形
        $this->compiledTemplate = preg_replace("/\?\>\n\<\?php/", "\n", $this->compiledTemplate);

        self::cleanupOption($this->option);

        return $this->compiledTemplate;
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
            $autoincludeDir = realpath(__DIR__.'/../../autoinclude');
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
