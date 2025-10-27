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
        $config = Common::readConfig($path);

        if (parent::isExpired($path)) {
            return true;
        }

        // includeファイルの更新を確認
        if (isset($config['include'][$path]) && is_array($config['include'][$path])) {
            foreach ($config['include'][$path] as $includeFile) {
                if (! file_exists($includeFile) || filemtime($includeFile) > $config['timestamp'][$path]) {
                    return true;
                }
            }
        }

        return false;
    }

    // Bladeを参照
    public function compile($path)
    {
        // Blocsを適用
        $blocsCompiler = new Compiler\BlocsCompiler;
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
