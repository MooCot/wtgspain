<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfferDateOnlyCastTest extends TestCase
{
    use RefreshDatabase;

    public function testCheckInIsStoredWithoutTimeComponent(): void
    {
        $offer = Offer::factory()->create([
            'check_in' => '2026-10-10 15:30:00',
            'check_out' => '2026-10-15',
        ]);

        $stored = DB::table('offers')->where('id', $offer->id)->value('check_in');

        $this->assertSame('2026-10-10', $stored);
    }

    public function testCheckInReadsBackAsDateOnly(): void
    {
        $offer = Offer::factory()->create([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
        ]);

        $fresh = $offer->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->check_in);
        $this->assertSame('2026-10-10', $fresh->check_in->toDateString());
        $this->assertSame('00:00:00', $fresh->check_in->toTimeString());
    }
}
