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

    public $label;
}

class BlocsCompiler
{
    use BlocsCompilerTrait;
    use CompileCommentTrait;
    use CompileTagTrait;

    // コンパイル
    public function compile($templatePath)
    {
        $this->init();
        $this->include = [str_replace(DIRECTORY_SEPARATOR, '/', $templatePath)];

        $this->compileTemplate(self::checkEncoding($templatePath), $templatePath);

        return $this->compiledTemplate;
    }

    // コンパイルしてレンダリング
    public function render($writeBuff, $val = [])
    {
        $this->init();

        // Bladeディレクティブを使わない
        defined('BLOCS_BLADE_OFF') || define('BLOCS_BLADE_OFF', true);

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
            if (strpos($formName, '<?php') !== false) {
                unset($this->validate[$formName]);
            }
        }

        $blocsConfig = new BlocsConfig;
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

            if (! count($htmlArray) && ! isset($autoincludeFlg)) {
                // classでのauto includeは最後に一回だけ
                $this->addAutoincludeClass($htmlArray);
                $autoincludeFlg = true;
            }

            if (! is_array($htmlBuff)) {
                /* テキストとコメントを処理 */

                // autoincludeの深さを取得
                if ($htmlBuff === '{{AUTOINCLUDE_START_FROM}}') {
                    $this->autoincludeDepth++;

                    continue;
                }
                if ($htmlBuff === '{{AUTOINCLUDE_END_TO}}') {
                    $this->autoincludeDepth--;

                    continue;
                }

                if ($this->ignoreFlg) {
                    continue;
                }

                // ラベルを取得
                isset($this->optionArray['label']) && $this->optionArray['label'] .= $htmlBuff;
                isset($this->labelArray['label']) && $this->labelArray['label'] .= $htmlBuff;

                if (! strncmp($htmlBuff, '<!', 2) && $this->isPart() < 2) {
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
            if (strpos($this->compiledTemplate, '{{REPLACE_TO_AUTOINCLUDE}}') !== false) {
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
