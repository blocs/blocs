<?php

namespace html5_loop_validate;

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

        $this->assertArrayHasKey('rows.*.price', $rules);
        $this->assertSame(['max:10', 'required', 'integer', 'min:1'], $rules['rows.*.price']);
        foreach (array_keys($rules) as $formName) {
            $this->assertStringNotContainsString('<?php', $formName);
        }
    }
}
