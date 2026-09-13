<?php

namespace select_scope;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;

class BlocsTest extends BlocsTestCase
{
    protected function values(): mixed
    {
        return ['fruit' => 'apple'];
    }
}
