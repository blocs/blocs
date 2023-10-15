<?php

namespace Blocs;

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

    // HTMLを生成
    public function generate($val = [], $withFixer = false)
    {
        empty($val) && $val = [];

        // キャッシュをチェック
        $compiledPath = $this->checkCache();

        // 引数をセット
        extract($val);

        ob_start();
        include $compiledPath;
        $writeBuff = ob_get_clean();

        // HTMLを整形
        $withFixer && $writeBuff = $this->fixerOutput($writeBuff);

        return $writeBuff;
    }

    // HTMLを出力
    public function output($val = [], $withFixer = false)
    {
        empty($val) && $val = [];

        echo $this->generate($val, $withFixer);
        exit;
    }

    // テンプレートのパスを取得
    public function getPath()
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', realpath($this->filename));
    }

    private function checkCache()
    {
        $path = $this->getPath();
        $compiledPath = BLOCS_CACHE_DIR.'/'.md5($path).'.php';

        (is_file($this->filename) && strlen($path)) || trigger_error('B003: Can not find template ('.getcwd().'/'.$this->filename.')', E_USER_ERROR);

        // タイムスタンプをチェック
        $updateCache = false;
        if (!file_exists($compiledPath)) {
            // キャッシュがない
            $updateCache = true;
        } elseif (isset($config['include'][$path]) && is_array($config['include'][$path])) {
            foreach ($this->config['include'][$path] as $includeFile) {
                if (filemtime($includeFile) > $this->config['timestamp'][$path]) {
                    $updateCache = true;
                    break;
                }
            }
        }

        if (!$updateCache) {
            // 更新なし
            return $compiledPath;
        }

        // Blocsを適用
        $blocsCompiler = new Compiler\BlocsCompiler();
        $contents = $blocsCompiler->compile($path);

        // 設定ファイルを作成
        $blocsConfig = $blocsCompiler->getConfig();
        Common::writeConfig($path, $blocsConfig);

        file_put_contents($compiledPath, $contents) && chmod($compiledPath, 0666);

        return $compiledPath;
    }

    private function fixerOutput($writeBuff)
    {
        $head = strtolower(substr(trim($writeBuff), 0, 9));
        if ('<!doctype' != $head && '<html' != substr($head, 0, 5)) {
            // HTML以外は整形しない
            return $writeBuff;
        }

        // コメントの削除、不要な改行を削除
        $contents = preg_split("/<\s*(textarea|pre)/si", $writeBuff, -1, PREG_SPLIT_DELIM_CAPTURE);

        $writeBuff = '';
        $replaceTag = '';
        foreach ($contents as $content) {
            if (in_array(strtolower($content), ['textarea', 'pre'])) {
                $writeBuff .= '<'.$content;
                empty($replaceTag) && $replaceTag = $content;
                continue;
            }

            if (!empty($replaceTag)) {
                $buffList = preg_split("/<\s*\/\s*{$replaceTag}/si", $content, 2);
                if (count($buffList) < 2) {
                    // 特定のタグでは整形しない
                    $writeBuff .= $content;
                    continue;
                } else {
                    $writeBuff .= $buffList[0];
                    $content = '</'.$replaceTag.$buffList[1];
                    $replaceTag = '';
                }
            }

            $content = preg_replace('/<!--[\s\S]*?-->/s', '', $content);
            $content = preg_replace("/\n[\s\n]+\n/", "\n\n", $content);

            $writeBuff .= $content;
        }

        return $writeBuff;
    }
}
