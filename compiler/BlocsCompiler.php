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

    // テンプレートファイルをコンパイルする
    public function compile($templatePath)
    {
        $this->init();
        $this->include = [str_replace(DIRECTORY_SEPARATOR, '/', $templatePath)];

        $this->compileTemplate(self::checkEncoding($templatePath), $templatePath);

        return $this->compiledTemplate;
    }

    // バッファをコンパイルしてレンダリングする
    public function render($writeBuff, $val = [])
    {
        $this->init();

        // Bladeディレクティブを無効化する
        defined('BLOCS_BLADE_OFF') || define('BLOCS_BLADE_OFF', true);

        $this->compileTemplate($writeBuff, __FILE__);

        // 渡された引数を展開する
        extract($val);

        ob_start();
        eval(substr($this->compiledTemplate, 5));
        $writeBuff = ob_get_clean();

        // 余分な改行を削除する
        $writeBuff = preg_replace("/\n[\s\n]+\n/", "\n\n", $writeBuff);

        return $writeBuff;
    }

    // テンプレートの設定を取得する
    // テンプレートの設定はディレクトリ単位でまとめて保持する
    // optionなどを同じディレクトリ配下のテンプレートで共有するため
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
                // classでのauto includeは最後に一度だけ処理する
                $this->appendAutoincludeCandidates($htmlArray);
                $autoincludeFlg = true;
            }

            if (! is_array($htmlBuff)) {
                /* テキストとコメントを処理する */

                // autoincludeの深さを追跡する
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

                // ラベル文字列を蓄積する
                isset($this->optionArray['label']) && $this->optionArray['label'] .= $htmlBuff;
                isset($this->labelArray['label']) && $this->labelArray['label'] .= $htmlBuff;

                if (! strncmp($htmlBuff, '<!', 2) && $this->getPartProcessingState() < 2) {
                    // タグ記法でブロック処理中はコメント記法を実行しない
                    // コメント記法の指示を処理する
                    $this->processCommentDirective($htmlBuff, $htmlArray);
                }

                // ブロック単位でタグを保持する
                if ($this->getPartProcessingState()) {
                    $this->partInclude[$this->partName][] = $htmlBuff;

                    continue;
                }

                // コンパイル結果へ書き込む
                if ($this->autoincludeDepth) {
                    $this->autoincludeTemplate .= self::escapePhpShortTag($htmlBuff);
                } else {
                    $this->compiledTemplate .= self::escapePhpShortTag($htmlBuff);
                }

                continue;
            }

            // タグ記法を処理する
            $compiledTag = $this->processTagDirective($htmlBuff, $htmlArray);

            // コンパイル結果へ書き込む
            if ($this->autoincludeDepth) {
                $this->autoincludeTemplate .= $compiledTag;
            } else {
                $this->compiledTemplate .= $compiledTag;
            }
        }

        // auto includeの結果を呼び出し箇所に差し込む
        if ($this->autoincludeTemplate) {
            if (strpos($this->compiledTemplate, '{{REPLACE_TO_AUTOINCLUDE}}') !== false) {
                $this->compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', $this->autoincludeTemplate, $this->compiledTemplate);
            }
        } else {
            $this->compiledTemplate = str_replace('{{REPLACE_TO_AUTOINCLUDE}}', '', $this->compiledTemplate);
        }

        // 初期化用スクリプトを冒頭に追加する
        $initScript = self::buildInitialScript();
        $this->compiledTemplate = $initScript.$this->compiledTemplate;

        // PHPタグの間の改行を調整する
        $this->compiledTemplate = preg_replace("/\?\>\n\<\?php/", "\n", $this->compiledTemplate);

        self::normalizeOptionConfig($this->option);

        return $this->compiledTemplate;
    }
}
