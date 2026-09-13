<?php

namespace html5_number;

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

        $this->assertSame(['nullable', 'numeric'], $rules['price']);
        $this->assertSame(['nullable', 'numeric', 'min:1'], $rules['qty']);
        $this->assertSame(['nullable', 'numeric', 'min:0', 'max:100'], $rules['amount']);
    }
}
