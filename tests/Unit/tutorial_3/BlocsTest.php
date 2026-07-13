<?php

namespace tutorial_3;

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
        return [
            'name' => 'Linear',
            'url' => 'http://www.linear.com/',
        ];
    }
}
