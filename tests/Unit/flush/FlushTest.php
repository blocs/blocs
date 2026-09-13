<?php

namespace flush;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Compiler\BlocsCompiler;
use Blocs\Option;
use Blocs\Tests\TestEnvironment;
use Blocs\Validate;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 常駐ワーカー（Octane）向けに、リクエスト開始時へ差し込む flush() が
 * Common / Option の静的な状態を確実に破棄することを確認する。
 */
class FlushTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();

        // テンプレート設定の再構築（コンパイル）に必要な定数
        defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', dirname(__DIR__));
        require_once dirname(__DIR__, 3).'/src/Consts.php';
    }

    #[Test, RunInSeparateProcess]
    public function option_flush_discards_appended_options(): void
    {
        Option::add('category', ['a' => 'A', 'b' => 'B']);
        $this->assertArrayHasKey('category', Option::append());

        Option::flush();

        $this->assertSame([], Option::append());
    }

    #[Test, RunInSeparateProcess]
    public function common_flush_discards_last_template_config(): void
    {
        $templatePath = __DIR__.'/test.html';
        $config = Common::readConfig($templatePath);
        $this->assertNotEmpty($config);

        // 引数なしの readConfig() は直近のテンプレート設定を返す
        $this->assertSame($config, Common::readConfig());

        Common::flush();

        // 破棄後は「テンプレート未指定」として空配列になる
        $this->assertSame([], Common::readConfig());
    }

    #[Test, RunInSeparateProcess]
    public function validate_flush_discards_cached_template_config(): void
    {
        $templatePath = __DIR__.'/test.html';
        Common::readConfig($templatePath);

        $configProperty = new \ReflectionProperty(Validate::class, 'config');
        $configProperty->setValue(null, ['stale' => true]);

        Validate::flush();

        $this->assertNull($configProperty->getValue());
    }

    #[Test, RunInSeparateProcess]
    public function render_does_not_leave_blade_off_enabled(): void
    {
        $compiler = new BlocsCompiler;
        $compiler->render('hi');

        $this->assertFalse(defined('BLOCS_BLADE_OFF'));
        $this->assertFalse(BlocsCompiler::isBladeOff());
    }

    #[Test, RunInSeparateProcess]
    public function compile_restores_blade_off_depth_and_matches_blade_on_output(): void
    {
        $templatePath = dirname(__DIR__).'/data_loop/test.html';
        $compiler = new BlocsCompiler;
        $expected = $compiler->compile($templatePath);

        $depth = new \ReflectionProperty(BlocsCompiler::class, 'bladeOffDepth');
        $depth->setValue(null, 2);

        $compiled = (new BlocsCompiler)->compile($templatePath);

        $this->assertSame(2, $depth->getValue());
        $this->assertSame($expected, $compiled);
    }

    #[Test, RunInSeparateProcess]
    public function compile_restores_working_directory_when_include_is_missing(): void
    {
        $original = getcwd();
        $this->assertNotFalse($original);

        $templateDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'blocs-chdir-'.uniqid('', true);
        mkdir($templateDir);
        $templatePath = $templateDir.DIRECTORY_SEPARATOR.'test.html';
        file_put_contents($templatePath, '<!-- data-include="missing.html" -->');

        try {
            (new BlocsCompiler)->compile($templatePath);
            $this->fail('missing include で例外になりませんでした');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('B003:', $exception->getMessage());
        }

        $this->assertSame($original, getcwd());
    }
}
