<?php

namespace B002_condition;

require_once dirname(__DIR__, 3).'/ErrorTestCase.php';

use Blocs\Tests\ErrorTestCase;

class BlocsTest extends ErrorTestCase
{
    protected function errorCode(): string
    {
        return 'B002';
    }
}
