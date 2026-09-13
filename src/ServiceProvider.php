<?php

namespace Blocs;

use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\ViewServiceProvider;
use Laravel\Octane\Events\RequestReceived;

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
        $this->registerStaticStateFlush();
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

    /**
     * 常駐ワーカー（Laravel Octane）ではリクエストをまたいで static が残るため、
     * リクエスト開始時にテンプレート設定と動的選択肢を初期化する。Octane 未導入なら何もしない。
     */
    protected function registerStaticStateFlush()
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(RequestReceived::class, function () {
            Common::flush();
            Option::flush();
            Validate::flush();
            Compiler\BlocsCompiler::flush();
        });
    }
}
