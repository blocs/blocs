<?php

namespace B004;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected $testDir;
    protected $actual;

    protected function setUp(): void
    {
        $this->testDir = __DIR__;

        // エラーを例外に変換
        set_error_handler(function ($severity, $message, $filename, $lineno) {
            throw new \ErrorException($message, 0, $severity, $filename, $lineno);
        });
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        try {
            $blocs = new \Blocs\View($this->testDir.'/test.html');
            $this->actual = $blocs->generate(null, true);
        } catch (\ErrorException $e) {
            $this->assertStringContainsString('B004:', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
    }
}
