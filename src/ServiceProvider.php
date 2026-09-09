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
        $this->registerStaticStateFlush();
    }

    /**
     * 常駐ワーカー（Laravel Octane）でリクエスト開始時に静的な状態を初期化する。
     *
     * Common / Option はテンプレート設定と動的選択肢を静的プロパティに保持しており、
     * PHP-FPM ではリクエスト終了とともに消えるが、Octane ではワーカーが生きている間残り続ける。
     * Octane が導入されていない環境では何もしない。
     */
    protected function registerStaticStateFlush()
    {
        $requestReceived = 'Laravel\\Octane\\Events\\RequestReceived';
        if (! class_exists($requestReceived)) {
            return;
        }

        $this->app['events']->listen($requestReceived, function () {
            Common::flush();
            Option::flush();
        });
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
