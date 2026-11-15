<?php

namespace regression;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Compiler\BlocsCompiler;
use Blocs\Data\Convert;
use Blocs\Option;
use Blocs\Tests\TestEnvironment;
use Blocs\Validate;
use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 過去に見つかった不具合の再発を防ぐテスト
 */
class RegressionTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
        defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', sys_get_temp_dir());
        require_once dirname(__DIR__, 3).'/src/Consts.php';
    }

    private function writeTemplate(string $contents, string $name = 'test.html', ?string $dir = null): string
    {
        $dir ??= sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4));
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents($dir.'/'.$name, $contents);

        return str_replace(DIRECTORY_SEPARATOR, '/', realpath($dir.'/'.$name));
    }

    private function generate(string $contents, array $values = []): string
    {
        return (new View($this->writeTemplate($contents)))->generate($values);
    }

    #[Test, RunInSeparateProcess]
    public function dynamic_option_label_is_never_evaluated_as_template(): void
    {
        $template = $this->writeTemplate('<select name="cat"><option value="1">A</option></select>[<!-- $cat -->]');
        $view = new View($template);

        Option::add('cat', ['9' => "<?php echo 'PWNED'.(6*7); ?><script>x</script><i data-val=\$zz></i>"]);
        $html = $view->generate(['cat' => '9']);

        $this->assertStringNotContainsString('PWNED42', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('[&lt;?php echo', $html);
    }

    #[Test, RunInSeparateProcess]
    public function template_label_copied_by_option_set_is_still_rendered(): void
    {
        $dir = sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4));
        $label = '<b><!-- data-if="true" -->太字<!-- data-endif --></b>';
        $source = $this->writeTemplate('<select name="kind"><option value="1">'.$label.'</option></select>', 'source.html', $dir);
        $target = $this->writeTemplate('[<!-- $kind -->]', 'target.html', $dir.'-target');

        $view = new View($target);
        Option::set($source, 'kind');
        $this->assertSame('[<b>太字</b>]', preg_replace('/\s*\n\s*/', '', $view->generate(['kind' => '1'])));

        // 同じ内容でも Option::add() で追加したラベルはテンプレートとして解釈しない
        Option::add('kind', ['2' => $label]);
        $this->assertSame('['.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').']', trim($view->generate(['kind' => '2'])));
    }

    #[Test, RunInSeparateProcess]
    public function option_set_keeps_sequential_numeric_values(): void
    {
        $source = $this->writeTemplate('<select name="flag"><option value="0">無効</option><option value="1">有効</option></select>');

        Option::set($source, 'flag');

        $this->assertSame(['0', '1'], array_map('strval', array_column(Option::append()['flag'], 'value')));
        $this->assertSame(['無効', '有効'], array_column(Option::append()['flag'], 'label'));
    }

    #[Test, RunInSeparateProcess]
    public function option_add_accepts_sequential_numeric_values_when_told_not_a_value_list(): void
    {
        Option::add('flag', [0 => '無効', 1 => '有効'], false);
        $this->assertSame([0, 1], array_column(Option::append()['flag'], 'value'));

        // 既定では値だけのリストとして扱う（従来どおり）
        Option::add('size', ['S', 'M']);
        $this->assertSame(['S', 'M'], array_column(Option::append()['size'], 'value'));
    }

    #[Test, RunInSeparateProcess]
    public function alias_does_not_rewrite_attributes_that_only_start_with_alias_name(): void
    {
        $html = $this->generate('<p data-language="ja" data-repeater="1">x</p><i data-repeat=$rows><!-- $n --></i>', ['rows' => [['n' => 1], ['n' => 2]]]);

        $this->assertStringContainsString('<p data-language="ja" data-repeater="1">x</p>', $html);

        // 本来のエイリアス（data-repeat）は従来どおり data-loop として働く
        $this->assertSame('<i>1</i><i>2</i>', preg_replace('/\s*\n\s*/', '', explode('</p>', $html)[1]));
    }

    #[Test, RunInSeparateProcess]
    public function void_element_without_closing_slash_closes_condition_and_loop(): void
    {
        $template = '<img data-exist=$url src="a.png"><br data-none=$url><input type="hidden" data-loop=$rows name="id"><p>after</p>';

        $this->assertSame(
            '<img src="a.png"><input type="hidden" name="rows[0][id]" value="7"><p>after</p>',
            preg_replace('/\s*\n\s*/', '', $this->generate($template, ['url' => 'x', 'rows' => [['id' => 7]]]))
        );
        $this->assertSame('<br><p>after</p>', preg_replace('/\s*\n\s*/', '', $this->generate($template)));
    }

    #[Test, RunInSeparateProcess]
    public function loop_row_may_contain_internal_looking_keys(): void
    {
        $html = $this->generate('<ul><li data-loop=$items><!-- $name --></li></ul>', [
            'items' => [
                ['name' => 'a', 'parent' => ['id' => 1], 'workKey' => 1, 'dummyArray' => 1],
                ['name' => 'b', 'parent' => ['id' => 2], 'workKey' => 2, 'dummyArray' => 2],
            ],
        ]);

        $this->assertSame('<ul><li>a</li><li>b</li></ul>', preg_replace('/\s*\n\s*/', '', $html));
    }

    #[Test, RunInSeparateProcess]
    public function selected_is_decided_by_string_comparison(): void
    {
        $template = '<select name="code"><option value="1">a</option><option value="01">b</option><option value="1e1">c</option><option value="10">d</option></select>';

        $html = $this->generate($template, ['code' => '10']);
        $this->assertSame(1, substr_count($html, ' selected'));
        $this->assertStringContainsString('<option value="10" selected>', $html);

        $html = $this->generate($template, ['code' => 1]);
        $this->assertSame(1, substr_count($html, ' selected'));
        $this->assertStringContainsString('<option value="1" selected>', $html);
    }

    #[Test, RunInSeparateProcess]
    public function newline_is_not_converted_to_br_inside_attribute(): void
    {
        $html = $this->generate('<div :title=$text><!-- $text --></div>', ['text' => "a\nb"]);

        $this->assertStringContainsString("title=\"a\nb\"", $html);
        $this->assertStringContainsString("a<br />\nb</div>", $html);
    }

    #[Test, RunInSeparateProcess]
    public function default_value_with_dollar_digit_or_backslash_is_kept(): void
    {
        $html = $this->generate('<input type="text" name="price" value="US$5 \\\\1" />');

        $this->assertStringContainsString('value="US$5 \\\\1"', $html);
    }

    #[Test, RunInSeparateProcess]
    public function filter_is_stored_as_callable_and_applied_to_multiple_values(): void
    {
        $template = $this->writeTemplate(
            '<input type="text" name="tags[]" data-filter="single" /><input type="text" name="name" data-filter="single|katakana" />'
        );

        $config = Common::readConfig($template);
        $this->assertSame(['func' => 'Blocs\Data\Filter::single', 'args' => []], $config['filter'][$template]['tags'][0]);
        $this->assertStringNotContainsString('$value', json_encode($config['filter']));

        $filtered = Validate::filter($template, ['name' => 'ｱＢ', 'tags' => ['ＡＢ', 'ＣＤ']]);
        $this->assertSame('アB', $filtered['name']);
        $this->assertSame(['AB', 'CD'], $filtered['tags']);
    }

    #[Test, RunInSeparateProcess]
    public function filter_arguments_are_passed_as_values(): void
    {
        $template = $this->writeTemplate('<input type="text" name="word" data-filter="\regression\RegressionTest::wrap:\'[:\':2:true" />');

        $this->assertSame('[:x|2|true', Validate::filter($template, ['word' => 'x'])['word']);
    }

    public static function wrap($value, $prefix, $number, $flag): string
    {
        return $prefix.$value.'|'.var_export($number, true).'|'.var_export($flag, true);
    }

    #[Test, RunInSeparateProcess]
    public function invalid_date_is_returned_as_is(): void
    {
        $this->assertSame('not-a-date', Convert::date('not-a-date'));
        $this->assertSame('', Convert::date(null));
        $this->assertSame('2024/02/03', Convert::date('2024-02-03 10:00:00'));
    }

    #[Test, RunInSeparateProcess]
    public function validation_is_rebuilt_for_template_missing_from_existing_config(): void
    {
        $dir = sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4));
        $first = $this->writeTemplate('<input type="text" name="a" required />', 'first.html', $dir);
        $second = $this->writeTemplate('<input type="text" name="b" required />', 'second.html', $dir);

        // 同じディレクトリの first だけがコンパイル済み
        Common::readConfig($first);

        [$rules] = Validate::get($second);
        $this->assertSame(['b' => ['required']], $rules);
    }

    #[Test, RunInSeparateProcess]
    public function config_of_deleted_template_is_pruned(): void
    {
        $dir = sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4));
        $removed = $this->writeTemplate('<input type="hidden" name="doc" class="ai-upload" value="" /><select name="kind"><option value="1">A</option></select>', 'removed.html', $dir);
        $kept = $this->writeTemplate('<input type="text" name="b" />', 'kept.html', $dir);

        $config = Common::readConfig($removed);
        $this->assertArrayHasKey('doc', $config['upload']);
        $this->assertArrayHasKey('kind', $config['menu']);

        unlink($removed);
        touch($kept, time() + 1);
        (new View($kept))->generate();

        $config = Common::readConfig($kept);
        $this->assertArrayNotHasKey('doc', $config['upload']);
        $this->assertArrayNotHasKey('kind', $config['menu']);
        $this->assertArrayNotHasKey($removed, $config['timestamp']);
    }

    #[Test, RunInSeparateProcess]
    public function menu_label_comes_from_the_template_being_rendered(): void
    {
        $first = $this->writeTemplate('<select name="kind"><option value="1">一番目</option></select>[<!-- $kind -->]');
        $second = $this->writeTemplate('<select name="kind"><option value="1">二番目</option></select>[<!-- $kind -->]');

        $firstView = new View($first);
        $secondView = new View($second);

        // 直近に読み込んだ設定は second だが、first の表示には first の選択肢を使う
        $this->assertStringContainsString('[一番目]', $firstView->generate(['kind' => '1']));
        $this->assertStringContainsString('[二番目]', $secondView->generate(['kind' => '1']));
        $this->assertStringContainsString('[一番目]', $firstView->generate(['kind' => '1']));
    }

    #[Test, RunInSeparateProcess]
    public function option_value_containing_dollar_is_not_interpolated(): void
    {
        set_error_handler(function (int $errno, string $message): bool {
            throw new \ErrorException($message);
        });

        try {
            $html = $this->generate(
                '<input type="radio" name="a" value="$abc" /><input type="radio" name="a" value="{$x}" /><select name="s"><option value="a$b">x</option></select>',
                ['a' => '$abc', 's' => 'a$b']
            );
        } finally {
            restore_error_handler();
        }

        $this->assertStringContainsString('value="$abc" checked', $html);
        $this->assertStringContainsString('<option value="a$b" selected>', $html);
    }

    #[Test, RunInSeparateProcess]
    public function notice_argument_is_not_split_or_replaced_again(): void
    {
        $langPath = BLOCS_ROOT_DIR.'/lang.json';
        file_put_contents($langPath, json_encode(['msg:at' => 'at {1}', 'msg:hello' => 'Hello {1} and {2}']));

        try {
            $html = $this->generate(
                '[<!-- data-notice="msg:at:$time" -->][<!-- data-notice="msg:hello:$p:$q" -->][<!-- data-notice=$whole -->][<!-- data-notice="msg:at:a\\" -->]',
                ['time' => '10:30', 'p' => '{2}', 'q' => 'Q', 'whole' => 'msg:hello:A:B']
            );
        } finally {
            unlink($langPath);
        }

        // 値に含まれる「:」で引数が分かれない、値に含まれる {2} が再置換されない、末尾の \ で壊れない
        $this->assertSame('[at 10:30][Hello {2} and Q][Hello A and B][at a\\]', preg_replace('/\s*\n\s*/', '', $html));
    }

    #[Test, RunInSeparateProcess]
    public function comparison_operator_in_script_is_not_parsed_as_tag(): void
    {
        $template = "<script>\nif (a<b) return \$x \n; if (c>d) {}\nvar v = '<!-- \$word -->';\n</script><p data-val=\$word>x</p>";

        $html = $this->generate($template, ['word' => 'W', 'x' => 'X']);

        // scriptの中身はそのまま（コメント記法だけは使える）。scriptの後ろは通常どおり処理する
        $this->assertStringContainsString("if (a<b) return \$x \n; if (c>d) {}", $html);
        $this->assertSame("var v = 'W';", preg_replace('/\s*\n\s*/', '', explode("{}\n", explode('</script>', $html)[0])[1]));
        $this->assertStringContainsString('<p>W</p>', preg_replace('/\s*\n\s*/', '', $html));
    }

    #[Test, RunInSeparateProcess]
    public function include_update_is_detected_after_config_is_rebuilt(): void
    {
        $dir = sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4));
        $part = $this->writeTemplate('OLD-PART', 'part.html', $dir.'-part');
        $template = $this->writeTemplate('<!-- data-include="'.$part.'" -->', 'main.html', $dir);

        $this->assertStringContainsString('OLD-PART', (new View($template))->generate());

        // コンパイル済みファイルより後に include 先を更新し、設定ファイルは古い書式（作り直しの対象）にする
        $past = time() - 100;
        touch($template, $past);
        touch(BLOCS_CACHE_DIR.'/'.md5($template).'.php', $past + 10);
        file_put_contents($part, 'NEW-PART');
        touch($part, $past + 20);

        $configPath = Common::getConfigPath(dirname($template));
        $config = json_decode((string) file_get_contents($configPath), true);
        unset($config['version']);
        file_put_contents($configPath, json_encode($config));
        Common::flush();

        // 設定だけ作り直しても、コンパイル結果が古いままなら再コンパイルする
        $this->assertStringContainsString('NEW-PART', (new View($template))->generate());
        $this->assertStringContainsString('NEW-PART', (new View($template))->generate());
    }

    #[Test, RunInSeparateProcess]
    public function config_only_rebuild_does_not_mark_compiled_output_as_fresh(): void
    {
        $template = $this->writeTemplate('<input type="text" name="a" required />');

        // 表示せずに設定だけを作る（バリデーションの取得など）
        $config = Common::readConfig($template);

        $this->assertSame(0, $config['timestamp'][$template]);
        $this->assertTrue(Common::includesAreStale($template, $config));

        (new View($template))->generate();
        $config = Common::readConfig($template);

        $this->assertGreaterThan(0, $config['timestamp'][$template]);
        $this->assertFalse(Common::includesAreStale($template, $config));
    }

    #[Test, RunInSeparateProcess]
    public function read_config_with_missing_path_or_directory_does_not_keep_or_build_config(): void
    {
        $template = $this->writeTemplate('<select name="st"><option value="1">ONE</option></select>');
        Common::readConfig($template);
        $this->assertSame('ONE', Common::convertDefault('1', 'st'));

        // 存在しないパスでは、直前のテンプレートの選択肢を使わない
        $this->assertSame([], Common::readConfig(dirname($template).'/missing.html'));
        $this->assertSame('1', Common::convertDefault('1', 'st'));

        // ディレクトリはコンパイルしない（親ディレクトリ用の設定ファイルを作らない）
        $emptyDir = sys_get_temp_dir().'/blocs-regression-'.bin2hex(random_bytes(4)).'/sub';
        mkdir($emptyDir, 0755, true);
        $this->assertSame([], Common::readConfig($emptyDir));
        $this->assertFileDoesNotExist(Common::getConfigPath(dirname(str_replace(DIRECTORY_SEPARATOR, '/', realpath($emptyDir)))));
    }

    #[Test, RunInSeparateProcess]
    public function selected_treats_decimal_notation_of_same_number_as_equal(): void
    {
        $template = '<select name="rate"><option value="1">a</option><option value="1.5">b</option><option value="01">c</option></select>';

        $html = $this->generate($template, ['rate' => '1.00']);
        $this->assertSame(1, substr_count($html, ' selected'));
        $this->assertStringContainsString('<option value="1" selected>', $html);

        $html = $this->generate($template, ['rate' => '1.50']);
        $this->assertSame(1, substr_count($html, ' selected'));
        $this->assertStringContainsString('<option value="1.5" selected>', $html);
    }

    #[Test, RunInSeparateProcess]
    public function template_type_script_still_processes_data_attributes(): void
    {
        $html = $this->generate('<script type="text/template"><div data-val=$y>t</div></script>', ['y' => 'Y']);

        $this->assertSame('<script type="text/template"><div>Y</div></script>', preg_replace('/\s*\n\s*/', '', $html));
    }

    #[Test, RunInSeparateProcess]
    public function old_input_is_extracted_only_for_form_names(): void
    {
        $template = $this->writeTemplate(
            '<input type="text" name="title" /><table><tr data-loop=$rows><td><input type="text" name="qty" /></td></tr></table><!-- data-exist=$isAdmin -->x<!-- data-endexist -->'
        );

        $compiled = (new BlocsCompiler)->compile($template);

        $this->assertMatchesRegularExpression(
            "/extract\(array_intersect_key\(\\\$_oldValue, array \(\s*'title' => true,\s*'rows' => true,\s*\)\), EXTR_SKIP\)/",
            $compiled
        );
        $this->assertStringNotContainsString('extract($_oldValue', $compiled);
    }
}
