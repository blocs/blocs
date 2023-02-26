<?php

namespace x_apl_id;

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

        \Blocs\Option::set($this->testDir.'/../single_input/test.html', 'type');
        \Blocs\Option::set($this->testDir.'/../single_input/test.html', 'size');

        $val = [
            'name' => 'yada',
            'type' => 'private',
            'size' => "s\tl\txxl",
        ];
        $this->actual = $blocs->generate($val);

        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}