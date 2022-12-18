<?php

namespace B002;

use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected $testDir;
    protected $actual;

    protected function setUp(): void
    {
        $this->testDir = __DIR__;
    }

    /**
     * @runInSeparateProcess
     */
    public function test(): void
    {
        $this->expectError();
        $this->expectErrorMessageMatches('/^B002:/');

        $blocs = new \Blocs\View($this->testDir.'/test.html');
        $this->actual = $blocs->generate();
    }

    protected function tearDown(): void
    {
    }
}
