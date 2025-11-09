<?php

namespace Blocs;

use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\ViewServiceProvider;

class ServiceProvider extends ViewServiceProvider
{
    public function register()
    {
        $this->registerBlocsCompiler();
    }

    public function boot()
    {
        // 定数の読み込み
        require_once 'Consts.php';

        $this->registerExtension();
    }

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
