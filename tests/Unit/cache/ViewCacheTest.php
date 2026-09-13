<?php

namespace cache;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Common;
use Blocs\Tests\TestEnvironment;
use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ViewCacheTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
        defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', sys_get_temp_dir());
        require_once dirname(__DIR__, 3).'/src/Consts.php';
    }

    #[Test, RunInSeparateProcess]
    public function generate_recompiles_when_include_config_is_missing(): void
    {
        $dir = sys_get_temp_dir().'/blocs-view-cache-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/cache.html';
        file_put_contents($template, 'FIRST');

        $first = (new View($template))->generate();
        $this->assertSame('FIRST', trim($first));

        $path = str_replace(DIRECTORY_SEPARATOR, '/', realpath($template));
        $configPath = Common::getConfigPath(dirname($path));
        $config = json_decode((string) file_get_contents($configPath), true);
        unset($config['include'][$path]);
        file_put_contents($configPath, json_encode($config));

        file_put_contents($template, 'SECOND');
        $compiledPath = BLOCS_CACHE_DIR.'/'.md5($path).'.php';
        touch($compiledPath, time() - 10);

        $second = (new View($template))->generate();
        $this->assertSame('SECOND', trim($second));
    }

    #[Test, RunInSeparateProcess]
    public function write_config_leaves_no_lock_file_behind(): void
    {
        $dir = sys_get_temp_dir().'/blocs-view-cache-'.bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        $template = $dir.'/lock.html';
        file_put_contents($template, 'LOCK');

        (new View($template))->generate();

        $path = str_replace(DIRECTORY_SEPARATOR, '/', realpath($template));
        $configPath = Common::getConfigPath(dirname($path));

        // 設定ファイル自身をロックするので、.lock ファイルは残らない
        $this->assertFileExists($configPath);
        $this->assertFileDoesNotExist($configPath.'.lock');

        // 権限は 0644、内容は有効な JSON
        $this->assertSame(0644, fileperms($configPath) & 0777);
        $this->assertIsArray(json_decode((string) file_get_contents($configPath), true));
    }
}
