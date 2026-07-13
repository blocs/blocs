<?php

namespace Blocs\Tests;

final class TestEnvironment
{
    public static function prepare(): void
    {
        if (! defined('BLOCS_NO_LARAVEL')) {
            define('BLOCS_NO_LARAVEL', true);
        }

        if (! defined('BLOCS_CACHE_DIR')) {
            $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'blocs'.DIRECTORY_SEPARATOR;
            if (! is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }

            define('BLOCS_CACHE_DIR', $cacheDir);
        }
    }
}
