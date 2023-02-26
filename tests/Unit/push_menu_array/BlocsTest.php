<?php

namespace push_menu_array;

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

        \Blocs\Option::add('type', [
            'sales' => 'sales',
            'service' => 'サービス',
        ]);

        \Blocs\Option::add('sex2', [
            'sales' => 'sales',
            'service' => 'サービス',
            'part' => '品番',
        ]);

        \Blocs\Option::add('size', ['xl' => 'XL']);
        \Blocs\Option::add('size', ['xxl' => 'XXL']);

        $this->actual = $blocs->generate();
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'type')).'<br />';
        $this->actual .= json_encode(\Blocs\Option::get($this->testDir.'/test.html', 'sex2')).'<br />';

        $this->assertSame($this->expected, $this->actual);
    }

    protected function tearDown(): void
    {
        is_file($this->testDir.'/expected.html') || file_put_contents($this->testDir.'/expected.html', $this->actual);
    }
}
