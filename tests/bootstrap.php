<?php

$packageRoot = dirname(__DIR__);
// 1) パッケージ単体で composer install した場合
// 2) アプリの vendor/blocs/blocs として入っている場合
// 3) 環境変数 BLOCS_TEST_AUTOLOAD で任意のアプリの autoload.php を指定した場合
$autoloadCandidates = array_filter([
    getenv('BLOCS_TEST_AUTOLOAD') ?: null,
    $packageRoot.'/vendor/autoload.php',
    dirname($packageRoot, 2).'/autoload.php',
]);

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class) use ($packageRoot): bool {
    $map = [
        'Blocs\\Compiler\\' => $packageRoot.'/compiler/',
        'Blocs\\Data\\' => $packageRoot.'/data/',
        'Blocs\\Tests\\' => $packageRoot.'/tests/',
        'Blocs\\' => $packageRoot.'/src/',
    ];

    foreach ($map as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;

            return true;
        }
    }

    return false;
}, true, true);

require_once __DIR__.'/TestEnvironment.php';
require_once __DIR__.'/BlocsTestCase.php';
require_once __DIR__.'/ErrorTestCase.php';
