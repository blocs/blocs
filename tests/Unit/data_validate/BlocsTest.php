<?php

namespace data_validate;

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
        $actual = $this->generate();
        [$rules, $messages] = Validate::get($this->templatePath());
        $actual .= json_encode($rules).'<br />';
        $actual .= json_encode($messages).'<br />';

        $this->assertSnapshot($actual);
    }
}
