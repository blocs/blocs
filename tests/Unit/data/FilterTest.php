<?php

namespace data;

require_once dirname(__DIR__, 2).'/TestEnvironment.php';

use Blocs\Data\Filter;
use Blocs\Tests\TestEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    protected function setUp(): void
    {
        TestEnvironment::prepare();
    }

    #[Test, DataProvider('postalCases')]
    public function postal_inserts_hyphen_only_for_seven_digit_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, Filter::postal($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function postalCases(): array
    {
        return [
            'seven digits' => ['1234567', '123-4567'],
            'already hyphenated' => ['123-4567', '123-4567'],
            'five digits stay unchanged' => ['12345', '12345'],
            'ten digits stay unchanged' => ['1234567890', '1234567890'],
        ];
    }
}
