<?php

namespace B001;

require_once dirname(__DIR__, 3).'/ErrorTestCase.php';

use Blocs\Tests\ErrorTestCase;
use Blocs\View;

class BlocsTest extends ErrorTestCase
{
    protected function errorCode(): string
    {
        return 'B001';
    }

    protected function prepareEnvironment(): void
    {
        // BLOCS_CACHE_DIR は triggerError で無効パスを先に定義する
    }

    protected function triggerError(): void
    {
        define('BLOCS_CACHE_DIR', '/tmpx');
        new View('test.html');
    }
}
