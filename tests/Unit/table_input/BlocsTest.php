<?php

namespace table_input;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;

class BlocsTest extends BlocsTestCase
{
    protected function values(): mixed
    {
        return [
            'matrix2' => [[], [
                'text' => 'yada',
                'size' => "s\tl",
                'area' => 'テストです。',
            ]],
            'matrix' => [[
                'text' => 'yada',
                'sex' => 'f',
            ], []],
        ];
    }
}
