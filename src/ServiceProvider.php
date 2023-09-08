<?php

namespace Blocs;

use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\ViewServiceProvider;

class ServiceProvider extends ViewServiceProvider
{
    // Bladeを参照
    public function register()
    {
        $this->registerBlocsCompiler();
    }

    // Bladeを参照
    public function boot()
    {
        // 定数の読み込み
        require_once 'Consts.php';

        $this->registerExtension();
    }

    // Bladeを参照
    public function registerBlocsCompiler()
    {
        $this->app->singleton('blocs.compiler', fn ($app) => new Compiler($app['files'], $app['config']['view.compiled']));
    }

    protected function registerExtension()
    {
        $this->app['view']->addExtension(
            'blocs.html',
            'blocs',
            fn () => new CompilerEngine($this->app['blocs.compiler'])
        );
    }
}
