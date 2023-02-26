<?php

namespace B003;

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
        try {
            $blocs = new \Blocs\View($this->testDir.'/test.html');
            $this->actual = $blocs->generate();
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertStringContainsString('B003:', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
    }
}