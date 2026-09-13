<?php

namespace html5_attr_case;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;
use Blocs\Validate;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $this->generate();
        [$rules] = Validate::get($this->templatePath());

        $this->assertSame(['required', 'numeric', 'min:1'], $rules['amount']);
    }
}
