<?php

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\Models\Casts\DateOnlyCast;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DateOnlyCastTest extends TestCase
{
    public function testGetReturnsNullForNullValue(): void
    {
        $cast = new DateOnlyCast;

        $this->assertNull($cast->get(new Offer, 'check_in', null, []));
    }

    public function testGetTruncatesTimeComponent(): void
    {
        $cast = new DateOnlyCast;

        $date = $cast->get(new Offer, 'check_in', '2026-10-10 15:30:00', []);

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertSame('2026-10-10', $date->toDateString());
        $this->assertSame('00:00:00', $date->toTimeString());
    }

    public function testSetReturnsNullForNullValue(): void
    {
        $cast = new DateOnlyCast;

        $this->assertNull($cast->set(new Offer, 'check_in', null, []));
    }

    public function testSetFormatsAsDateOnlyRegardlessOfTimeInInput(): void
    {
        $cast = new DateOnlyCast;

        $stored = $cast->set(new Offer, 'check_in', '2026-10-10 15:30:00', []);

        $this->assertSame('2026-10-10', $stored);
    }
}
