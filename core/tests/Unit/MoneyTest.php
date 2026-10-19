<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    private Money $money;

    protected function setUp(): void
    {
        $this->money = new Money();
    }

    public function test_zero_decimal_currency_uses_whole_units(): void
    {
        self::assertSame('1200', $this->money->toMinor('1200', 0));
    }

    public function test_two_decimal_currency_uses_cents(): void
    {
        self::assertSame('123456', $this->money->toMinor('1234.56', 2));
        self::assertSame('1,234.56', $this->money->formatMinor('123456', 2));
    }

    public function test_three_decimal_currency_uses_thousandths(): void
    {
        self::assertSame('12345', $this->money->toMinor('12.345', 3));
        self::assertSame('12.345', $this->money->formatMinor('12345', 3));
    }

    public function test_extra_zero_places_are_allowed_but_extra_value_is_rejected(): void
    {
        self::assertSame('123', $this->money->toMinor('1.2300', 2));
        $this->expectException(InvalidArgumentException::class);
        $this->money->toMinor('1.231', 2);
    }

    public function test_integer_only_amounts_reject_grouping_and_exponents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->money->toMinor('1,200', 2);
    }
}
