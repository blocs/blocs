<?php

namespace data_include;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        define('BLOCS_ROOT_DIR', $this->testDir);

        $this->assertSnapshot($this->generate());
    }
}
