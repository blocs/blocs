<?php

namespace B001;

use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected function setUp(): void
    {
    }

    /**
     * @runInSeparateProcess
     */
    public function test(): void
    {
        $this->expectError();
        $this->expectErrorMessageMatches('/^B001:/');

        define('BLOCS_CACHE_DIR', '/tmpx');

        $blocs = new \Blocs\View('test.html');
    }

    protected function tearDown(): void
    {
    }
}
