<?php

namespace get_label;

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
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function test(): void
    {
        $blocs = new \Blocs\View($this->testDir.'/test.html');

        $this->actual = $blocs->generate(null, true);
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'type')).'<br />';
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'size')).'<br />';
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'sex')).'<br />';
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'sex2')).'<br />';

        isset($this->expected) || $this->expected = $this->actual;
        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}
