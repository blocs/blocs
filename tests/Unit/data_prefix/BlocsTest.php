<?php

namespace data_prefix;

use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected $testDir;
    protected $expected;
    protected $actual;

    protected function setUp(): void
    {
        $this->testDir = __DIR__;

        touch($this->testDir.'/test.html');
        if (is_file($this->testDir.'/expected.html')) {
            $this->expected = file_get_contents($this->testDir.'/expected.html');
        } else {
            $this->expected = '';
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function test(): void
    {
        $blocs = new \Blocs\View($this->testDir.'/test.html');
        $this->actual = $blocs->generate();

        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}
