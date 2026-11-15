<?php

namespace Blocs;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\View\Engines\CompilerEngine;
use Laravel\Octane\Events\RequestReceived;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->registerBlocsCompiler();
    }

    public function boot()
    {
        // 定数の読み込み
        require_once __DIR__.'/Consts.php';

        $this->registerExtension();
        $this->registerStaticStateFlush();
    }

    public function registerBlocsCompiler()
    {
        // Blade と同じビュー設定（view.cache など）に従う
        $this->app->singleton('blocs.compiler', fn ($app) => new Compiler(
            $app['files'],
            $app['config']['view.compiled'],
            $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
            $app['config']->get('view.cache', true),
            $app['config']->get('view.compiled_extension', 'php'),
            $app['config']->get('view.check_cache_timestamps', true),
        ));
    }

    protected function registerExtension()
    {
        // ビューを使わないリクエストでビューファクトリを生成しないよう、解決されたときに登録する
        $this->callAfterResolving('view', function ($view) {
            $view->addExtension('blocs.html', 'blocs', function () {
                $engine = new CompilerEngine($this->app['blocs.compiler']);

                // Blade と同じく、リクエスト終了時に「更新確認済み」の記憶を消す
                // （常駐ワーカーでも、次のリクエストでテンプレートの更新を確認させる）
                if (method_exists($engine, 'forgetCompiledOrNotExpired')) {
                    $this->app->terminating(static fn () => $engine->forgetCompiledOrNotExpired());
                }

                return $engine;
            });
        });
    }

    /**
     * 常駐ワーカーではリクエストやジョブをまたいで static が残るため、
     * リクエスト開始時（Laravel Octane）とジョブ開始時（キューワーカー）に、テンプレート設定と動的選択肢を初期化する。
     */
    protected function registerStaticStateFlush()
    {
        $flushStaticState = static function () {
            Common::flush();
            Option::flush();
            Validate::flush();
            Lang::flush();
            Compiler\BlocsCompiler::flush();
        };

        // Octane 未導入ならイベントが発行されないだけなので、登録しても害はない
        $this->app['events']->listen(RequestReceived::class, $flushStaticState);
        $this->app['events']->listen(JobProcessing::class, $flushStaticState);
    }
}
