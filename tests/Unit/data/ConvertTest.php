<?php

namespace data;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Data\Convert;
use Blocs\Tests\TestEnvironment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConvertTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
    }

    #[Test]
    public function jdate_replaces_full_weekday_names(): void
    {
        $this->assertSame('日曜日', Convert::jdate('2026-09-13', 'l'));
        $this->assertSame('日', Convert::jdate('2026-09-13', 'D'));
    }

    #[Test]
    public function hidden_counts_multibyte_characters(): void
    {
        $this->assertSame('**', Convert::hidden('あい'));
    }

    #[Test]
    public function raw_autolink_escapes_target_attribute(): void
    {
        $html = Convert::raw_autolink('https://example.com', '" onclick="alert(1)');

        $this->assertStringNotContainsString('onclick="alert(1)', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }
}
