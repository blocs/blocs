<?php

namespace render_buffer;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Compiler\BlocsCompiler;
use Blocs\Tests\BlocsTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        // Blocs の定数を読み込むため、いったんテンプレートを生成する
        $this->generate();

        $compiler = new BlocsCompiler;

        // 正常系では出力バッファの深さが変わらない
        $baseLevel = ob_get_level();
        $this->assertSame('<span>OK</span>', trim($compiler->render('<span data-val="$word">x</span>', ['word' => 'OK'])));
        $this->assertSame($baseLevel, ob_get_level());

        $prefixHtml = trim($compiler->render('<span data-val="$word" data-prefix=$prefix>x</span>', [
            'word' => 'OK',
            'prefix' => '<script>x</script>',
        ]));
        $this->assertStringNotContainsString('<script>', $prefixHtml);
        $this->assertStringContainsString('&lt;script&gt;', $prefixHtml);

        // eval 中に例外が出てもバッファを積み残さない
        $thrown = null;
        try {
            $compiler->render('<!-- data-if="blocs_no_such_function_xyz()" -->x<!-- data-endif -->');
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'render() が例外を投げませんでした');
        $this->assertSame($baseLevel, ob_get_level(), '出力バッファが積み残されています');

        ob_start();
        $this->assertSame('<span>OK</span>', trim($compiler->render('<span data-val="$word">x</span>', [
            'word' => 'OK',
            'baseObLevel' => 0,
            '__blocs_compiled' => '<?php echo "hijacked";',
        ])));
        $this->assertSame($baseLevel + 1, ob_get_level(), 'extract() が出力バッファを破棄しています');
        ob_end_clean();
        $this->assertSame($baseLevel, ob_get_level());
    }
}
