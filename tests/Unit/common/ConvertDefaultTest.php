<?php

namespace common;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Tests\TestEnvironment;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ConvertDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
        defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', sys_get_temp_dir());
        require_once dirname(__DIR__, 3).'/src/Consts.php';
    }

    #[Test, RunInSeparateProcess]
    public function convert_default_returns_empty_string_for_arrays(): void
    {
        $this->assertSame('', Common::convertDefault(['a']));
    }

    #[Test, RunInSeparateProcess]
    public function convert_default_escapes_menu_labels(): void
    {
        $config = new ReflectionProperty(Common::class, 'config');
        $config->setValue(null, [
            'menu' => [
                'status' => [
                    ['value' => '1', 'label' => '<script>alert(1)</script>'],
                ],
            ],
        ]);

        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Common::convertDefault('1', 'status')
        );
        $this->assertStringNotContainsString('<script>', Common::convertDefault('1', 'status'));
    }
}
