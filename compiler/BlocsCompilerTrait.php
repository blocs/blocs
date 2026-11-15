<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait BlocsCompilerTrait
{
    private array $include;

    private array $filter;

    private array $option;

    // バリデーション関連の設定
    private array $validate;

    private array $validateMessage;

    private array $validateUpload;

    private array $dataAttribute;

    private array $endloop;

    // タグ記法のための変数
    private array $tagCounter;

    private bool $ignoreFlg;

    private $arrayFormName;

    // 処理中のdata-bloc名
    private $partName;

    // ファイルおよびブロックごとにタグを保持する
    private array $partInclude;

    // partDepth=0の時に$compiledTemplateに書き出す
    private int $partDepth;

    // クラス経由でincludeされるテンプレート候補
    private array $autoincludeClass;

    private array $autoincluded;

    // autoincludeの深さ
    private $autoincludeDepth;

    private $autoincludeTemplate;

    private array $assignedValue;

    // オプション関連の情報
    private array $label;

    private array $optionArray;

    private array $labelArray;

    // dummy付与済みかどうかのフラグ
    private array $dummyArray;

    private $scriptCounter;

    private $selectName;

    // コンパイル済みテンプレート
    private $compiledTemplate;

    // テンプレートに含まれるフォーム名（old() から展開してよい変数名）
    private array $formNameList;

    // コンパイル中のテンプレートファイル（バッファのレンダリングでは null）
    private ?string $templatePath = null;

    public function init()
    {
        $this->include = [];
        $this->filter = [];
        $this->option = [];

        $this->validate = [];
        $this->validateMessage = [];
        $this->validateUpload = [];

        $this->dataAttribute = [];
        $this->endloop = [];

        $this->tagCounter = [];
        $this->ignoreFlg = false;
        $this->arrayFormName = '';

        $this->partName = '';
        $this->partInclude = [];
        $this->partDepth = 0;

        $this->autoincludeClass = [];
        $this->autoincluded = [];

        $this->autoincludeDepth = 0;
        $this->autoincludeTemplate = '';

        $this->assignedValue = [];

        $this->label = [];
        $this->optionArray = [];
        $this->labelArray = [];
        $this->dummyArray = [];

        $this->scriptCounter = 0;
        $this->selectName = '';

        $this->compiledTemplate = '';

        $this->formNameList = [];
        $this->templatePath = null;
    }

    /**
     * old() から展開してよい変数名として、フォーム名を記録する
     */
    private function rememberFormName($formName): void
    {
        if (is_string($formName) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $formName)) {
            $this->formNameList[$formName] = true;
        }
    }

    private function getPartProcessingState()
    {
        if (strlen($this->partName)) {
            if ($this->partDepth) {
                // コメント記法でブロックを処理している
                return 1;
            } else {
                // タグ記法でブロックを処理している
                return 2;
            }
        }

        // ブロック処理の対象ではない
        return 0;
    }

    private function appendAutoincludeCandidates(&$htmlArray)
    {
        $autoincludeDir = self::resolveAutoincludeDirectory();
        if ($autoincludeDir === false) {
            return;
        }

        // auto include対象の候補一覧を正規化する
        if (! count($this->autoincludeClass)) {
            return;
        }
        $this->autoincludeClass = array_merge(array_unique($this->autoincludeClass));

        foreach (scandir($autoincludeDir) as $targetFile) {
            if (str_starts_with($targetFile, '.') || ! is_file($autoincludeDir.'/'.$targetFile)) {
                continue;
            }

            $autoinclude = pathinfo($targetFile, PATHINFO_FILENAME);

            // クラスから呼び出されていて、まだincludeしていないテンプレートだけをauto includeする
            if (in_array($autoinclude, $this->autoincludeClass) && ! isset($this->autoincluded[$autoinclude])) {
                $htmlArray[] = self::buildAutoincludeComment($autoincludeDir, $autoinclude);
            }
        }
    }

    // auto includeのテンプレートを読み込むコメント記法を組み立てる
    private static function buildAutoincludeComment($autoincludeDir, $autoinclude)
    {
        return '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->";
    }

    // auto includeディレクトリにあるテンプレートか
    private static function isAutoincludeTemplate($realpath): bool
    {
        $autoincludeDir = self::resolveAutoincludeDirectory();

        return $autoincludeDir !== false && str_starts_with($realpath, rtrim($autoincludeDir, '/').'/');
    }

    // テンプレートの基準ディレクトリを切り替えるコメント記法を組み立てる
    private static function buildChdirComment($directory)
    {
        return '<!-- '.BLOCS_DATA_CHDIR.'="'.$directory.'" -->';
    }

    private static function resolveAutoincludeDirectory()
    {
        if (! empty($GLOBALS['BLOCS_AUTOINCLUDE_DIR'])) {
            $autoincludeDir = $GLOBALS['BLOCS_AUTOINCLUDE_DIR'];
        } elseif (defined('BLOCS_ROOT_DIR')) {
            $autoincludeDir = BLOCS_ROOT_DIR.'/autoinclude';
        }

        if (empty($autoincludeDir) || ! is_dir($autoincludeDir)) {
            $autoincludeDir = realpath(__DIR__.'/../../autoinclude');
        }

        if (empty($autoincludeDir) || ! is_dir($autoincludeDir)) {
            return false;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $autoincludeDir);
    }

    // テンプレート実行前の初期処理を組み立てる
    private function buildInitialScript()
    {
        // 表示中のテンプレートを覚えておき、選択肢のラベル変換に使う
        $templatePath = var_export($this->templatePath, true);

        // old() はリクエスト由来の値なので、テンプレートにあるフォーム名だけを変数として展開する
        $formNameList = var_export($this->formNameList, true);

        return <<< END_of_HTML
<?php
    \$__blocsPath = {$templatePath};

    \$_appendOption = \\Blocs\\Option::append();
    extract(\$_appendOption, EXTR_PREFIX_ALL, 'option');

    if (! defined('BLOCS_NO_LARAVEL')) {
        \$_oldValue = old();
        !empty(\$_oldValue) && is_array(\$_oldValue) && extract(array_intersect_key(\$_oldValue, {$formNameList}), EXTR_SKIP);

        \$_userData = \Auth::user();

        !empty(\$_userData) && \$_userData = \$_userData->toArray();
        !empty(\$_userData) && is_array(\$_userData) && extract(\$_userData, EXTR_PREFIX_ALL, 'auth');
    }

    \$_ = function(\$s){ return \$s; };
?>

END_of_HTML;
    }

    private static function normalizeOptionConfig(&$thisOption)
    {
        $idList = [];
        foreach ($thisOption as $num => $buff) {
            if (isset($buff['value']) && ! strncmp($buff['value'], '<?php', 5)) {
                unset($thisOption[$num]);

                continue;
            }

            if (! isset($buff['id'])) {
                if (! isset($buff['name']) || ! isset($buff['value'])) {
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
            if (! isset($buff['name']) || ! isset($buff['value']) || ! isset($buff['label'])) {
                continue;
            }
            if (empty($buff['type']) || ! ($buff['type'] === 'radio' || $buff['type'] === 'checkbox' || $buff['type'] === 'select')) {
                continue;
            }

            $optionItemList[$buff['name']][] = $buff;
        }
        $thisOption = $optionItemList;
    }

    private static function escapePhpShortTag($rawString)
    {
        $rawString = explode('<?', $rawString);
        $resultBuff = array_shift($rawString);
        foreach ($rawString as $buff) {
            $resultBuff .= '<?';
            if (strpos($buff, '?>') === false) {
                // 閉じタグがないので囲み直せない。そのまま出力して次へ進む
                $resultBuff .= $buff;

                continue;
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
