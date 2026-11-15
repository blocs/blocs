<?php

namespace Blocs;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\Compiler as ViewCompiler;
use Illuminate\View\Compilers\CompilerInterface;

class Compiler extends ViewCompiler implements CompilerInterface
{
    public function isExpired($path)
    {
        // 設定ファイルを読み込み最新の設定を取得する
        $config = Common::readConfig($path);

        if (parent::isExpired($path)) {
            return true;
        }

        if (property_exists($this, 'shouldCheckTimestamps') && ! $this->shouldCheckTimestamps) {
            // view.check_cache_timestamps が false の場合は、includeファイルの更新も確認しない
            return false;
        }

        // includeファイルの更新を確認し最新状態を担保する
        return Common::includesAreStale($path, $config);
    }

    public function compile($path)
    {
        // Blocsを適用して拡張テンプレートをコンパイルし、書き出せたら設定ファイルを最新化する
        Common::compileTemplate($path, function (string $compiledContents) use ($path) {
            $compiledContents = $this->compileBlade($compiledContents, $path);

            $compiledPath = $this->getCompiledPath($path);
            method_exists($this, 'ensureCompiledDirectoryExists') && $this->ensureCompiledDirectoryExists($compiledPath);

            // 書きかけのファイルを他のリクエストが読み込まないよう、置き換えで書き込む
            if (method_exists($this->files, 'replace')) {
                $this->files->replace($compiledPath, $compiledContents);
            } else {
                $this->files->put($compiledPath, $compiledContents);
            }
        });
    }

    /**
     * Blade としてコンパイルし、Blade と同じくテンプレートのパスを末尾に記録する
     * （エラー画面がコンパイル済みファイルではなくテンプレートを指せるようにする）
     */
    private function compileBlade(string $contents, string $path): string
    {
        $bladeCompiler = $this->resolveBladeCompiler();

        // 共有の Blade コンパイラが直前にコンパイルしたビューのパスを持っているので、一時的に差し替える
        $previousPath = $bladeCompiler->getPath();
        $bladeCompiler->setPath($path);

        try {
            $contents = $bladeCompiler->compileString($contents);
        } finally {
            $bladeCompiler->setPath($previousPath);
        }

        $phpTagTokens = array_filter(
            token_get_all($contents),
            fn ($token) => is_array($token) && in_array($token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG], true)
        );
        $lastPhpTagToken = end($phpTagTokens);
        if ($lastPhpTagToken !== false && $lastPhpTagToken[0] !== T_CLOSE_TAG) {
            $contents .= ' ?>';
        }

        return $contents."<?php /**PATH {$path} ENDPATH**/ ?>";
    }

    /**
     * アプリに登録された Blade コンパイラを使う（カスタムディレクティブ / @vite などを引き継ぐ）
     */
    private function resolveBladeCompiler(): BladeCompiler
    {
        if (function_exists('app') && app()->bound('blade.compiler')) {
            return app('blade.compiler');
        }

        return new BladeCompiler($this->files, $this->cachePath);
    }
}
