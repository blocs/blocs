<?php

$autoloadCandidates = [
    dirname(__DIR__, 3).'/autoload.php',
    dirname(__DIR__).'/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

require_once __DIR__.'/TestEnvironment.php';
require_once __DIR__.'/BlocsTestCase.php';
require_once __DIR__.'/ErrorTestCase.php';
