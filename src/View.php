<?php

namespace Blocs;

function setTemplateCacheDir()
{
    define('BLOCS_NO_LARAVEL', true);

    $key = '/tmp';
    if (($key = str_replace(DIRECTORY_SEPARATOR, '/', realpath($key))) && is_dir($key) && is_writable($key)) {
        define('BLOCS_CACHE_DIR', $key.'/');

        return;
    }

    foreach (['TMPDIR', 'TMP', 'TEMP', 'USERPROFILE'] as $key) {
        if (! empty($_ENV[$key]) && ($key = str_replace(DIRECTORY_SEPARATOR, '/', realpath($_ENV[$key]))) && is_dir($key) && is_writable($key)) {
            define('BLOCS_CACHE_DIR', $key.'/');

            return;
        }
    }

    $key = ini_get('upload_tmp_dir');
    if (! empty($key) && ($key = str_replace(DIRECTORY_SEPARATOR, '/', realpath($key))) && is_dir($key) && is_writable($key)) {
        define('BLOCS_CACHE_DIR', $key.'/');

        return;
    }

    trigger_error('B001: Can not write cache file into directory', E_USER_ERROR);
}

defined('BLOCS_CACHE_DIR') || setTemplateCacheDir();
defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', $_SERVER['DOCUMENT_ROOT']);

require_once 'Consts.php';

class View
{
    private $filename;

    private $config;

    public function __construct($filename)
    {
        function_exists('mb_internal_encoding') && mb_internal_encoding('UTF-8');

        $this->filename = $filename;

        // 設定ファイルを読み込み
        $this->config = Common::readConfig($this->getPath());
    }

    // HTML文字列を生成
    public function generate($val = [], $withFixer = false)
    {
        empty($val) && $val = [];

        // キャッシュを確認してコンパイル済みテンプレートのパスを取得
        $compiledPath = $this->resolveCompiledPath();

        // 引数をセット
        extract($val);

        ob_start();
        include $compiledPath;
        $renderedHtml = ob_get_clean();

        // HTMLの整形処理を実行
        $withFixer && $renderedHtml = $this->formatHtmlOutput($renderedHtml);

        return $renderedHtml;
    }

    // HTMLを即時出力
    public function output($val = [], $withFixer = false)
    {
        empty($val) && $val = [];

        $renderedHtml = $this->generate($val, $withFixer);
        echo $renderedHtml;
        exit;
    }

    // テンプレートの実ファイルパスを取得
    public function getPath()
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', realpath($this->filename));
    }

    private function resolveCompiledPath()
    {
        $path = $this->getPath();
        $compiledPath = BLOCS_CACHE_DIR.'/'.md5($path).'.php';

        (is_file($this->filename) && strlen($path)) || trigger_error('B003: Can not find template ('.getcwd().'/'.$this->filename.')', E_USER_ERROR);

        $shouldUpdateCache = $this->shouldUpdateCache($compiledPath, $path);
        if (! $shouldUpdateCache) {
            // 更新なしのため既存キャッシュを利用
            return $compiledPath;
        }

        // Blocsのコンパイラを実行
        $blocsCompiler = new Compiler\BlocsCompiler;
        $compiledContents = $blocsCompiler->compile($path);

        // 設定ファイルを作成してキャッシュ情報を保持
        $blocsConfig = $blocsCompiler->getConfig();
        Common::writeConfig($path, $blocsConfig);

        file_put_contents($compiledPath, $compiledContents) && chmod($compiledPath, 0666);

        return $compiledPath;
    }

    private function shouldUpdateCache($compiledPath, $path)
    {
        if (! file_exists($compiledPath)) {
            // キャッシュが未生成
            return true;
        }

        if (! isset($this->config['include'][$path]) || ! is_array($this->config['include'][$path])) {
            return false;
        }

        foreach ($this->config['include'][$path] as $includeFile) {
            if (! file_exists($includeFile) || filemtime($includeFile) > $this->config['timestamp'][$path]) {
                return true;
            }
        }

        return false;
    }

    private function formatHtmlOutput($outputHtml)
    {
        $head = strtolower(substr(trim($outputHtml), 0, 9));
        if ($head != '<!doctype' && substr($head, 0, 5) != '<html') {
            // HTML以外は整形対象外
            return $outputHtml;
        }

        // コメントと余分な改行を整理
        $contents = preg_split("/<\s*(textarea|pre)/si", $outputHtml, -1, PREG_SPLIT_DELIM_CAPTURE);

        $formattedHtml = '';
        $replaceTag = '';
        foreach ($contents as $content) {
            if (in_array(strtolower($content), ['textarea', 'pre'])) {
                $formattedHtml .= '<'.$content;
                empty($replaceTag) && $replaceTag = $content;

                continue;
            }

            if (! empty($replaceTag)) {
                $buffList = preg_split("/<\s*\/\s*{$replaceTag}/si", $content, 2);
                if (count($buffList) < 2) {
                    // 特定のタグでは整形処理をスキップ
                    $formattedHtml .= $content;

                    continue;
                } else {
                    $formattedHtml .= $buffList[0];
                    $content = '</'.$replaceTag.$buffList[1];
                    $replaceTag = '';
                }
            }

            $content = preg_replace('/<!--[\s\S]*?-->/s', '', $content);
            $content = preg_replace("/\n[\s\n]+\n/", "\n\n", $content);

            $formattedHtml .= $content;
        }

        return $formattedHtml;
    }
}
