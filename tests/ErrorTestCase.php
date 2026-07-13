<?php

namespace Blocs\Tests;

require_once __DIR__.'/TestEnvironment.php';

use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class ErrorTestCase extends TestCase
{
    protected function setUp(): void
    {
        set_error_handler(function (int $errno, string $message, string $filename, int $lineno): bool {
            throw new \ErrorException($message, 0, $errno, $filename, $lineno);
        });

        $this->prepareEnvironment();
    }

    protected function prepareEnvironment(): void
    {
        TestEnvironment::prepare();
    }

    abstract protected function errorCode(): string;

    protected function triggerError(): void
    {
        $testDir = dirname((new \ReflectionClass($this))->getFileName());
        (new View($testDir.'/test.html'))->generate(null, true);
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/'.$this->errorCode().':/');

        $this->triggerError();
    }
}
