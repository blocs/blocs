<?php

namespace Blocs;

function setTemplateCacheDir()
{
    define('BLOCS_NO_LARAVEL', true);

    $baseCandidates = ['/tmp'];
    foreach (['TMPDIR', 'TMP', 'TEMP', 'USERPROFILE'] as $key) {
        empty($_ENV[$key]) || $baseCandidates[] = $_ENV[$key];
    }
    $baseCandidates[] = ini_get('upload_tmp_dir');

    foreach ($baseCandidates as $baseCandidate) {
        if (empty($baseCandidate) || ! ($baseDir = realpath($baseCandidate)) || ! is_dir($baseDir) || ! is_writable($baseDir)) {
            continue;
        }

        $cacheDir = resolvePrivateCacheDir(str_replace(DIRECTORY_SEPARATOR, '/', $baseDir));
        if ($cacheDir !== null) {
            define('BLOCS_CACHE_DIR', $cacheDir.'/');

            return;
        }
    }

    throw Common::cacheWriteException();
}

/**
 * 共用の一時ディレクトリに直接キャッシュを置くと、他のユーザーにコンパイル済みファイルを
 * 差し替えられる余地があるため、実行ユーザー専用のディレクトリ（0700）を使う
 */
function resolvePrivateCacheDir(string $baseDir): ?string
{
    $owner = function_exists('posix_geteuid') ? posix_geteuid() : null;
    $cacheDir = $baseDir.'/blocs-'.($owner ?? substr(md5(get_current_user()), 0, 8));

    if (! is_dir($cacheDir)) {
        try {
            @mkdir($cacheDir, 0700);
        } catch (\Throwable) {
        }
    }

    if (! is_dir($cacheDir)) {
        return null;
    }

    if (is_link($cacheDir) || ! is_writable($cacheDir)) {
        return null;
    }

    if ($owner !== null && fileowner($cacheDir) !== $owner) {
        // 他のユーザーが先回りして作ったディレクトリは使わない
        return null;
    }

    return $cacheDir;
}

defined('BLOCS_CACHE_DIR') || setTemplateCacheDir();
defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', $_SERVER['DOCUMENT_ROOT'] ?? '');

require_once __DIR__.'/Consts.php';

class View
{
    private $filename;

    private $config;

    public function __construct($filename)
    {
        function_exists('mb_internal_encoding') && mb_internal_encoding('UTF-8');

        $this->filename = $filename;

        Common::ensureCacheDirectory();

        // 設定ファイルを読み込み
        $this->config = Common::readConfig($this->getPath());
    }

    // HTML文字列を生成
    public function generate($val = [], $withFixer = false)
    {
        empty($val) && $val = [];

        $compiledPath = $this->resolveCompiledPath();

        $renderedHtml = (static function (array $__blocs_val, string $__blocs_compiled): string {
            extract($__blocs_val, EXTR_SKIP);
            ob_start();
            include $__blocs_compiled;

            return (string) ob_get_clean();
        })($val, $compiledPath);

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
        return Common::normalizeRealPath($this->filename);
    }

    private function resolveCompiledPath()
    {
        $path = $this->getPath();
        $compiledPath = BLOCS_CACHE_DIR.'/'.md5($path).'.php';

        (is_file($this->filename) && strlen($path)) || throw new \RuntimeException('B003: Can not find template ('.getcwd().'/'.$this->filename.')');

        $shouldUpdateCache = $this->shouldUpdateCache($compiledPath, $path);
        if (! $shouldUpdateCache) {
            // 更新なしのため既存キャッシュを利用
            return $compiledPath;
        }

        // Blocsのコンパイラを実行し、書き出せたら設定ファイルを最新化する
        Common::compileTemplate($path, function (string $compiledContents) use ($compiledPath) {
            // 書きかけのファイルを他のリクエストが読み込まないよう、一時ファイルに書いてから置き換える
            $temporaryPath = $compiledPath.'.'.bin2hex(random_bytes(6)).'.tmp';
            if (file_put_contents($temporaryPath, $compiledContents) === false || ! rename($temporaryPath, $compiledPath)) {
                is_file($temporaryPath) && unlink($temporaryPath);

                throw Common::cacheWriteException();
            }
            chmod($compiledPath, 0644);
        });

        return $compiledPath;
    }

    private function shouldUpdateCache($compiledPath, $path)
    {
        if (! file_exists($compiledPath)) {
            // キャッシュが未生成
            return true;
        }

        if (! file_exists($this->filename) || filemtime($this->filename) > filemtime($compiledPath)) {
            return true;
        }

        return Common::includesAreStale($path, $this->config);
    }

    private function formatHtmlOutput($outputHtml)
    {
        $head = strtolower(substr(trim($outputHtml), 0, 9));
        if ($head != '<!doctype' && substr($head, 0, 5) != '<html') {
            // HTML以外は整形対象外
            return $outputHtml;
        }

        // コメントと余分な改行を整理
        // script・styleの中身はHTMLコメントではないため、textarea・preと同じく整形の対象外にする
        $keepTagList = ['textarea', 'pre', 'script', 'style'];
        $contents = preg_split('/<\s*('.implode('|', $keepTagList).')\b/si', $outputHtml, -1, PREG_SPLIT_DELIM_CAPTURE);

        $formattedHtml = '';
        $replaceTag = '';
        foreach ($contents as $content) {
            if (in_array(strtolower($content), $keepTagList, true)) {
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
