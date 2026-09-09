<?php

namespace flush;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Option;
use Blocs\Tests\TestEnvironment;
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
        $templatePath = dirname(__DIR__).'/get_label/test.html';
        $config = Common::readConfig($templatePath);
        $this->assertNotEmpty($config);

        // 引数なしの readConfig() は直近のテンプレート設定を返す
        $this->assertSame($config, Common::readConfig());

        Common::flush();

        // 破棄後は「テンプレート未指定」として空配列になる
        $this->assertSame([], Common::readConfig());
    }
}
