<?php

namespace Blocs\Compiler;

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
    use BlocsCompilerTrait;
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

    // 処理中のdata-bloc
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
                    // コメント記法を処理
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

            // タグ記法を処理
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
}
