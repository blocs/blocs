<?php

namespace upload_validation;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Tests\TestEnvironment;
use Blocs\Validate;
use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UploadValidationTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
        defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', sys_get_temp_dir());
        require_once dirname(__DIR__, 3).'/src/Consts.php';
    }

    #[Test, RunInSeparateProcess]
    public function ai_upload_without_validate_is_registered_in_config(): void
    {
        $dir = sys_get_temp_dir().'/blocs-upload-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/create.html';
        file_put_contents($template, '<input type="hidden" name="bloomberg_upload" class="ai-upload" value="" />');

        (new View($template))->generate();

        $path = str_replace(DIRECTORY_SEPARATOR, '/', realpath($template));
        $configPath = Common::getConfigPath(dirname($path));
        $config = json_decode((string) file_get_contents($configPath), true);

        $this->assertArrayHasKey('bloomberg_upload', $config['upload']);
        $this->assertArrayNotHasKey('validate', $config['upload']['bloomberg_upload']);
    }

    #[Test, RunInSeparateProcess]
    public function upload_returns_empty_rules_for_declared_field_without_validate(): void
    {
        $dir = sys_get_temp_dir().'/blocs-upload-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/create.html';
        file_put_contents($template, '<input type="hidden" name="bloomberg_upload" class="ai-upload" value="" />');

        (new View($template))->generate();

        $resolvedDir = str_replace(DIRECTORY_SEPARATOR, '/', realpath($dir));
        $result = Validate::upload($resolvedDir, 'bloomberg_upload');

        $this->assertIsArray($result);
        [$rules, $messages] = $result;
        $this->assertSame([], $rules);
        $this->assertSame([], $messages);
    }

    #[Test, RunInSeparateProcess]
    public function upload_returns_null_for_undeclared_field(): void
    {
        $dir = sys_get_temp_dir().'/blocs-upload-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/create.html';
        file_put_contents($template, '<input type="hidden" name="bloomberg_upload" class="ai-upload" value="" />');

        (new View($template))->generate();

        $resolvedDir = str_replace(DIRECTORY_SEPARATOR, '/', realpath($dir));
        $this->assertNull(Validate::upload($resolvedDir, 'not_declared'));
    }

    #[Test, RunInSeparateProcess]
    public function upload_returns_rules_when_validate_comment_exists(): void
    {
        $dir = sys_get_temp_dir().'/blocs-upload-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/create.html';
        file_put_contents($template, <<<'HTML'
<input type="hidden" name="doc_upload" class="ai-upload" value="" />
<!-- !doc_upload="max:10" -->
HTML);

        (new View($template))->generate();

        $resolvedDir = str_replace(DIRECTORY_SEPARATOR, '/', realpath($dir));
        $result = Validate::upload($resolvedDir, 'doc_upload');

        $this->assertIsArray($result);
        [$rules, $messages] = $result;
        $this->assertSame(['upload' => ['max:10']], $rules);
    }
}
