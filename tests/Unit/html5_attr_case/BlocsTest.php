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

        // step 省略なので整数のみ（type/属性名の大文字小文字は無視される）
        $this->assertSame(['required', 'integer', 'min:1'], $rules['amount']);
    }
}
