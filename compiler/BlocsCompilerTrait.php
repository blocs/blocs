<?php

namespace Blocs\Compiler;

use Blocs\Compiler\Cache\Common;

trait BlocsCompilerTrait
{
    private array $include;

    private array $filter;

    private array $option;

    // バリデーション変数
    private array $validate;

    private array $validateMessage;

    private array $validateUpload;

    private array $dataAttribute;

    private array $endloop;

    // タグ記法のための変数
    private array $tagCounter;

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

    private array $assignedValue;

    // オプション変数
    private array $label;

    private array $optionArray;

    private array $labelArray;

    // dummyを付与済フラグ
    private array $dummyArray;

    private $scriptCounter;

    private $selectName;

    // コンパイル後の文字列
    private $compiledTemplate;

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

    private function addAutoincludeClass(&$htmlArray)
    {
        $autoincludeDir = self::getAutoincludeDir();
        if ($autoincludeDir === false) {
            return;
        }

        // auto includeの候補
        if (! count($this->autoincludeClass)) {
            return;
        }
        $this->autoincludeClass = array_merge(array_unique($this->autoincludeClass));

        $targetFileList = scandir($autoincludeDir);
        foreach ($targetFileList as $targetFile) {
            if (substr($targetFile, 0, 1) == '.') {
                continue;
            }
            $targetFile = $autoincludeDir.'/'.$targetFile;
            if (! is_file($targetFile)) {
                continue;
            }

            $autoinclude = pathinfo($targetFile, PATHINFO_FILENAME);

            if (! in_array($autoinclude, $this->autoincludeClass)) {
                // classでのコールされていないのでauto includeしない
                continue;
            }

            if (isset($this->autoincluded[$autoinclude])) {
                // すでにincludeされている
                continue;
            }

            $htmlArray[] = '<!-- '.BLOCS_DATA_INCLUDE."='".str_replace(BLOCS_ROOT_DIR, '', $autoincludeDir).'/'.$autoinclude.".html' -->";
        }

    }

    private function getAutoincludeDir()
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

    private static function escapeQuestionTag($rawString)
    {
        $rawString = explode('<?', $rawString);
        $resultBuff = array_shift($rawString);
        foreach ($rawString as $buff) {
            $resultBuff .= '<?';
            if (strpos($buff, '?>') === false) {
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
