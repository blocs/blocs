<?php

namespace B001;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BlocsTest extends TestCase
{
    protected function setUp(): void
    {
        // エラーを例外に変換
        set_error_handler(function ($severity, $message, $filename, $lineno) {
            throw new \ErrorException($message, 0, $severity, $filename, $lineno);
        });
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        try {
            define('BLOCS_CACHE_DIR', '/tmpx');
            $blocs = new \Blocs\View('test.html');
        } catch (\ErrorException $e) {
            $this->assertStringContainsString('B001:', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
    }
}
