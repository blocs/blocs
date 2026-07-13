<?php

namespace x_apl_id;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Option;
use Blocs\Tests\BlocsTestCase;

class BlocsTest extends BlocsTestCase
{
    protected function values(): mixed
    {
        Option::set($this->testDir.'/../single_input/test.html', 'type');
        Option::set($this->testDir.'/../single_input/test.html', 'size');

        return [
            'name' => 'yada',
            'type' => 'private',
            'size' => "s\tl\txxl",
        ];
    }
}
