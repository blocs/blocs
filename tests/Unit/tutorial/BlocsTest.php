<?php

namespace tutorial;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected $testDir;
    protected $expected;
    protected $actual;

    protected function setUp(): void
    {
        $this->testDir = __DIR__;

        touch($this->testDir.'/hello.html');
        if (is_file($this->testDir.'/expected.html')) {
            $this->expected = file_get_contents($this->testDir.'/expected.html');
        }
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $blocs = new \Blocs\View($this->testDir.'/hello.html');

        $val = ['message' => 'Hello, Linear!'];
        $this->actual = $blocs->generate($val, true);

        isset($this->expected) || $this->expected = $this->actual;
        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}
