<?php

namespace data_convert;

use Blocs\Tests\BlocsTestCase;
use Blocs\Validate;
use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';
require_once __DIR__.'/convert_func.php';

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $blocs = new View($this->templatePath());
        $val = Validate::filter($blocs->getPath(), ['name' => '     あいうえお     ']);

        $this->assertSnapshot($blocs->generate($val, true));
    }
}
