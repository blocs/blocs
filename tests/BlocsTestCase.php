<?php

namespace Blocs\Tests;

require_once __DIR__.'/TestEnvironment.php';

use Blocs\View;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class BlocsTestCase extends TestCase
{
    protected string $testDir;

    protected ?string $expected = null;

    protected ?string $actual = null;

    protected function setUp(): void
    {
        TestEnvironment::prepare();

        $this->testDir = dirname((new \ReflectionClass($this))->getFileName());

        touch($this->templatePath());

        $expectedPath = $this->testDir.'/expected.html';
        if (is_file($expectedPath)) {
            $this->expected = file_get_contents($expectedPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->actual !== null && ! is_file($this->testDir.'/expected.html')) {
            file_put_contents($this->testDir.'/expected.html', $this->actual);
        }
    }

    protected function templateFile(): string
    {
        return 'test.html';
    }

    protected function templatePath(): string
    {
        return $this->testDir.'/'.$this->templateFile();
    }

    protected function values(): mixed
    {
        return null;
    }

    protected function withFixer(): bool
    {
        return true;
    }

    protected function generate(mixed $val = null, ?bool $withFixer = null): string
    {
        return (new View($this->templatePath()))->generate(
            $val,
            $withFixer ?? $this->withFixer()
        );
    }

    protected function assertSnapshot(string $actual): void
    {
        $this->actual = $actual;
        $this->expected ??= $this->actual;

        $this->assertSame($this->expected, $this->actual);
    }

    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $this->assertSnapshot($this->generate($this->values()));
    }
}
