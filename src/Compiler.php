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

        // includeファイルの更新を確認し最新状態を担保する
        if ($this->hasUpdatedInclude($path, $config)) {
            return true;
        }

        return false;
    }

    public function compile($path)
    {
        // Blocsを適用して拡張テンプレートをコンパイルする
        $blocsCompiler = new Compiler\BlocsCompiler;
        $compiledContents = $blocsCompiler->compile($path);

        // 設定ファイルを作成しキャッシュを最新化する
        $blocsConfig = $blocsCompiler->getConfig();
        Common::writeConfig($path, $blocsConfig);

        $bladeCompiler = $this->resolveBladeCompiler();
        $compiledContents = $bladeCompiler->compileString($compiledContents);

        $this->files->put(
            $this->getCompiledPath($path),
            $compiledContents
        );
    }

    private function hasUpdatedInclude($path, array $config)
    {
        if (! isset($config['include'][$path]) || ! is_array($config['include'][$path])) {
            return true;
        }

        $timestamp = $config['timestamp'][$path] ?? null;

        foreach ($config['include'][$path] as $includeFile) {
            if (! file_exists($includeFile)) {
                return true;
            }

            if (! isset($timestamp)) {
                return true;
            }

            if (filemtime($includeFile) > $timestamp) {
                return true;
            }
        }

        return false;
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
