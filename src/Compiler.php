<?php

namespace Blocs;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\Compiler as ViewCompiler;
use Illuminate\View\Compilers\CompilerInterface;

class Compiler extends ViewCompiler implements CompilerInterface
{
    // Bladeを参照
    public function isExpired($path)
    {
        // 設定ファイルを読み込み
        Common::readConfig($path);

        return parent::isExpired($path);
    }

    // Bladeを参照
    public function compile($path)
    {
        // Blocsを適用
        $blocsCompiler = new Compiler\BlocsCompiler();
        $contents = $blocsCompiler->compile($path);

        // 設定ファイルを作成
        $blocsConfig = $blocsCompiler->getConfig();
        Common::writeConfig($path, $blocsConfig);

        // Bladeを適用
        $bladeCompiler = new BladeCompiler($this->files, $this->cachePath);
        $contents = $bladeCompiler->compileString($contents);

        $this->files->put(
            $this->getCompiledPath($path),
            $contents
        );
    }
}
