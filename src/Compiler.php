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

        // Bladeを適用して最終的なPHPコードへ変換する
        $bladeCompiler = new BladeCompiler($this->files, $this->cachePath);
        $compiledContents = $bladeCompiler->compileString($compiledContents);

        $this->files->put(
            $this->getCompiledPath($path),
            $compiledContents
        );
    }

    private function hasUpdatedInclude($path, array $config)
    {
        if (! isset($config['include'][$path]) || ! is_array($config['include'][$path])) {
            return false;
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
}
