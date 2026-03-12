<?php

namespace push_menu;

use Blocs\Option;
use Blocs\View;
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

        touch($this->testDir.'/test.html');
        if (is_file($this->testDir.'/expected.html')) {
            $this->expected = file_get_contents($this->testDir.'/expected.html');
        }
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $blocs = new View($this->testDir.'/test.html');

        Option::add('type', 'sales');
        Option::add('type', ['service' => 'サービス']);

        Option::add('sex2', 'sales');
        Option::add('sex2', ['service' => 'サービス']);
        Option::add('sex2', ['part' => '品番']);

        Option::add('size', ['xl' => 'XL']);
        Option::add('size', ['xxl' => 'XXL']);

        $this->actual = $blocs->generate(null, true);
        $this->actual .= json_encode(Option::get($this->testDir.'/test.html', 'type')).'<br />';
        $this->actual .= json_encode(Option::get($this->testDir.'/test.html', 'sex2')).'<br />';
        $this->actual .= json_encode(Option::get($this->testDir.'/test.html', 'size')).'<br />';

        isset($this->expected) || $this->expected = $this->actual;
        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}
