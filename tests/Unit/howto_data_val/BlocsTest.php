<?php

namespace howto_data_val;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;

class BlocsTest extends BlocsTestCase
{
    protected function templateFile(): string
    {
        return 'hello.html';
    }

    protected function values(): mixed
    {
        return ['price' => 100];
    }
}
