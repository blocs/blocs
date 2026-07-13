<?php

namespace get_label;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Option;
use Blocs\Tests\BlocsTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $path = $this->templatePath();
        $actual = $this->generate();
        $actual .= json_encode(Option::get($path, 'type')).'<br />';
        $actual .= json_encode(Option::get($path, 'size')).'<br />';
        $actual .= json_encode(Option::get($path, 'sex')).'<br />';
        $actual .= json_encode(Option::get($path, 'sex2')).'<br />';

        $this->assertSnapshot($actual);
    }
}
