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
        try {
            define('BLOCS_CACHE_DIR', '/tmpx');
            $blocs = new \Blocs\View('test.html');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertStringContainsString('B001:', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
    }
}
